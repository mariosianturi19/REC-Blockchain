#!/usr/bin/env bash
set -e

export COMPOSE_FILE=./docker-compose.yaml
export COMPOSE_PROJECT_NAME=rec-project
export FABRIC_CFG_PATH=${PWD}

export COMPOSE_UID=$(id -u)
export COMPOSE_GID=$(id -g)

CHANNEL_NAME="recchannel"
MAIN_CHANNEL="mainrec"

CC_NAME="rec"
CC_VERSION="1.0"
CC_SEQUENCE="1"
CC_SRC_PATH_IN_CONTAINER="/opt/gopath/src/github.com/chaincode/lib/"

AUTO_DEPLOY=false
CREATE_MAIN_CHANNEL=false
NETWORK_ONLY=true
AUTO_APPROVE_COMMIT=false

# Parse arguments
if [[ "$2" == "--auto" ]]; then AUTO_DEPLOY=true; fi
if [[ "$3" == "--main" ]] || [[ "$2" == "--main" ]]; then CREATE_MAIN_CHANNEL=true; fi
if [[ "$2" == "--with-chaincode" ]]; then NETWORK_ONLY=false; fi
if [[ "$2" == "--auto-approve" ]] || [[ "$3" == "--auto-approve" ]] || [[ "$4" == "--auto-approve" ]]; then 
  AUTO_APPROVE_COMMIT=true
  NETWORK_ONLY=false
fi

# Fungsi utilitas

# Fungsi untuk membersihkan network yang konflik
cleanupNetworks() {
  echo "Cleaning up conflicting networks..."
  
  # Hapus network yang mungkin konflik
  docker network rm rec-blockchain-network >/dev/null 2>&1 || true
  docker network rm ${COMPOSE_PROJECT_NAME}_rec-net >/dev/null 2>&1 || true
  
  # Hapus network orphan lainnya yang mungkin ada
  docker network ls --format "table {{.Name}}" | grep -E "(rec-|blockchain)" | while read network; do
    if [ "$network" != "NAME" ]; then
      docker network rm "$network" >/dev/null 2>&1 || true
    fi
  done
  
  # Clean up unused networks
  docker network prune -f >/dev/null 2>&1 || true
  
  echo "Network cleanup completed"
}

# Menghentikan dan menghapus semua kontainer terkait proyek serta dev-peer
clearContainers() {
  echo "Menghapus kontainer dan network..."
  
  # Stop dan hapus containers dengan docker compose
  docker compose -f "$COMPOSE_FILE" -p "$COMPOSE_PROJECT_NAME" down --volumes --remove-orphans || true
  
  # Hapus containers yang mungkin tertinggal
  docker rm -f $(docker ps -aq --filter "name=${COMPOSE_PROJECT_NAME}") >/dev/null 2>&1 || true
  docker rm -f $(docker ps -a | grep "dev-peer" | awk '{print $1}') >/dev/null 2>&1 || true
  
  # Bersihkan network yang konflik
  cleanupNetworks
  
  # Clean up containers dan volumes yang tidak terpakai
  docker container prune -f >/dev/null 2>&1 || true
  docker volume prune -f >/dev/null 2>&1 || true
  
  echo "Container and network cleanup completed"
}

