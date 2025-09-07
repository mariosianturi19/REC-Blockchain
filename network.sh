#!/usr/bin/env bash

# Pastikan eksekusi berhenti jika ada error
set -e

# Nama file konfigurasi
export COMPOSE_FILE=./docker-compose.yaml
export COMPOSE_PROJECT_NAME=rec-project
export FABRIC_CFG_PATH=${PWD}

export CHANNEL_NAME="recchannel"
export CC_NAME="rec"
# Path chaincode dalam container
export CC_SRC_PATH_IN_CONTAINER="/opt/gopath/src/github.com/chaincode/rec/javascript/"

# --- Variabel untuk Chaincode ---
export CC_VERSION="1.0"
export CC_SEQUENCE="1"

# Flag AUTO DEPLOY (default false)
AUTO_DEPLOY=false
if [[ "$2" == "--auto" ]]; then
  AUTO_DEPLOY=true
fi

# =======================
# Fungsi Utama
# =======================

function clearContainers() {
  echo "========== Menghapus kontainer-kontainer lama... =========="
  docker compose -f $COMPOSE_FILE -p $COMPOSE_PROJECT_NAME down --volumes --remove-orphans

  LINGERING_CONTAINERS=$(docker ps -aq --filter "name=${COMPOSE_PROJECT_NAME}")
  if [ -n "$LINGERING_CONTAINERS" ]; then
    echo "Membersihkan sisa kontainer yang mungkin masih ada..."
    docker rm -f $LINGERING_CONTAINERS >/dev/null 2>&1
  fi

  docker rm -f $(docker ps -a | grep "dev-peer" | awk '{print $1}') >/dev/null 2>&1 || true
  echo "========== Kontainer lama berhasil dihapus =========="
}

