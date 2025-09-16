#!/usr/bin/env bash
set -e

export COMPOSE_FILE=./docker-compose.yaml
export COMPOSE_PROJECT_NAME=rec-project
export FABRIC_CFG_PATH=${PWD}

export CHANNEL_NAME="recchannel"
export CC_NAME="rec"
export CC_VERSION="1.0"
export CC_SEQUENCE="1"
export CC_SRC_PATH_IN_CONTAINER="/opt/gopath/src/github.com/chaincode/rec/javascript/"

AUTO_DEPLOY=false
if [[ "$2" == "--auto" ]]; then AUTO_DEPLOY=true; fi

clearContainers() {
  echo "========== Menghapus kontainer... =========="
  docker compose -f $COMPOSE_FILE -p $COMPOSE_PROJECT_NAME down --volumes --remove-orphans || true
  docker rm -f $(docker ps -aq --filter "name=${COMPOSE_PROJECT_NAME}") >/dev/null 2>&1 || true
  docker rm -f $(docker ps -a | grep "dev-peer" | awk '{print $1}') >/dev/null 2>&1 || true
}

removeOldArtifacts() {
  echo "========== Menghapus artefak... =========="
  rm -rf ./organizations ./system-genesis-block/* ./channel-artifacts/*
  rm -rf ./bin ./config ./install-fabric.sh ./scripts/package.id ./log.txt
  mkdir -p ./system-genesis-block ./channel-artifacts ./scripts
}

downloadFabricBinaries() {
  if [ ! -d "bin" ]; then
    echo "====== Mengunduh Fabric binaries v2.5.13 & CA v1.5.15 ======"
    curl -sSLO https://raw.githubusercontent.com/hyperledger/fabric/main/scripts/install-fabric.sh && chmod +x install-fabric.sh
    ./install-fabric.sh binary --fabric-version 2.5.13 --ca-version 1.5.15
  fi
}

generateCrypto() {
  echo "========== Generate crypto =========="
  ./bin/cryptogen generate --config=./crypto-config.yaml --output="./organizations"
}

createGenesisBlock() {
  echo "========== Genesis block =========="
  ./bin/configtxgen -profile RECOrdererGenesis -channelID system-channel -outputBlock ./system-genesis-block/genesis.block -configPath .
}

networkUp() {
  downloadFabricBinaries
  generateCrypto
  createGenesisBlock
  echo "========== Docker up =========="
  docker compose -f $COMPOSE_FILE -p $COMPOSE_PROJECT_NAME up -d
  docker ps -a
}

createChannel() {
  echo "========== Create channel =========="
  ./bin/configtxgen -profile RECChannel -outputCreateChannelTx ./channel-artifacts/${CHANNEL_NAME}.tx -channelID $CHANNEL_NAME -configPath .
  docker exec cli peer channel create \
    -o orderer.rec.com:7050 \
    -c $CHANNEL_NAME \
    --ordererTLSHostnameOverride orderer.rec.com \
    -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL_NAME}.tx \
    --outputBlock /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL_NAME}.block \
    --tls \
    --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
  joinChannel
}

joinOne() {
  MSP=$1; HOST=$2; PORT=$3; DOM=$4; PEER=$5
  echo "Join: $PEER ($MSP)"
  docker exec \
    -e CORE_PEER_LOCALMSPID=$MSP \
    -e CORE_PEER_ADDRESS=${HOST}:${PORT} \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/users/Admin@${DOM}/msp \
    -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/peers/${PEER}/tls/ca.crt \
    cli peer channel join -b /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL_NAME}.block
}

joinChannel() {
  echo "========== Join channel =========="
  joinOne GeneratorMSP peer0.generator.rec.com 7051 generator.rec.com peer0.generator.rec.com
  joinOne GeneratorMSP peer1.generator.rec.com 8051 generator.rec.com peer1.generator.rec.com
  joinOne IssuerMSP    peer0.issuer.rec.com    9051 issuer.rec.com    peer0.issuer.rec.com
  joinOne IssuerMSP    peer1.issuer.rec.com    10051 issuer.rec.com   peer1.issuer.rec.com
  joinOne BuyerMSP     peer0.buyer.rec.com     11051 buyer.rec.com    peer0.buyer.rec.com
  joinOne BuyerMSP     peer1.buyer.rec.com     12051 buyer.rec.com    peer1.buyer.rec.com
  updateAnchorPeers
}

updateAnchorPeers() {
  echo "========== Update anchors =========="
  ./bin/configtxgen -profile RECChannel -outputAnchorPeersUpdate ./channel-artifacts/GeneratorMSPanchors.tx -channelID $CHANNEL_NAME -asOrg GeneratorMSP -configPath .
  ./bin/configtxgen -profile RECChannel -outputAnchorPeersUpdate ./channel-artifacts/IssuerMSPanchors.tx    -channelID $CHANNEL_NAME -asOrg IssuerMSP    -configPath .
  ./bin/configtxgen -profile RECChannel -outputAnchorPeersUpdate ./channel-artifacts/BuyerMSPanchors.tx     -channelID $CHANNEL_NAME -asOrg BuyerMSP     -configPath .

  docker exec -e CORE_PEER_LOCALMSPID=GeneratorMSP -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
    cli peer channel update -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com -c $CHANNEL_NAME -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/GeneratorMSPanchors.tx --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem

  docker exec -e CORE_PEER_LOCALMSPID=IssuerMSP -e CORE_PEER_ADDRESS=peer0.issuer.rec.com:9051 -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/users/Admin@issuer.rec.com/msp \
    cli peer channel update -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com -c $CHANNEL_NAME -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/IssuerMSPanchors.tx --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem

  docker exec -e CORE_PEER_LOCALMSPID=BuyerMSP -e CORE_PEER_ADDRESS=peer0.buyer.rec.com:11051 -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/users/Admin@buyer.rec.com/msp \
    cli peer channel update -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com -c $CHANNEL_NAME -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/BuyerMSPanchors.tx --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
}

checkChaincodePath() {
  docker exec cli bash -lc "test -d '${CC_SRC_PATH_IN_CONTAINER}'" || { echo "ERROR: folder tidak ditemukan: ${CC_SRC_PATH_IN_CONTAINER}"; exit 1; }
}

packageAndInstall() {
  checkChaincodePath
  docker exec cli peer lifecycle chaincode package ${CC_NAME}_${CC_VERSION}.tar.gz --path ${CC_SRC_PATH_IN_CONTAINER} --lang node --label ${CC_NAME}_${CC_VERSION}
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
    docker exec \
      -e CORE_PEER_LOCALMSPID=$MSP \
      -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/peers/${PEER}/tls/ca.crt \
      -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/users/Admin@${DOM}/msp \
      -e CORE_PEER_ADDRESS=${HOST}:${PORT} \
      cli peer lifecycle chaincode install ${CC_NAME}_${CC_VERSION}.tar.gz
  done
}

approveAndCommit() {
  docker exec \
    -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
    cli peer lifecycle chaincode queryinstalled >&log.txt
  PACKAGE_ID=$(sed -n "/Package ID: ${CC_NAME}_${CC_VERSION}/,/Label:/p" log.txt | sed -n 's/Package ID: //; s/, Label:.*//p')
  for entry in \
    "GeneratorMSP peer0.generator.rec.com 7051 generator.rec.com" \
    "IssuerMSP    peer0.issuer.rec.com    9051 issuer.rec.com" \
    "BuyerMSP     peer0.buyer.rec.com     11051 buyer.rec.com"
  do
    set -- $entry
    MSP=$1; HOST=$2; PORT=$3; DOM=$4
    docker exec \
      -e CORE_PEER_LOCALMSPID=$MSP \
      -e CORE_PEER_ADDRESS=${HOST}:${PORT} \
      -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/users/Admin@${DOM}/msp \
      cli peer lifecycle chaincode approveformyorg \
        -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com \
        --channelID $CHANNEL_NAME --name ${CC_NAME} --version ${CC_VERSION} \
        --package-id ${PACKAGE_ID} --sequence ${CC_SEQUENCE} \
        --tls \
        --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
  done
  docker exec \
    -e CORE_PEER_LOCALMSPID=GeneratorMSP \
    -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
    -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
    cli peer lifecycle chaincode commit \
      -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com \
      --channelID $CHANNEL_NAME --name ${CC_NAME} --version ${CC_VERSION} --sequence ${CC_SEQUENCE} \
      --tls \
      --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem \
      --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
      --peerAddresses peer0.issuer.rec.com:9051    --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt \
      --peerAddresses peer0.buyer.rec.com:11051    --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt
}

deployCC() {
  packageAndInstall
  if $AUTO_DEPLOY; then approveAndCommit; fi
}

case "$1" in
  restart)
    clearContainers
    removeOldArtifacts
    networkUp
    echo "Menunggu 10 detik agar siap..."
    sleep 10
    createChannel
    if $AUTO_DEPLOY; then deployCC; fi
    ;;
  down)
    clearContainers
    ;;
  upgrade)
    deployCC
    ;;
  *)
    echo "Penggunaan: ./network.sh [restart|down|upgrade] [--auto]"
    exit 1
    ;;
esac