# Menghapus artefak lama (kecuali direktori CA) dan menyiapkan struktur direktori
removeOldArtifacts() {
  echo "Menghapus artefak..."
  # Hapus organisasi orderer/peer untuk regenerasi, tetapi JANGAN hapus CA
  rm -rf ./organizations/ordererOrganizations ./organizations/peerOrganizations
  rm -rf ./system-genesis-block/* ./channel-artifacts/*
  mkdir -p ./system-genesis-block ./channel-artifacts
}

# Unduh binary Fabric & CA jika belum ada
downloadFabricBinaries() {
  if [ ! -d "bin" ]; then
    echo "Mengunduh Fabric binaries v2.5.13 & CA v1.5.15..."
    curl -sSLO https://raw.githubusercontent.com/hyperledger/fabric/main/scripts/install-fabric.sh && chmod +x install-fabric.sh
    ./install-fabric.sh binary --fabric-version 2.5.13 --ca-version 1.5.15
    rm -f install-fabric.sh  # Clean up after use
  fi
}

# Generate materi kripto dari crypto-config.yaml
generateCrypto() {
  echo "Generate crypto..."
  ./bin/cryptogen generate --config=./crypto-config.yaml --output="./organizations"
}

# Membuat genesis block untuk orderer system-channel
createGenesisBlock() {
  echo "Membuat genesis block..."
  ./bin/configtxgen -profile RECOrdererGenesis -channelID system-channel -outputBlock ./system-genesis-block/genesis.block -configPath .
}

# Menghidupkan jaringan docker dengan network cleanup
networkUp() {
  downloadFabricBinaries
  generateCrypto
  
  # Buat direktori CA agar persisten dan pemiliknya benar
  mkdir -p ./organizations/fabric-ca/orderer \
           ./organizations/fabric-ca/generator \
           ./organizations/fabric-ca/issuer \
           ./organizations/fabric-ca/buyer
           
  createGenesisBlock
  
  # Pastikan network bersih sebelum memulai
  echo "Ensuring clean network environment..."
  cleanupNetworks
  
  echo "Menjalankan docker containers..."
  docker compose -f "$COMPOSE_FILE" -p "$COMPOSE_PROJECT_NAME" up -d
  
  # Wait untuk memastikan containers siap
  echo "Waiting for containers to be ready..."
  sleep 10
  
  docker ps -a
}

# Operasi channel

# Membuat channel genesis transaction dan genesis block
createChannel() {
  echo "Membuat REC Application Channel..."
  
  # Generate channel creation transaction
  ./bin/configtxgen -profile RECApplicationChannel \
    -outputCreateChannelTx ./channel-artifacts/${CHANNEL_NAME}.tx \
    -channelID "$CHANNEL_NAME" \
    -configPath .
  
  if [ $? -ne 0 ]; then
    echo "Failed to generate channel creation transaction"
    exit 1
  fi

  # Create the channel using CLI container
  echo "Creating channel ${CHANNEL_NAME}..."
  docker exec cli peer channel create \
    -o orderer.rec.com:7050 \
    -c "$CHANNEL_NAME" \
    --ordererTLSHostnameOverride orderer.rec.com \
    -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL_NAME}.tx \
    --outputBlock /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL_NAME}.block \
    --tls \
    --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem

  if [ $? -ne 0 ]; then
    echo "Failed to create channel ${CHANNEL_NAME}"
    exit 1
  fi

  echo "Channel ${CHANNEL_NAME} created successfully"
  
  # Join all peers to channel
  joinChannel

  # Create main channel if requested
  if $CREATE_MAIN_CHANNEL; then
    createMainChannel
  fi
}

# Create additional main channel
createMainChannel() {
  echo "Membuat Main REC Channel..."
  
  # Generate main channel creation transaction
  ./bin/configtxgen -profile RECMainChannel \
    -outputCreateChannelTx ./channel-artifacts/${MAIN_CHANNEL}.tx \
    -channelID "$MAIN_CHANNEL" \
    -configPath .
  
  if [ $? -ne 0 ]; then
    echo "Failed to generate main channel creation transaction"
    exit 1
  fi

  # Create the main channel
  echo "Creating main channel ${MAIN_CHANNEL}..."
  docker exec cli peer channel create \
    -o orderer.rec.com:7050 \
    -c "$MAIN_CHANNEL" \
    --ordererTLSHostnameOverride orderer.rec.com \
    -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${MAIN_CHANNEL}.tx \
    --outputBlock /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${MAIN_CHANNEL}.block \
    --tls \
    --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem

  if [ $? -ne 0 ]; then
    echo "Failed to create main channel ${MAIN_CHANNEL}"
    exit 1
  fi

  echo "Main channel ${MAIN_CHANNEL} created successfully"
  
  # Join all peers to main channel
  joinMainChannel
}

# Helper untuk join satu peer ke channel dengan validasi
joinOne() {
  local MSP="$1"
  local HOST="$2"
  local PORT="$3"
  local DOM="$4"
  local PEERNAME="$5"
  local CHANNEL="$6"
  
  echo "Joining ${PEERNAME} (${MSP}) to channel ${CHANNEL}..."
  
  docker exec \
    -e CORE_PEER_LOCALMSPID=$MSP \
    -e CORE_PEER_ADDRESS=${HOST}:${PORT} \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/users/Admin@${DOM}/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/peers/${PEERNAME}/tls/ca.crt \
    cli peer channel join -b /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL}.block

  if [ $? -ne 0 ]; then
    echo "Failed to join ${PEERNAME} to channel ${CHANNEL}"
    exit 1
  fi
  
  echo "${PEERNAME} successfully joined channel ${CHANNEL}"
}

# Join semua peer ke channel utama, lalu update anchor peer
joinChannel() {
  echo "Join All Peers to Channel ${CHANNEL_NAME}..."
  
  # Generator Organization Peers
  joinOne GeneratorMSP peer0.generator.rec.com 7051 generator.rec.com peer0.generator.rec.com $CHANNEL_NAME
  joinOne GeneratorMSP peer1.generator.rec.com 8051 generator.rec.com peer1.generator.rec.com $CHANNEL_NAME
  
  # Issuer Organization Peers
  joinOne IssuerMSP peer0.issuer.rec.com 9051 issuer.rec.com peer0.issuer.rec.com $CHANNEL_NAME
  joinOne IssuerMSP peer1.issuer.rec.com 10051 issuer.rec.com peer1.issuer.rec.com $CHANNEL_NAME
  
  # Buyer Organization Peers  
  joinOne BuyerMSP peer0.buyer.rec.com 11051 buyer.rec.com peer0.buyer.rec.com $CHANNEL_NAME
  joinOne BuyerMSP peer1.buyer.rec.com 12051 buyer.rec.com peer1.buyer.rec.com $CHANNEL_NAME
  
  echo "All peers successfully joined channel ${CHANNEL_NAME}"
  
  # Update anchor peers
  updateAnchorPeers $CHANNEL_NAME
}

# Join semua peer ke main channel
joinMainChannel() {
  echo "Join All Peers to Main Channel ${MAIN_CHANNEL}..."
  
  # Generator Organization Peers
  joinOne GeneratorMSP peer0.generator.rec.com 7051 generator.rec.com peer0.generator.rec.com $MAIN_CHANNEL
  joinOne GeneratorMSP peer1.generator.rec.com 8051 generator.rec.com peer1.generator.rec.com $MAIN_CHANNEL
  
  # Issuer Organization Peers
  joinOne IssuerMSP peer0.issuer.rec.com 9051 issuer.rec.com peer0.issuer.rec.com $MAIN_CHANNEL
  joinOne IssuerMSP peer1.issuer.rec.com 10051 issuer.rec.com peer1.issuer.rec.com $MAIN_CHANNEL
  
  # Buyer Organization Peers  
  joinOne BuyerMSP peer0.buyer.rec.com 11051 buyer.rec.com peer0.buyer.rec.com $MAIN_CHANNEL
  joinOne BuyerMSP peer1.buyer.rec.com 12051 buyer.rec.com peer1.buyer.rec.com $MAIN_CHANNEL
  
  echo "All peers successfully joined main channel ${MAIN_CHANNEL}"
  
  # Update anchor peers for main channel
  updateAnchorPeers $MAIN_CHANNEL
}

# Generate dan update anchor peer untuk setiap organisasi
updateAnchorPeers() {
  local CHANNEL_TO_UPDATE="$1"
  echo "Update Anchor Peers for Channel ${CHANNEL_TO_UPDATE}..."
  
  # Generate anchor peer updates for each organization
  echo "Generating anchor peer updates..."
  ./bin/configtxgen -profile RECApplicationChannel \
    -outputAnchorPeersUpdate ./channel-artifacts/GeneratorMSPanchors.tx \
    -channelID "$CHANNEL_TO_UPDATE" \
    -asOrg GeneratorMSP \
    -configPath .
    
  ./bin/configtxgen -profile RECApplicationChannel \
    -outputAnchorPeersUpdate ./channel-artifacts/IssuerMSPanchors.tx \
    -channelID "$CHANNEL_TO_UPDATE" \
    -asOrg IssuerMSP \
    -configPath .
    
  ./bin/configtxgen -profile RECApplicationChannel \
    -outputAnchorPeersUpdate ./channel-artifacts/BuyerMSPanchors.tx \
    -channelID "$CHANNEL_TO_UPDATE" \
    -asOrg BuyerMSP \
    -configPath .

  # Update anchor peer for Generator organization
  echo "Updating anchor peer for GeneratorMSP..."
  docker exec \
    -e CORE_PEER_LOCALMSPID=GeneratorMSP \
    -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
    cli peer channel update \
      -o orderer.rec.com:7050 \
      --ordererTLSHostnameOverride orderer.rec.com \
      -c "$CHANNEL_TO_UPDATE" \
      -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/GeneratorMSPanchors.tx \
      --tls \
      --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem

  # Update anchor peer for Issuer organization
  echo "Updating anchor peer for IssuerMSP..."
  docker exec \
    -e CORE_PEER_LOCALMSPID=IssuerMSP \
    -e CORE_PEER_ADDRESS=peer0.issuer.rec.com:9051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/users/Admin@issuer.rec.com/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt \
    cli peer channel update \
      -o orderer.rec.com:7050 \
      --ordererTLSHostnameOverride orderer.rec.com \
      -c "$CHANNEL_TO_UPDATE" \
      -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/IssuerMSPanchors.tx \
      --tls \
      --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem

  # Update anchor peer for Buyer organization
  echo "Updating anchor peer for BuyerMSP..."
  docker exec \
    -e CORE_PEER_LOCALMSPID=BuyerMSP \
    -e CORE_PEER_ADDRESS=peer0.buyer.rec.com:11051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/users/Admin@buyer.rec.com/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt \
    cli peer channel update \
      -o orderer.rec.com:7050 \
      --ordererTLSHostnameOverride orderer.rec.com \
      -c "$CHANNEL_TO_UPDATE" \
      -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/BuyerMSPanchors.tx \
      --tls \
      --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem

  echo "Anchor peers updated successfully for channel ${CHANNEL_TO_UPDATE}"
}

# Operasi chaincode (IMPROVED WITH AUTO APPROVE & COMMIT)

# Pastikan path chaincode ada dalam container CLI
checkChaincodePath() {
  if [ "$NETWORK_ONLY" = true ]; then
    echo "Skipping chaincode path check - Network architecture focus mode"
    return 0
  fi
  
  docker exec cli bash -lc "test -d '${CC_SRC_PATH_IN_CONTAINER}'" || { 
    echo "WARNING: Chaincode folder tidak ditemukan: ${CC_SRC_PATH_IN_CONTAINER}"
    echo "Pastikan blockchain developer sudah menyediakan chaincode di folder ./chaincode/lib/"
    return 1
  }
}

# Package chaincode
packageChaincode() {
  echo "==== PACKAGING CHAINCODE ===="
  echo "Packaging chaincode ${CC_NAME} v${CC_VERSION}..."
  
  # Check if chaincode path exists
  checkChaincodePath || return 1
  
  # Package chaincode
  docker exec cli peer lifecycle chaincode package ${CC_NAME}_${CC_VERSION}.tar.gz \
    --path ${CC_SRC_PATH_IN_CONTAINER} \
    --lang node \
    --label ${CC_NAME}_${CC_VERSION}
  
  if [ $? -ne 0 ]; then
    echo "ERROR: Failed to package chaincode"
    exit 1
  fi
  
  echo "SUCCESS: Chaincode package created: ${CC_NAME}_${CC_VERSION}.tar.gz"
}

# Install chaincode di semua peer
installChaincode() {
  echo "==== INSTALLING CHAINCODE ===="
  echo "Installing chaincode ${CC_NAME} v${CC_VERSION} on all peers..."
  
  for entry in \
    "GeneratorMSP peer0.generator.rec.com 7051 generator.rec.com peer0.generator.rec.com" \
    "GeneratorMSP peer1.generator.rec.com 8051 generator.rec.com peer1.generator.rec.com" \
    "IssuerMSP    peer0.issuer.rec.com    9051 issuer.rec.com    peer0.issuer.rec.com" \
    "IssuerMSP    peer1.issuer.rec.com    10051 issuer.rec.com   peer1.issuer.rec.com" \
    "BuyerMSP     peer0.buyer.rec.com     11051 buyer.rec.com    peer0.buyer.rec.com" \
    "BuyerMSP     peer1.buyer.rec.com     12051 buyer.rec.com    peer1.buyer.rec.com"
  do
    set -- $entry
    MSP=$1; HOST=$2; PORT=$3; DOM=$4; PEER=$5
    
    echo "Installing on ${PEER}..."
    docker exec \
      -e CORE_PEER_LOCALMSPID=$MSP \
      -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/peers/${PEER}/tls/ca.crt \
      -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/users/Admin@${DOM}/msp \
      -e CORE_PEER_ADDRESS=${HOST}:${PORT} \
      cli peer lifecycle chaincode install ${CC_NAME}_${CC_VERSION}.tar.gz
      
    if [ $? -ne 0 ]; then
      echo "ERROR: Failed to install chaincode on ${PEER}"
      exit 1
    fi
  done
  
  echo "SUCCESS: Chaincode installed on all peers"
}

# Auto approve chaincode untuk semua organisasi
autoApproveChaincode() {
  echo "==== AUTO APPROVING CHAINCODE ===="
  echo "Auto approving chaincode ${CC_NAME} v${CC_VERSION} for all organizations..."
  
  # Query installed chaincode untuk mendapatkan package ID
  echo "Querying installed chaincode untuk mendapatkan Package ID..."
  docker exec \
    -e CORE_PEER_LOCALMSPID=GeneratorMSP \
    -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
    cli peer lifecycle chaincode queryinstalled > /tmp/chaincode_query.log 2>&1
    
  PACKAGE_ID=$(sed -n "/Package ID: ${CC_NAME}_${CC_VERSION}/,/Label:/p" /tmp/chaincode_query.log | sed -n 's/Package ID: //; s/, Label:.*//p')
  rm -f /tmp/chaincode_query.log
  echo "Package ID: $PACKAGE_ID"
  
  if [ -z "$PACKAGE_ID" ]; then
    echo "ERROR: Package ID not found. Pastikan chaincode sudah di-install."
    exit 1
  fi

  # Auto approve chaincode untuk setiap organisasi
  for entry in \
    "GeneratorMSP peer0.generator.rec.com 7051 generator.rec.com" \
    "IssuerMSP    peer0.issuer.rec.com    9051 issuer.rec.com" \
    "BuyerMSP     peer0.buyer.rec.com     11051 buyer.rec.com"
  do
    set -- $entry
    MSP=$1; HOST=$2; PORT=$3; DOM=$4
    
    echo "Auto approving chaincode untuk ${MSP}..."
    docker exec \
      -e CORE_PEER_LOCALMSPID=$MSP \
      -e CORE_PEER_ADDRESS=${HOST}:${PORT} \
      -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/users/Admin@${DOM}/msp \
      -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/peers/${HOST}/tls/ca.crt \
      cli peer lifecycle chaincode approveformyorg \
        -o orderer.rec.com:7050 \
        --ordererTLSHostnameOverride orderer.rec.com \
        --channelID $CHANNEL_NAME \
        --name $CC_NAME \
        --version $CC_VERSION \
        --package-id $PACKAGE_ID \
        --sequence $CC_SEQUENCE \
        --tls \
        --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
        
    if [ $? -ne 0 ]; then
      echo "ERROR: Failed to approve chaincode untuk ${MSP}"
      exit 1
    fi
    
    echo "SUCCESS: Chaincode approved untuk ${MSP}"
  done
  
  echo "SUCCESS: All organizations have approved the chaincode"
}

