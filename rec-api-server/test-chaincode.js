const { Gateway, Wallets } = require('fabric-network');
const path = require('path');
const fs = require('fs');

async function testChaincode() {
    try {
        console.log('Starting chaincode test...');
        
        // Simple connection profile
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
                    url: 'grpcs://localhost:7051',
                    tlsCACerts: {
                        path: '../organizations/peerOrganizations/generator.rec.com/tlsca/tlsca.generator.rec.com-cert.pem'
                    },
                    grpcOptions: {
                        'ssl-target-name-override': 'peer0.generator.rec.com'
                    }
                }
            }
        };

        // Create wallet
        const walletPath = path.join(__dirname, 'wallets');
        const wallet = await Wallets.newFileSystemWallet(walletPath);
        
        // Get identity
        const identity = await wallet.get('Admin');
        if (!identity) {
            throw new Error('Admin identity not found in wallet');
        }

        // Connect gateway
        const gateway = new Gateway();
        await gateway.connect(connectionProfile, {
            wallet,
            identity: 'Admin',
            discovery: { enabled: false }
        });

        console.log('Gateway connected successfully');

        // Get network and contract
        const network = await gateway.getNetwork('recchannel');
        const contract = network.getContract('rec');

        console.log('Contract obtained successfully');

        // Test getAllEnergyData first
        console.log('Testing getAllEnergyData...');
        const allDataResult = await contract.evaluateTransaction('EnergyDataContract:getAllEnergyData');
        console.log('All energy data:', allDataResult.toString());

        // Test submitEnergyData
        console.log('Testing submitEnergyData...');
        const submitResult = await contract.submitTransaction(
            'EnergyDataContract:submitEnergyData',
            'TEST_API_SDK_001',
            '2500',
            'Solar',
            '2025-10-02',
            'SDK Test Location',
            'GEN-SDK-001'
        );
        console.log('Submit result:', submitResult.toString());

        gateway.disconnect();
        console.log('Test completed successfully');

    } catch (error) {
        console.error('Test failed:', error);
    }
}

testChaincode();