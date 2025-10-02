const { Gateway, Wallets } = require('fabric-network');
const path = require('path');
const fs = require('fs');

async function testSimpleConnection() {
    try {
        console.log('Starting simple connection test...');
        
        // Use exact same configuration as CLI
        const connectionProfile = {
            name: 'rec-network',
            version: '1.0.0',
            client: {
                organization: 'GeneratorMSP'
            },
            organizations: {
                GeneratorMSP: {
                    mspid: 'GeneratorMSP',
                    peers: ['peer0.generator.rec.com']
                }
            },
            peers: {
                'peer0.generator.rec.com': {
                    url: 'grpc://localhost:7051',  // Try without TLS first
                    grpcOptions: {
                        'ssl-target-name-override': 'peer0.generator.rec.com'
                    }
                }
            }
        };

        // Create wallet and identity manually
        const walletPath = path.join(__dirname, 'wallets');
        const wallet = await Wallets.newFileSystemWallet(walletPath);
        
        // Check if Admin identity exists
        const identity = await wallet.get('Admin');
        if (!identity) {
            console.log('Creating Admin identity...');
            
            // Read certificate and private key directly
            const orgPath = path.resolve(__dirname, '../organizations/peerOrganizations/generator.rec.com');
            const certPath = path.join(orgPath, 'users/Admin@generator.rec.com/msp/signcerts');
            const keyPath = path.join(orgPath, 'users/Admin@generator.rec.com/msp/keystore');
            
            const certFiles = fs.readdirSync(certPath);
            const keyFiles = fs.readdirSync(keyPath);
            
            const certificate = fs.readFileSync(path.join(certPath, certFiles[0]), 'utf8');
            const privateKey = fs.readFileSync(path.join(keyPath, keyFiles[0]), 'utf8');
            
            const adminIdentity = {
                credentials: {
                    certificate: certificate,
                    privateKey: privateKey,
                },
                mspId: 'GeneratorMSP',
                type: 'X.509',
            };
            
            await wallet.put('Admin', adminIdentity);
            console.log('Admin identity created successfully');
        }

        // Connect with minimal configuration
        const gateway = new Gateway();
        await gateway.connect(connectionProfile, {
            wallet,
            identity: 'Admin',
            discovery: { enabled: false }
        });

        console.log('Gateway connected');

        // Get network and contract
        const network = await gateway.getNetwork('recchannel');
        console.log('Network obtained');
        
        const contract = network.getContract('rec');
        console.log('Contract obtained');

        // Try simple query first
        console.log('Testing simple query...');
        const result = await contract.evaluateTransaction('EnergyDataContract:getAllEnergyData');
        console.log('Query result:', result.toString());

        gateway.disconnect();
        console.log('Test completed successfully');

    } catch (error) {
        console.error('Test failed:', error.message);
        console.error('Stack:', error.stack);
    }
}

testSimpleConnection();