# Check commit readiness
checkCommitReadiness() {
  echo "==== CHECKING COMMIT READINESS ===="
  echo "Checking commit readiness for chaincode ${CC_NAME} v${CC_VERSION}..."
  
  docker exec \
    -e CORE_PEER_LOCALMSPID=GeneratorMSP \
    -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
    cli peer lifecycle chaincode checkcommitreadiness \
      --channelID $CHANNEL_NAME \
      --name $CC_NAME \
      --version $CC_VERSION \
      --sequence $CC_SEQUENCE \
      --tls \
      --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem \
      --output json
      
  echo "Commit readiness check completed"
}

autoCommitChaincode() {
  echo "==== AUTO COMMITTING CHAINCODE ===="
  echo "Auto committing chaincode ${CC_NAME} v${CC_VERSION} to channel ${CHANNEL_NAME}..."
  
  # Commit from GeneratorMSP
  docker exec \
    -e CORE_PEER_LOCALMSPID=GeneratorMSP \
    -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
    cli peer lifecycle chaincode commit \
      -o orderer.rec.com:7050 \
      --ordererTLSHostnameOverride orderer.rec.com \
      --channelID $CHANNEL_NAME \
      --name $CC_NAME \
      --version $CC_VERSION \
      --sequence $CC_SEQUENCE \
      --tls \
      --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem \
      --peerAddresses peer0.generator.rec.com:7051 \
      --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
      --peerAddresses peer0.issuer.rec.com:9051 \
      --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt \
      --peerAddresses peer0.buyer.rec.com:11051 \
      --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt

  # Commit from IssuerMSP (tambahan untuk memenuhi mayoritas)
  docker exec \
    -e CORE_PEER_LOCALMSPID=IssuerMSP \
    -e CORE_PEER_ADDRESS=peer0.issuer.rec.com:9051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/users/Admin@issuer.rec.com/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt \
    cli peer lifecycle chaincode commit \
      -o orderer.rec.com:7050 \
      --ordererTLSHostnameOverride orderer.rec.com \
      --channelID $CHANNEL_NAME \
      --name $CC_NAME \
      --version $CC_VERSION \
      --sequence $CC_SEQUENCE \
      --tls \
      --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem \
      --peerAddresses peer0.generator.rec.com:7051 \
      --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
      --peerAddresses peer0.issuer.rec.com:9051 \
      --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt \
      --peerAddresses peer0.buyer.rec.com:11051 \
      --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt

  if [ $? -ne 0 ]; then
    echo "ERROR: Failed to commit chaincode"
    exit 1
  fi

  echo "SUCCESS: Chaincode committed successfully to channel ${CHANNEL_NAME}"
}

