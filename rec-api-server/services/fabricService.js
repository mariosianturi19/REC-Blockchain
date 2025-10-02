const { Gateway, Wallets } = require('fabric-network');
const path = require('path');
const fs = require('fs');
require('dotenv').config();

class FabricService {
    constructor() {
        this.channelName = process.env.CHANNEL_NAME || 'recchannel';
        this.chaincodeName = process.env.CHAINCODE_NAME || 'rec';
        this.walletPath = path.join(process.cwd(), process.env.WALLET_PATH || './wallets');
        this.connectionProfilePath = path.resolve(__dirname, '../config/connection.json');
        this.orgPath = path.resolve(__dirname, '../../organizations/peerOrganizations/generator.rec.com');
    }

    async connectToNetwork(username = 'Admin') {
        try {
            // Load connection profile
            const connectionProfile = JSON.parse(fs.readFileSync(this.connectionProfilePath, 'utf8'));
            
            // Create wallet instance
            const wallet = await Wallets.newFileSystemWallet(this.walletPath);

            // Check if user exists in wallet
            let userIdentity = await wallet.get(username);
            if (!userIdentity) {
                console.log(`Creating identity for user "${username}" from existing credentials`);
                await this.createUserIdentityFromCredentials(wallet, username);
                userIdentity = await wallet.get(username);
            }

            // Create gateway instance with discovery disabled
            const gateway = new Gateway();
            await gateway.connect(connectionProfile, {
                wallet,
                identity: username,
                discovery: { enabled: false }  // Completely disable discovery
            });

            // Get channel and contract
            const network = await gateway.getNetwork(this.channelName);
            const contract = network.getContract(this.chaincodeName);

            console.log(`Successfully connected to network as ${username}`);
            return { gateway, network, contract };
        } catch (error) {
            console.error('Failed to connect to network:', error);
            throw error;
        }
    }

    async createUserIdentityFromCredentials(wallet, username) {
        try {
            const credentialsPath = path.join(this.orgPath, 'users', `${username}@generator.rec.com`);
            
            // Read certificate
            const certPath = path.join(credentialsPath, 'msp', 'signcerts');
            const certFiles = fs.readdirSync(certPath);
            const certFile = certFiles[0]; // Take the first certificate file
            const certificate = fs.readFileSync(path.join(certPath, certFile), 'utf8');
            
            // Read private key
            const keyPath = path.join(credentialsPath, 'msp', 'keystore');
            const keyFiles = fs.readdirSync(keyPath);
            const keyFile = keyFiles[0]; // Take the first key file
            const privateKey = fs.readFileSync(path.join(keyPath, keyFile), 'utf8');
            
            // Create identity
            const identity = {
                credentials: {
                    certificate: certificate,
                    privateKey: privateKey,
                },
                mspId: 'GeneratorMSP',
                type: 'X.509',
            };
            
            await wallet.put(username, identity);
            console.log(`Successfully created wallet identity for ${username}`);
        } catch (error) {
            console.error(`Failed to create identity for ${username}:`, error);
            throw error;
        }
    }

    async disconnect(gateway) {
        if (gateway) {
            gateway.disconnect();
        }
    }
}

module.exports = FabricService;