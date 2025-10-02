#!/usr/bin/env bash

set -e

# Konfigurasi
CHANNEL_NAME="recchannel"
CC_NAME="rec"
CC_VERSION="1.0"
CC_SEQUENCE="1"
CC_SRC_PATH_IN_CONTAINER="/opt/gopath/src/github.com/chaincode/rec/javascript/"

# Function to get next version and sequence
getNextVersion() {
    local current_version="$1"
    if [[ $current_version =~ ^([0-9]+)\.([0-9]+)$ ]]; then
        local major="${BASH_REMATCH[1]}"
        local minor="${BASH_REMATCH[2]}"
        minor=$((minor + 1))
        echo "${major}.${minor}"
    else
        echo "1.1"  # Default if parsing fails
    fi
}

# Function to upgrade chaincode
upgradeChaincode() {
    local new_version="$1"
    local new_sequence="$2"
    
    if [ -z "$new_version" ] || [ -z "$new_sequence" ]; then
        echo "Usage: upgradeChaincode <new_version> <new_sequence>"
        echo "Example: upgradeChaincode 1.1 2"
        return 1
    fi
    
    echo "==== CHAINCODE UPGRADE ===="
    echo "Upgrading chaincode: ${CC_NAME} from v${CC_VERSION} to v${new_version}"
    echo "Sequence: ${CC_SEQUENCE} → ${new_sequence}"
    echo ""
    
    # Update global variables for upgrade
    local old_version=$CC_VERSION
    local old_sequence=$CC_SEQUENCE
    CC_VERSION=$new_version
    CC_SEQUENCE=$new_sequence
    
    echo "Step 1: Package new version..."
    packageChaincode
    echo ""
    
    echo "Step 2: Install new version ke semua peers..."
    installChaincode
    echo ""
    
    echo "Step 3: Approve new version untuk semua organisasi..."
    approveChaincode
    echo ""
    
    echo "Step 4: Check commit readiness..."
    checkCommitReadiness
    echo ""
    
    echo "Step 5: Commit new version ke channel..."
    commitChaincode
    echo ""
    
    echo "Step 6: Verify upgrade..."
    queryCommitted
    echo ""
    
    echo "==== CHAINCODE UPGRADE COMPLETE ===="
    echo "✅ Chaincode ${CC_NAME} upgraded from v${old_version} to v${new_version}!"
    echo "✅ Sequence updated from ${old_sequence} to ${new_sequence}"
}

# Function to invoke chaincode
invokeChaincode() {
    local function_name="$1"
    local args="$2"
    
    if [ -z "$function_name" ]; then
        echo "Usage: invokeChaincode <function_name> [args]"
        echo "Example: invokeChaincode initLedger"
        echo "Example: invokeChaincode createREC '{\"id\":\"REC001\",\"amount\":100}'"
        return 1
    fi
    
    echo "Invoking chaincode function: $function_name"
    
    if [ -n "$args" ]; then
        docker exec \
            -e CORE_PEER_LOCALMSPID=GeneratorMSP \
            -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
            cli peer chaincode invoke \
                -o orderer.rec.com:7050 \
                --ordererTLSHostnameOverride orderer.rec.com \
                --tls \
                --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem \
                -C $CHANNEL_NAME \
                -n $CC_NAME \
                --peerAddresses peer0.generator.rec.com:7051 \
                --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
                --peerAddresses peer0.issuer.rec.com:9051 \
                --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt \
                -c "{\"function\":\"$function_name\",\"Args\":[$args]}"
    else
        docker exec \
            -e CORE_PEER_LOCALMSPID=GeneratorMSP \
            -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
            cli peer chaincode invoke \
                -o orderer.rec.com:7050 \
                --ordererTLSHostnameOverride orderer.rec.com \
                --tls \
                --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem \
                -C $CHANNEL_NAME \
                -n $CC_NAME \
                --peerAddresses peer0.generator.rec.com:7051 \
                --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
                --peerAddresses peer0.issuer.rec.com:9051 \
                --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt \
                -c "{\"function\":\"$function_name\",\"Args\":[]}"
    fi
}