# Query committed chaincode untuk verifikasi
queryCommittedChaincode() {
  echo "==== VERIFYING COMMITTED CHAINCODE ===="
  echo "Verifying committed chaincode ${CC_NAME} v${CC_VERSION}..."
  
  docker exec \
    -e CORE_PEER_LOCALMSPID=GeneratorMSP \
    -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
    cli peer lifecycle chaincode querycommitted \
      --channelID $CHANNEL_NAME \
      --name $CC_NAME \
      --tls \
      --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
      
  echo "Chaincode verification completed"
}

# Deploy chaincode dengan auto approve dan commit
deployChaincode() {
  if [ "$NETWORK_ONLY" = true ]; then
    echo "Chaincode Deployment Skipped - Running in network architecture mode"
    echo "Para blockchain developer bisa deploy chaincode dengan: ./network.sh deploy-chaincode"
    return 0
  fi
  
  echo "========================================"
  echo "    CHAINCODE DEPLOYMENT STARTED"
  echo "========================================"
  echo "Deploying chaincode: ${CC_NAME} v${CC_VERSION}"
  echo "Target channel: ${CHANNEL_NAME}"
  echo "Auto approve: ${AUTO_APPROVE_COMMIT}"
  echo ""
  
  # Step 1: Package chaincode
  packageChaincode
  echo ""
  
  # Step 2: Install chaincode
  installChaincode
  echo ""
  
  # Step 3: Auto approve dan commit jika diminta
  if $AUTO_APPROVE_COMMIT; then
    # Step 3a: Auto approve
    autoApproveChaincode
    echo ""
    
    # Step 3b: Check commit readiness
    checkCommitReadiness
    echo ""
    
    # Step 3c: Auto commit
    autoCommitChaincode
    echo ""
    
    # Step 3d: Verify
    queryCommittedChaincode
    echo ""
    
    echo "========================================"
    echo "  CHAINCODE DEPLOYMENT COMPLETED!"
    echo "========================================"
    echo "✅ Chaincode ${CC_NAME} v${CC_VERSION} berhasil di-deploy!"
    echo "✅ Semua organisasi telah auto approve dan commit"
    echo "✅ Chaincode siap digunakan di channel ${CHANNEL_NAME}"
  else
    echo "========================================"
    echo "  CHAINCODE PACKAGED & INSTALLED"
    echo "========================================"
    echo "✅ Chaincode ${CC_NAME} v${CC_VERSION} berhasil di-package dan install!"
    echo "📝 Untuk approve dan commit, gunakan: ./network.sh approve-commit"
  fi
}

