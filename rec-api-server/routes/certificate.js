const express = require('express');
const router = express.Router();
const { exec } = require('child_process');

// Step 3: Generator Request Certificate
router.post('/request', async (req, res) => {
    try {
        const { certId, energyId, generatorId } = req.body;

        if (!certId || !energyId || !generatorId) {
            return res.status(400).json({
                success: false,
                message: 'All fields are required: certId, energyId, generatorId'
            });
        }

        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt --peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt --peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt -c '{"function":"CertificateContract:createCertificateRequest","Args":["${certId}","${energyId}","${generatorId}"]}'`;

        console.log('Executing CLI command for createCertificateRequest...');
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, (error, stdout, stderr) => {
                if (error) {
                    console.error('CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr) {
                    console.log('CLI command stderr:', stderr);
                }
                console.log('CLI command stdout:', stdout);
                resolve(stdout);
            });
        });

        // Parse result from CLI output
        const match = result.match(/payload:"(.+?)"/);
        if (match && match[1]) {
            const responseData = JSON.parse(match[1].replace(/\\/g, ''));
            res.status(201).json({
                success: true,
                message: 'Certificate request created successfully',
                data: responseData
            });
        } else {
            res.status(201).json({
                success: true,
                message: 'Certificate request created successfully',
                data: { message: 'Certificate request completed' }
            });
        }

    } catch (error) {
        console.error('Error creating certificate request:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to create certificate request',
            error: error.message
        });
    }
});

// Step 4: Issuer Issue Certificate
router.put('/issue/:certId', async (req, res) => {
    try {
        const { certId } = req.params;
        const { issuerId } = req.body;

        if (!issuerId) {
            return res.status(400).json({
                success: false,
                message: 'issuerId is required'
            });
        }

        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt --peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt --peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt -c '{"function":"CertificateContract:issueCertificate","Args":["${certId}","${issuerId}"]}'`;

        console.log('Executing CLI command for issueCertificate...');
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, (error, stdout, stderr) => {
                if (error) {
                    console.error('CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr) {
                    console.log('CLI command stderr:', stderr);
                }
                console.log('CLI command stdout:', stdout);
                resolve(stdout);
            });
        });

        // Parse result from CLI output
        const match = result.match(/payload:"(.+?)"/);
        if (match && match[1]) {
            const responseData = JSON.parse(match[1].replace(/\\/g, ''));
            res.json({
                success: true,
                message: 'Certificate issued successfully',
                data: responseData
            });
        } else {
            res.json({
                success: true,
                message: 'Certificate issued successfully',
                data: { message: 'Certificate issuance completed' }
            });
        }

    } catch (error) {
        console.error('Error issuing certificate:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to issue certificate',
            error: error.message
        });
    }
});

// Step 5: Buyer Create Purchase Request
router.post('/purchase', async (req, res) => {
    try {
        const { certId, buyerId, amount } = req.body;

        if (!certId || !buyerId || !amount) {
            return res.status(400).json({
                success: false,
                message: 'All fields are required: certId, buyerId, amount'
            });
        }

        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt --peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt --peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt -c '{"function":"CertificateContract:createPurchaseRequest","Args":["${certId}","${buyerId}","${amount}"]}'`;

        console.log('Executing CLI command for createPurchaseRequest...');
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, (error, stdout, stderr) => {
                if (error) {
                    console.error('CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr) {
                    console.log('CLI command stderr:', stderr);
                }
                console.log('CLI command stdout:', stdout);
                resolve(stdout);
            });
        });

        // Parse result from CLI output
        const match = result.match(/payload:"(.+?)"/);
        if (match && match[1]) {
            const responseData = JSON.parse(match[1].replace(/\\/g, ''));
            res.status(201).json({
                success: true,
                message: 'Purchase request created successfully',
                data: responseData
            });
        } else {
            res.status(201).json({
                success: true,
                message: 'Purchase request created successfully',
                data: { message: 'Purchase request completed' }
            });
        }

    } catch (error) {
        console.error('Error creating purchase request:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to create purchase request',
            error: error.message
        });
    }
});

// Step 6: Issuer Confirm Purchase
router.put('/confirm/:certId', async (req, res) => {
    try {
        const { certId } = req.params;

        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt --peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt --peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt -c '{"function":"CertificateContract:confirmPurchase","Args":["${certId}"]}'`;

        console.log('Executing CLI command for confirmPurchase...');
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, (error, stdout, stderr) => {
                if (error) {
                    console.error('CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr) {
                    console.log('CLI command stderr:', stderr);
                }
                console.log('CLI command stdout:', stdout);
                resolve(stdout);
            });
        });

        // Parse result from CLI output
        const match = result.match(/payload:"(.+?)"/);
        if (match && match[1]) {
            const responseData = JSON.parse(match[1].replace(/\\/g, ''));
            res.json({
                success: true,
                message: 'Purchase confirmed successfully',
                data: responseData
            });
        } else {
            res.json({
                success: true,
                message: 'Purchase confirmed successfully',
                data: { message: 'Purchase confirmation completed' }
            });
        }

    } catch (error) {
        console.error('Error confirming purchase:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to confirm purchase',
            error: error.message
        });
    }
});

// Get Certificate by ID
router.get('/:certId', async (req, res) => {
    try {
        const { certId } = req.params;

        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:getCertificateById","Args":["${certId}"]}'`;

        console.log('Executing CLI command for getCertificateById...');
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, (error, stdout, stderr) => {
                if (error) {
                    console.error('CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr && stderr.includes('does not exist')) {
                    reject(new Error(`Certificate ${certId} not found`));
                    return;
                }
                console.log('CLI command stdout:', stdout);
                resolve(stdout);
            });
        });

        // Parse the result
        const certificate = JSON.parse(result.trim());

        res.json({
            success: true,
            data: certificate
        });

    } catch (error) {
        console.error('Error getting certificate:', error);
        
        if (error.message.includes('not found')) {
            res.status(404).json({
                success: false,
                message: 'Certificate not found',
                error: error.message
            });
        } else {
            res.status(500).json({
                success: false,
                message: 'Failed to get certificate',
                error: error.message
            });
        }
    }
});

// Get All Purchased Certificates
router.get('/', async (req, res) => {
    try {
        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:getAllPurchasedCertificates","Args":[]}'`;

        console.log('Executing CLI command for getAllPurchasedCertificates...');
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, (error, stdout, stderr) => {
                if (error) {
                    console.error('CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr && !stdout) {
                    console.log('CLI command stderr:', stderr);
                    reject(new Error(stderr));
                    return;
                }
                console.log('CLI command stdout:', stdout);
                resolve(stdout);
            });
        });

        // Parse the result
        let certificates = [];
        try {
            certificates = JSON.parse(result.trim());
        } catch (parseError) {
            console.log('Parse error, returning empty array:', parseError);
            certificates = [];
        }

        res.json({
            success: true,
            data: certificates
        });

    } catch (error) {
        console.error('Error getting certificates:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to get certificates',
            error: error.message
        });
    }
});

module.exports = router;