# Function to query chaincode
queryChaincode() {
    local function_name="$1"
    local args="$2"
    
    if [ -z "$function_name" ]; then
        echo "Usage: queryChaincode <function_name> [args]"
        echo "Example: queryChaincode getAllRECs"
        echo "Example: queryChaincode getREC '\"REC001\"'"
        return 1
    fi
    
    echo "Querying chaincode function: $function_name"
    
    if [ -n "$args" ]; then
        docker exec \
            -e CORE_PEER_LOCALMSPID=GeneratorMSP \
            -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
            cli peer chaincode query \
                -C $CHANNEL_NAME \
                -n $CC_NAME \
                -c "{\"function\":\"$function_name\",\"Args\":[$args]}"
    else
        docker exec \
            -e CORE_PEER_LOCALMSPID=GeneratorMSP \
            -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
            cli peer chaincode query \
                -C $CHANNEL_NAME \
                -n $CC_NAME \
                -c "{\"function\":\"$function_name\",\"Args\":[]}"
    fi
}

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
    upgrade)
        upgradeChaincode "$2" "$3"
        ;;
    invoke)
        invokeChaincode "$2" "$3"
        ;;
    query)
        queryChaincode "$2" "$3"
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
        echo "Usage: ./chaincode-lifecycle.sh [command] [options]"
        echo ""
        echo "🚀 BLOCKCHAIN ENGINEER COMMANDS:"
        echo "  deploy                         : Complete chaincode lifecycle (package → install → approve → commit)"
        echo "  upgrade <version> <sequence>   : Upgrade chaincode to new version"
        echo "  package                        : Package chaincode"
        echo "  install                        : Install chaincode ke semua peers"
        echo "  approve                        : Approve chaincode untuk semua organisasi"
        echo "  commit                         : Commit chaincode ke channel"
        echo ""
        echo "🔧 CHAINCODE OPERATIONS:"
        echo "  invoke <function> [args]       : Invoke chaincode function (modify ledger)"
        echo "  query <function> [args]        : Query chaincode function (read-only)"
        echo ""
        echo "📊 MONITORING COMMANDS:"
        echo "  query-installed                : Query installed chaincode di semua peers"
        echo "  query-committed                : Query committed chaincode di channel"
        echo "  check-readiness                : Check commit readiness"
        echo ""
        echo "🎯 WORKFLOW UNTUK BLOCKCHAIN ENGINEER:"
        echo "1. Deploy: ./scripts/chaincode-lifecycle.sh deploy"
        echo "2. Test: ./scripts/chaincode-lifecycle.sh invoke initLedger"
        echo "3. Query: ./scripts/chaincode-lifecycle.sh query getAllRECs"
        echo "4. Upgrade: ./scripts/chaincode-lifecycle.sh upgrade 1.1 2"
        echo ""
        echo "📝 Examples:"
        echo "  ./scripts/chaincode-lifecycle.sh deploy                    # Initial deployment"
        echo "  ./scripts/chaincode-lifecycle.sh upgrade 1.1 2             # Upgrade to version 1.1"
        echo "  ./scripts/chaincode-lifecycle.sh invoke initLedger         # Initialize ledger"
        echo "  ./scripts/chaincode-lifecycle.sh query getAllRECs          # Get all RECs"
        echo "  ./scripts/chaincode-lifecycle.sh invoke createREC '\"REC001\",\"100\"'  # Create REC"
        echo "  ./scripts/chaincode-lifecycle.sh query getREC '\"REC001\"'      # Get specific REC"
        echo "  ./scripts/chaincode-lifecycle.sh query-committed           # Check status"
        exit 1
        ;;
esac

echo "Script completed successfully"