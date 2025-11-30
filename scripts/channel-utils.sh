#!/usr/bin/env bash

# channel-utils.sh
# Script utilitas untuk mengelola channel dalam jaringan REC Blockchain

set -e

# Konfigurasi
CHANNEL_NAME="recchannel"
MAIN_CHANNEL="mainrec"
FABRIC_CFG_PATH=${PWD}

# Fungsi untuk mengecek status channel
checkChannelStatus() {
    local CHANNEL="$1"
    echo "========== Checking Channel Status: $CHANNEL =========="
    
    # List channels untuk setiap peer
    for org in "Generator" "Issuer" "Buyer"; do
        case $org in
            "Generator")
                MSP="GeneratorMSP"
                PEER="peer0.generator.rec.com:7051"
                TLS_CERT="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt"
                MSP_PATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp"
                ;;
            "Issuer")
                MSP="IssuerMSP"
                PEER="peer0.issuer.rec.com:9051"
                TLS_CERT="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt"
                MSP_PATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/users/Admin@issuer.rec.com/msp"
                ;;
            "Buyer")
                MSP="BuyerMSP"
                PEER="peer0.buyer.rec.com:11051"
                TLS_CERT="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt"
                MSP_PATH="/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/users/Admin@buyer.rec.com/msp"
                ;;
        esac

        echo "Checking $org organization..."
        docker exec \
            -e CORE_PEER_LOCALMSPID=$MSP \
            -e CORE_PEER_ADDRESS=$PEER \
            -e CORE_PEER_MSPCONFIGPATH=$MSP_PATH \
            -e CORE_PEER_TLS_ROOTCERT_FILE=$TLS_CERT \
            cli peer channel list || echo "Failed to list channels for $org"
    done
}

# Fungsi untuk mendapatkan info channel
getChannelInfo() {
    local CHANNEL="$1"
    echo "========== Channel Information: $CHANNEL =========="
    
    docker exec \
        -e CORE_PEER_LOCALMSPID=GeneratorMSP \
        -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
        -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
        -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
        cli peer channel getinfo -c $CHANNEL
}

# Fungsi untuk fetch channel config
fetchChannelConfig() {
    local CHANNEL="$1"
    echo "========== Fetching Channel Config: $CHANNEL =========="
    
    docker exec \
        -e CORE_PEER_LOCALMSPID=GeneratorMSP \
        -e CORE_PEER_ADDRESS=peer0.generator.rec.com:7051 \
        -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/users/Admin@generator.rec.com/msp \
        -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt \
        cli peer channel fetch config ./channel-artifacts/${CHANNEL}_config.block \
        -o orderer.rec.com:7050 \
        -c $CHANNEL \
        --tls \
        --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem
}

# Fungsi untuk list semua channels
listAllChannels() {
    echo "========== All Channels Status =========="
    checkChannelStatus $CHANNEL_NAME
    echo ""
    checkChannelStatus $MAIN_CHANNEL
}

# Fungsi untuk join peer tertentu ke channel
joinPeerToChannel() {
    local PEER_NAME="$1"
    local CHANNEL="$2"
    
    if [ -z "$PEER_NAME" ] || [ -z "$CHANNEL" ]; then
        echo "Usage: joinPeerToChannel <peer_name> <channel_name>"
        echo "Example: joinPeerToChannel peer1.generator.rec.com recchannel"
        return 1
    fi
    
    echo "========== Joining $PEER_NAME to $CHANNEL =========="
    
    # Determine organization and settings based on peer name
    if [[ $PEER_NAME == *"generator"* ]]; then
        MSP="GeneratorMSP"
        DOM="generator.rec.com"
        if [[ $PEER_NAME == "peer0"* ]]; then
            PORT="7051"
        else
            PORT="8051"
        fi
    elif [[ $PEER_NAME == *"issuer"* ]]; then
        MSP="IssuerMSP"
        DOM="issuer.rec.com"
        if [[ $PEER_NAME == "peer0"* ]]; then
            PORT="9051"
        else
            PORT="10051"
        fi
    elif [[ $PEER_NAME == *"buyer"* ]]; then
        MSP="BuyerMSP"
        DOM="buyer.rec.com"
        if [[ $PEER_NAME == "peer0"* ]]; then
            PORT="11051"
        else
            PORT="12051"
        fi
    else
        echo "Invalid peer name: $PEER_NAME"
        return 1
    fi
    
    docker exec \
        -e CORE_PEER_LOCALMSPID=$MSP \
        -e CORE_PEER_ADDRESS=${PEER_NAME}:${PORT} \
        -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/users/Admin@${DOM}/msp \
        -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/${DOM}/peers/${PEER_NAME}/tls/ca.crt \
        cli peer channel join -b /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/channel-artifacts/${CHANNEL}.block
}

# Main script logic
case "$1" in
    status)
        if [ -n "$2" ]; then
            checkChannelStatus "$2"
        else
            listAllChannels
        fi
        ;;
    info)
        if [ -n "$2" ]; then
            getChannelInfo "$2"
        else
            getChannelInfo $CHANNEL_NAME
        fi
        ;;
    config)
        if [ -n "$2" ]; then
            fetchChannelConfig "$2"
        else
            fetchChannelConfig $CHANNEL_NAME
        fi
        ;;
    join)
        joinPeerToChannel "$2" "$3"
        ;;
    list)
        listAllChannels
        ;;
    *)
        echo "========== REC Channel Management Utilities =========="
        echo "Usage: ./channel-utils.sh [command] [options]"
        echo ""
        echo "Commands:"
        echo "  status [channel]           : Check channel status for all orgs"
        echo "  info [channel]             : Get channel information"
        echo "  config [channel]           : Fetch channel configuration"
        echo "  join <peer> <channel>      : Join specific peer to channel"
        echo "  list                       : List all channels status"
        echo ""
        echo "Examples:"
        echo "  ./channel-utils.sh status recchannel"
        echo "  ./channel-utils.sh info"
        echo "  ./channel-utils.sh join peer1.generator.rec.com recchannel"
        echo "  ./channel-utils.sh list"
        exit 1
        ;;
esac