#!/usr/bin/env bash

set -e

# Konfigurasi
CHANNEL_NAME="recchannel"
CC_NAME="rec"
CC_VERSION="1.0"
CC_SEQUENCE="1"
CC_SRC_PATH_IN_CONTAINER="/opt/gopath/src/github.com/chaincode/rec/javascript/"

# Fungsi untuk query installed chaincode
queryInstalled() {
    echo "Query installed chaincode di semua peers..."
    
    for entry in \
        "GeneratorMSP peer0.generator.rec.com 7051 generator.rec.com" \
        "IssuerMSP    peer0.issuer.rec.com    9051 issuer.rec.com" \
        "BuyerMSP     peer0.buyer.rec.com     11051 buyer.rec.com"
    do
        set -- $entry
        MSP=$1; HOST=$2; PORT=$3; DOM=$4
        
        echo "Checking installed chaincode on ${MSP}..."
        docker exec \
            -e CORE_PEER_LOCALMSPID=$MSP \
            -e CORE_PEER_ADDRESS=${HOST}:${PORT} \
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/users/Admin@${DOM}/msp \
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/peers/${HOST}/tls/ca.crt \
            cli peer lifecycle chaincode queryinstalled
    done
}

# Fungsi untuk package chaincode
packageChaincode() {
    echo "Packaging chaincode..."
    
    # Check if chaincode path exists
    docker exec cli bash -lc "test -d '${CC_SRC_PATH_IN_CONTAINER}'" || { 
        echo "ERROR: Chaincode folder tidak ditemukan: ${CC_SRC_PATH_IN_CONTAINER}"
        echo "Pastikan blockchain developer sudah menyediakan chaincode di folder ./chaincode/rec/javascript/"
        return 1
    }
    
    # Package chaincode
    docker exec cli peer lifecycle chaincode package ${CC_NAME}_${CC_VERSION}.tar.gz \
        --path ${CC_SRC_PATH_IN_CONTAINER} \
        --lang node \
        --label ${CC_NAME}_${CC_VERSION}
    
    echo "Chaincode package created: ${CC_NAME}_${CC_VERSION}.tar.gz"
}

# Fungsi untuk install chaincode di semua peers
installChaincode() {
    echo "Installing chaincode di semua peers..."
    
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
    done
    
    echo "Chaincode installed di semua peers"
}

# Fungsi untuk approve chaincode untuk semua organisasi
approveChaincode() {
    echo "Approve chaincode untuk semua organisasi..."
    
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

    # Approve chaincode untuk setiap organisasi
    for entry in \
        "GeneratorMSP peer0.generator.rec.com 7051 generator.rec.com" \
        "IssuerMSP    peer0.issuer.rec.com    9051 issuer.rec.com" \
        "BuyerMSP     peer0.buyer.rec.com     11051 buyer.rec.com"
    do
        set -- $entry
        MSP=$1; HOST=$2; PORT=$3; DOM=$4
        
        echo "Approving chaincode untuk ${MSP}..."
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
    
    echo "Semua organisasi telah approve chaincode"
}

# Fungsi untuk check commit readiness
checkCommitReadiness() {
    echo "Checking commit readiness..."
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
}

# Fungsi untuk commit chaincode
commitChaincode() {
    echo "Committing chaincode ke channel..."
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

    if [ $? -ne 0 ]; then
        echo "ERROR: Failed to commit chaincode"
        exit 1
    fi

    echo "SUCCESS: Chaincode committed successfully ke channel ${CHANNEL_NAME}"
}

# Fungsi untuk query committed chaincode
queryCommitted() {
    echo "Verifying committed chaincode..."
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
}

# Fungsi untuk deploy complete lifecycle
deployComplete() {
    echo "==== CHAINCODE LIFECYCLE DEPLOYMENT - REC BLOCKCHAIN ===="
    echo "Deploying chaincode: ${CC_NAME} v${CC_VERSION} to channel: ${CHANNEL_NAME}"
    echo ""
    
    echo "Step 1: Package chaincode..."
    packageChaincode
    echo ""
    
    echo "Step 2: Install chaincode ke semua peers..."
    installChaincode
    echo ""
    
    echo "Step 3: Approve chaincode untuk semua organisasi..."
    approveChaincode
    echo ""
    
    echo "Step 4: Check commit readiness..."
    checkCommitReadiness
    echo ""
    
    echo "Step 5: Commit chaincode ke channel..."
    commitChaincode
    echo ""
    
    echo "Step 6: Verify committed chaincode..."
    queryCommitted
    echo ""
    
    echo "==== CHAINCODE DEPLOYMENT COMPLETE ===="
    echo "Chaincode ${CC_NAME} v${CC_VERSION} telah berhasil di-deploy!"
    echo "Semua organisasi (Generator, Issuer, Buyer) telah approve dan commit"
}

# Main script logic
case "$1" in
    package)
        packageChaincode
        ;;
    install)
        installChaincode
        ;;
    approve)
        approveChaincode
        ;;
    commit)
        commitChaincode
        ;;
    query-installed)
        queryInstalled
        ;;
    query-committed)
        queryCommitted
        ;;
    check-readiness)
        checkCommitReadiness
        ;;
    deploy)
        deployComplete
        ;;
    *)
        echo "==== REC Chaincode Lifecycle Management ===="
        echo "Usage: ./chaincode-lifecycle.sh [command]"
        echo ""
        echo "BLOCKCHAIN ENGINEER COMMANDS:"
        echo "  deploy                    : Complete chaincode lifecycle (package → install → approve → commit)"
        echo "  package                   : Package chaincode"
        echo "  install                   : Install chaincode ke semua peers"
        echo "  approve                   : Approve chaincode untuk semua organisasi"
        echo "  commit                    : Commit chaincode ke channel"
        echo ""
        echo "MONITORING COMMANDS:"
        echo "  query-installed           : Query installed chaincode di semua peers"
        echo "  query-committed           : Query committed chaincode di channel"
        echo "  check-readiness           : Check commit readiness"
        echo ""
        echo "WORKFLOW UNTUK BLOCKCHAIN ENGINEER:"
        echo "1. Pastikan network sudah running: ./network.sh restart"
        echo "2. Pastikan blockchain developer sudah provide chaincode di ./chaincode/rec/javascript/"
        echo "3. Deploy chaincode: ./scripts/chaincode-lifecycle.sh deploy"
        echo ""
        echo "Examples:"
        echo "  ./scripts/chaincode-lifecycle.sh deploy        # Complete deployment"
        echo "  ./scripts/chaincode-lifecycle.sh approve       # Approve only"
        echo "  ./scripts/chaincode-lifecycle.sh commit        # Commit only"
        echo "  ./scripts/chaincode-lifecycle.sh query-committed  # Check status"
        exit 1
        ;;
esac

echo "Script completed successfully"