# Fungsi untuk approve dan commit saja (untuk manual control)
approveAndCommitChaincode() {
  echo "========================================"
  echo "    CHAINCODE APPROVE & COMMIT"
  echo "========================================"
  echo "Approving and committing chaincode: ${CC_NAME} v${CC_VERSION}"
  echo ""
  
  # Auto approve
  autoApproveChaincode
  echo ""
  
  # Check commit readiness
  checkCommitReadiness
  echo ""
  
  # Auto commit
  autoCommitChaincode
  echo ""
  
  # Verify
  queryCommittedChaincode
  echo ""
  
  echo "========================================"
  echo "  APPROVE & COMMIT COMPLETED!"
  echo "========================================"
  echo "✅ Chaincode ${CC_NAME} v${CC_VERSION} berhasil di-approve dan commit!"
  echo "✅ Chaincode siap digunakan di channel ${CHANNEL_NAME}"
}

# Main entry point

case "$1" in
  restart)
    clearContainers
    removeOldArtifacts
    networkUp
    echo "Menunggu 15 detik agar network siap..."
    sleep 15
    createChannel
    
    # Auto deploy chaincode jika diminta
    if $AUTO_DEPLOY || $AUTO_APPROVE_COMMIT; then 
      echo "Auto-deploying chaincode..."
      deployChaincode
    fi
    
    echo "========================================"
    echo "      NETWORK SETUP COMPLETED!"
    echo "========================================"
    echo "✅ Network berhasil di-setup"
    echo "✅ Channel: $CHANNEL_NAME created and all peers joined"
    if $CREATE_MAIN_CHANNEL; then
      echo "✅ Main Channel: $MAIN_CHANNEL also created"
    fi
    if [ "$NETWORK_ONLY" = true ]; then
      echo "📝 Network ready for chaincode deployment"
      echo "📝 Gunakan: ./network.sh deploy-chaincode --auto-approve"
    fi
    ;;
    
  down)
    clearContainers
    echo "Network Stopped"
    ;;
    
  deploy-chaincode)
    echo "Deploying Chaincode..."
    NETWORK_ONLY=false
    if [[ "$2" == "--auto-approve" ]]; then
      AUTO_APPROVE_COMMIT=true
    fi
    deployChaincode
    ;;
    
  approve-commit)
    echo "Approving and Committing Chaincode..."
    NETWORK_ONLY=false
    approveAndCommitChaincode
    ;;
    
  upgrade)
    echo "Upgrading/Deploying Chaincode..."
    if [[ "$2" == "--with-chaincode" ]] || [[ "$2" == "--auto-approve" ]]; then
      NETWORK_ONLY=false
      if [[ "$2" == "--auto-approve" ]] || [[ "$3" == "--auto-approve" ]]; then
        AUTO_APPROVE_COMMIT=true
      fi
      deployChaincode
    else
      NETWORK_ONLY=false
      deployChaincode
    fi
    ;;
    
  channel)
    if [ "$2" = "create" ]; then
      if [ "$3" = "main" ]; then
        CREATE_MAIN_CHANNEL=true
        createMainChannel
      else
        createChannel
      fi
    else
      echo "Usage: ./network.sh channel create [main]"
    fi
    ;;
    
  test)
    echo "=== NETWORK STATUS CHECK ==="
    echo "Checking running containers..."
    docker ps -a --filter "name=${COMPOSE_PROJECT_NAME}"
    echo ""
    echo "Checking network status..."
    docker network ls | grep -E "(rec|blockchain)" || echo "No REC networks found"
    echo ""
    echo "Checking chaincode status..."
    queryCommittedChaincode 2>/dev/null || echo "No chaincode committed yet"
    ;;
    
  clean)
    echo "=== DEEP CLEAN MODE ==="
    clearContainers
    echo "Removing all related artifacts..."
    rm -rf organizations system-genesis-block channel-artifacts
    echo "Deep clean completed"
    ;;
    
  *)
    echo "========================================"
    echo "      REC BLOCKCHAIN NETWORK SCRIPT"
    echo "========================================"
    echo "Usage: ./network.sh [command] [options]"
    echo ""
    echo "NETWORK COMMANDS:"
    echo "  restart [--main] [--auto-approve]     : Setup network (optional: create main channel & auto deploy chaincode)"
    echo "  down                                  : Stop network"
    echo "  channel create [main]                 : Create channels"
    echo "  test                                  : Check network and chaincode status"
    echo "  clean                                 : Deep clean (remove all artifacts)"
    echo ""
    echo "CHAINCODE COMMANDS:"
    echo "  deploy-chaincode [--auto-approve]     : Deploy chaincode (package + install + optional auto approve/commit)"
    echo "  approve-commit                        : Manual approve and commit chaincode"
    echo "  upgrade [--auto-approve]              : Upgrade/Deploy chaincode"
    echo ""
    echo "EXAMPLES:"
    echo "  # Setup network saja:"
    echo "  ./network.sh restart"
    echo ""
    echo "  # Setup network + auto deploy chaincode:"
    echo "  ./network.sh restart --auto-approve"
    echo ""
    echo "  # Deploy chaincode dengan auto approve & commit:"
    echo "  ./network.sh deploy-chaincode --auto-approve"
    echo ""
    echo "  # Deploy chaincode manual (package + install saja):"
    echo "  ./network.sh deploy-chaincode"
    echo "  ./network.sh approve-commit"
    echo ""
    echo "  # Deep clean semua:"
    echo "  ./network.sh clean"
    echo ""
    echo "  # Stop network:"
    echo "  ./network.sh down"
    echo ""
    exit 1
    ;;
esac

echo "Script completed successfully"