function removeOldArtifacts() {
    echo "========== Menghapus artefak lama... =========="
    rm -rf ./organizations ./system-genesis-block/* ./channel-artifacts/*
    rm -rf ./bin ./config ./install-fabric.sh ./scripts/package.id ./log.txt
    mkdir -p ./system-genesis-block ./channel-artifacts ./scripts
    echo "========== Artefak lama berhasil dihapus =========="
}

function downloadFabricBinaries() {
    if [ ! -d "bin" ]; then
        echo "FABRIC BINARIES NOT FOUND"
        echo "====== Mengunduh Hyperledger Fabric Binaries v2.5.13 dan Fabric CA v1.5.15 ======"
        curl -sSLO https://raw.githubusercontent.com/hyperledger/fabric/main/scripts/install-fabric.sh && chmod +x install-fabric.sh
        ./install-fabric.sh binary --fabric-version 2.5.13 --ca-version 1.5.15
        echo "====== Unduhan Selesai ======"
    fi
}

function generateCrypto() {
    echo "========== Membangkitkan materi kripto... =========="
    ./bin/cryptogen generate --config=./crypto-config.yaml --output="./organizations"
    echo "========== Materi kripto berhasil dibuat =========="
}

function createGenesisBlock() {
    echo "========== Membuat Genesis Block... =========="
    ./bin/configtxgen -profile RECOrdererGenesis -channelID system-channel -outputBlock ./system-genesis-block/genesis.block -configPath .
    echo "========== Genesis Block berhasil dibuat =========="
}

function networkUp() {
    downloadFabricBinaries
    generateCrypto
    createGenesisBlock
    echo "========== Menjalankan Jaringan Docker... =========="
    docker compose -f $COMPOSE_FILE -p $COMPOSE_PROJECT_NAME up -d
    docker ps -a
    echo "========== Jaringan Docker berhasil berjalan =========="
}

function createChannel() {
    echo "========== Membuat Channel... =========="
    ./bin/configtxgen -profile RECChannel -outputCreateChannelTx ./channel-artifacts/${CHANNEL_NAME}.tx -channelID $CHANNEL_NAME -configPath .
    docker exec cli peer channel create -o orderer.rec.com:7050 -c $CHANNEL_NAME --ordererTLSHostnameOverride orderer.rec.com -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL_NAME}.tx --outputBlock /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL_NAME}.block --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
    echo "========== Channel berhasil dibuat =========="
    joinChannel
}

function joinChannel() {
    echo "========== Bergabung ke Channel... =========="
    for org in 1 2 3; do
        for peer in 0 1; do
            if [ $org -eq 1 ]; then MSP="ProdusenMSP"; if [ $peer -eq 0 ]; then PORT=7051; else PORT=8051; fi
            elif [ $org -eq 2 ]; then MSP="PBFMSP"; if [ $peer -eq 0 ]; then PORT=9051; else PORT=10051; fi
            elif [ $org -eq 3 ]; then MSP="ApotekMSP"; if [ $peer -eq 0 ]; then PORT=11051; else PORT=12051; fi
            fi
            echo "Bergabung ke channel untuk peer${peer}.org${org}.rec.com..."
            docker exec -e CORE_PEER_LOCALMSPID=$MSP -e CORE_PEER_ADDRESS="peer${peer}.org${org}.rec.com:${PORT}" -e CORE_PEER_MSPCONFIGPATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org${org}.rec.com/users/Admin@org${org}.rec.com/msp" -e CORE_PEER_TLS_ROOTCERT_FILE="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org${org}.rec.com/peers/peer${peer}.org${org}.rec.com/tls/ca.crt" cli peer channel join -b /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL_NAME}.block
        done
    done
    echo "========== Semua 6 peer berhasil join channel =========="
    updateAnchorPeers
}

function updateAnchorPeers() {
    echo "========== Update Anchor Peers... =========="
    for org in 1 2 3; do
      if [ $org -eq 1 ]; then MSP="ProdusenMSP"; PORT=7051; elif [ $org -eq 2 ]; then MSP="PBFMSP"; PORT=9051; elif [ $org -eq 3 ]; then MSP="ApotekMSP"; PORT=11051; fi
      echo "Update Anchor Peer untuk ${MSP}..."
      ./bin/configtxgen -profile RECChannel -outputAnchorPeersUpdate ./channel-artifacts/${MSP}anchors.tx -channelID $CHANNEL_NAME -asOrg $MSP -configPath .
      docker exec -e CORE_PEER_LOCALMSPID=$MSP -e CORE_PEER_ADDRESS="peer0.org${org}.rec.com:${PORT}" -e CORE_PEER_MSPCONFIGPATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org${org}.rec.com/users/Admin@org${org}.rec.com/msp" cli peer channel update -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com -c $CHANNEL_NAME -f /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${MSP}anchors.tx --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
    done
    echo "========== Semua Anchor Peer berhasil diupdate =========="
}

function packageAndInstall() {
    docker exec cli peer lifecycle chaincode package ${CC_NAME}_${CC_VERSION}.tar.gz --path ${CC_SRC_PATH_IN_CONTAINER} --lang node --label ${CC_NAME}_${CC_VERSION}
    echo "Chaincode berhasil di-package."
    
    for org in 1 2 3; do
        if [ $org -eq 1 ]; then MSP="ProdusenMSP"; elif [ $org -eq 2 ]; then MSP="PBFMSP"; elif [ $org -eq 3 ]; then MSP="ApotekMSP"; fi
        for peer in 0 1; do
            if [ $org -eq 1 ]; then if [ $peer -eq 0 ]; then PORT=7051; else PORT=8051; fi
            elif [ $org -eq 2 ]; then if [ $peer -eq 0 ]; then PORT=9051; else PORT=10051; fi
            elif [ $org -eq 3 ]; then if [ $peer -eq 0 ]; then PORT=11051; else PORT=12051; fi
            fi
            echo "--- Menginstall di peer${peer}.org${org}.rec.com (sebagai admin Org${org}) ---"
            docker exec \
              -e CORE_PEER_LOCALMSPID=$MSP \
              -e CORE_PEER_TLS_ROOTCERT_FILE="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org${org}.rec.com/peers/peer${peer}.org${org}.rec.com/tls/ca.crt" \
              -e CORE_PEER_MSPCONFIGPATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org${org}.rec.com/users/Admin@org${org}.rec.com/msp" \
              -e CORE_PEER_ADDRESS="peer${peer}.org${org}.rec.com:${PORT}" \
              cli peer lifecycle chaincode install ${CC_NAME}_${CC_VERSION}.tar.gz
        done
    done
}

function deployCC() {
    echo "========== Deploy Chaincode Awal =========="
    packageAndInstall
    echo "========== Chaincode ter-package & ter-install =========="
    if [ "$AUTO_DEPLOY" = true ]; then
      approveAndCommit
    else
      echo "⚠️  Ingat: Lanjutkan manual untuk approve & commit chaincode."
    fi
}

function upgradeCC() {
    echo "========== Upgrade Chaincode =========="
    packageAndInstall
    echo "========== Chaincode berhasil di-install =========="
    if [ "$AUTO_DEPLOY" = true ]; then
      approveAndCommit
    else
      echo "⚠️  Ingat: Lanjutkan manual untuk approve & commit chaincode upgrade."
    fi
}

# Fungsi auto approve & commit chaincode
function approveAndCommit() {
    echo "========== Auto Approve & Commit Chaincode =========="
    docker exec -e CORE_PEER_ADDRESS="peer0.org1.rec.com:7051" -e CORE_PEER_MSPCONFIGPATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org1.rec.com/users/Admin@org1.rec.com/msp" cli peer lifecycle chaincode queryinstalled >&log.txt
    PACKAGE_ID=$(sed -n "/Package ID: ${CC_NAME}_${CC_VERSION}/,/Label:/p" log.txt | sed -n 's/Package ID: //; s/, Label:.*//p')
    echo "Package ID adalah: ${PACKAGE_ID}"

    for org in 1 2 3; do
        if [ $org -eq 1 ]; then MSP="ProdusenMSP"; PORT=7051
        elif [ $org -eq 2 ]; then MSP="PBFMSP"; PORT=9051
        elif [ $org -eq 3 ]; then MSP="ApotekMSP"; PORT=11051
        fi

        echo "--- Approve chaincode untuk $MSP ---"
        docker exec -e CORE_PEER_LOCALMSPID=$MSP -e CORE_PEER_ADDRESS="peer0.org${org}.rec.com:${PORT}" -e CORE_PEER_MSPCONFIGPATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org${org}.rec.com/users/Admin@org${org}.rec.com/msp" cli peer lifecycle chaincode approveformyorg -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --channelID $CHANNEL_NAME --name ${CC_NAME} --version ${CC_VERSION} --package-id ${PACKAGE_ID} --sequence ${CC_SEQUENCE} --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
    done

    echo "--- Commit chaincode ke channel ---"
    docker exec -e CORE_PEER_LOCALMSPID="ProdusenMSP" -e CORE_PEER_ADDRESS="peer0.org1.rec.com:7051" -e CORE_PEER_MSPCONFIGPATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org1.rec.com/users/Admin@org1.rec.com/msp" cli peer lifecycle chaincode commit -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --channelID $CHANNEL_NAME --name ${CC_NAME} --version ${CC_VERSION} --sequence ${CC_SEQUENCE} --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem --peerAddresses peer0.org1.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org1.rec.com/peers/peer0.org1.rec.com/tls/ca.crt --peerAddresses peer0.org2.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org2.rec.com/peers/peer0.org2.rec.com/tls/ca.crt --peerAddresses peer0.org3.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/org3.rec.com/peers/peer0.org3.rec.com/tls/ca.crt

    echo "========== Chaincode berhasil diapprove & commit =========="
}

# =======================
# Main CLI
# =======================
if [ "$1" == "restart" ]; then
  clearContainers
  removeOldArtifacts
  networkUp
  echo "Menunggu 10 detik agar orderer dan peer siap..."
  sleep 10
  createChannel
  deployCC
elif [ "$1" == "down" ]; then
  clearContainers
elif [ "$1" == "upgrade" ]; then
  upgradeCC
else
  echo "Penggunaan: ./network.sh [restart|down|upgrade] [--auto]"
  exit 1
fi
