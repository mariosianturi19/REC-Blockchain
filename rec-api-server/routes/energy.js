const express = require('express');
const router = express.Router();
const { exec } = require('child_process');
const path = require('path');

// Submit Energy Data (Generator role) - UPDATED with proper endorsement
router.post('/submit', async (req, res) => {
    try {
        const { energyDataId, generatorId, energyAmount, generationDate, location, energySource } = req.body;

        // Validate required fields
        if (!energyDataId || !generatorId || !energyAmount || !generationDate || !location || !energySource) {
            return res.status(400).json({
                success: false,
                message: 'All fields are required: energyDataId, generatorId, energyAmount, generationDate, location, energySource'
            });
        }

        // ✅ UPDATED: Use proper endorsement with multiple peers
        const cliCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt --peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt -c '{"function":"EnergyDataContract:submitEnergyData","Args":["${energyDataId}","${energyAmount}","${energySource}","${generationDate}","${location}","${generatorId}"]}' --waitForEvent`;

        console.log('🔄 Executing CLI command for submitEnergyData...');
        console.log('📝 Data:', { energyDataId, energyAmount, energySource, generationDate, location, generatorId });
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, { timeout: 30000 }, (error, stdout, stderr) => {
                console.log('📤 Raw stdout:', stdout);
                console.log('📤 Raw stderr:', stderr);
                
                if (error) {
                    console.error('❌ CLI command error:', error);
                    reject(new Error(`Command execution failed: ${error.message}`));
                    return;
                }
                
                // Check for blockchain success patterns
                const combinedOutput = (stdout || '') + (stderr || '');
                const hasSuccess = combinedOutput && (
                    combinedOutput.includes('status:200') || 
                    combinedOutput.includes('Chaincode invoke successful') ||
                    combinedOutput.includes('committed with status (VALID)') ||
                    (combinedOutput.includes('payload:') && !combinedOutput.includes('Error:'))
                );
                
                if (!hasSuccess) {
                    console.error('❌ Transaction not confirmed. Combined output:', combinedOutput);
                    reject(new Error(`Transaction failed - no success confirmation`));
                    return;
                }
                
                console.log('✅ Energy data submitted successfully');
                resolve(stdout);
            });
        });

        // Try to extract the response data from the CLI output
        let responseData = null;
        try {
            const payloadMatch = result.match(/payload:"({.*?})"/);
            if (payloadMatch && payloadMatch[1]) {
                responseData = JSON.parse(payloadMatch[1].replace(/\\/g, ''));
                console.log('📊 Extracted response data:', responseData);
            }
        } catch (parseError) {
            console.log('⚠️ Could not parse response data, but submission was successful');
        }

        res.status(201).json({
            success: true,
            message: 'Energy data submitted successfully',
            data: {
                energyDataId: energyDataId,
                status: 'PENDING',  // ✅ Changed from 'PENDING_VERIFICATION' to 'PENDING'
                submittedAt: new Date().toISOString(),
                details: responseData || {
                    energyDataId,
                    energyAmount: parseFloat(energyAmount),
                    energySource,
                    generationDate,
                    location,
                    generatorId
                }
            }
        });

    } catch (error) {
        console.error('💥 Error submitting energy data:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to submit energy data',
            error: error.message,
            details: 'Check blockchain network connectivity and chaincode deployment'
        });
    }
});

// Verify Energy Data (Issuer role) - UPDATED with proper endorsement
router.put('/verify/:energyDataId', async (req, res) => {
    try {
        const { energyDataId } = req.params;
        const { issuerId } = req.body;

        if (!issuerId) {
            return res.status(400).json({
                success: false,
                message: 'issuerId is required'
            });
        }

        // ✅ UPDATED: Use proper endorsement with multiple peers
        const cliCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt --peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt -c '{"function":"EnergyDataContract:verifyEnergyData","Args":["${energyDataId}","${issuerId}"]}' --waitForEvent`;

        console.log('🔄 Executing CLI command for verifyEnergyData...');
        console.log('📝 Data:', { energyDataId, issuerId });
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, { timeout: 30000 }, (error, stdout, stderr) => {
                console.log('📤 Raw stdout:', stdout);
                console.log('📤 Raw stderr:', stderr);
                
                if (error) {
                    console.error('❌ CLI command error:', error);
                    reject(error);
                    return;
                }
                
                // Check for success patterns
                const combinedOutput = (stdout || '') + (stderr || '');
                const hasSuccess = combinedOutput && (
                    combinedOutput.includes('status:200') || 
                    combinedOutput.includes('Chaincode invoke successful') ||
                    combinedOutput.includes('committed with status (VALID)')
                );
                
                if (!hasSuccess) {
                    console.error('❌ Verification failed:', combinedOutput);
                    reject(new Error('Energy data verification failed'));
                    return;
                }
                
                console.log('✅ Energy data verified successfully');
                resolve(stdout);
            });
        });

        // Try to extract the response data
        let responseData = null;
        try {
            const payloadMatch = result.match(/payload:"({.*?})"/);
            if (payloadMatch && payloadMatch[1]) {
                responseData = JSON.parse(payloadMatch[1].replace(/\\/g, ''));
            }
        } catch (parseError) {
            console.log('⚠️ Could not parse response data, but verification was successful');
        }

        res.json({
            success: true,
            message: 'Energy data verified successfully',
            data: {
                energyDataId: energyDataId,
                status: 'VERIFIED',
                verifiedBy: issuerId,
                verifiedAt: new Date().toISOString(),
                details: responseData
            }
        });

    } catch (error) {
        console.error('💥 Error verifying energy data:', error);
        
        if (error.message.includes('does not exist')) {
            res.status(404).json({
                success: false,
                message: 'Energy data not found',
                error: error.message
            });
        } else {
            res.status(500).json({
                success: false,
                message: 'Failed to verify energy data',
                error: error.message
            });
        }
    }
});

// Get All Energy Data - UPDATED query method
router.get('/', async (req, res) => {
    try {
        const cliCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:getAllEnergyData","Args":[]}'`;

        console.log('🔄 Executing CLI command for getAllEnergyData...');
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.error('❌ CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr && stderr.includes('Error:')) {
                    console.error('❌ Blockchain query error:', stderr);
                    reject(new Error(stderr));
                    return;
                }
                console.log('✅ Query successful, raw output:', stdout);
                resolve(stdout);
            });
        });

        // Parse the result
        let energyDataList = [];
        try {
            const trimmedResult = result.trim();
            if (trimmedResult && trimmedResult !== '[]' && trimmedResult !== '') {
                energyDataList = JSON.parse(trimmedResult);
                console.log('📊 Parsed energy data:', energyDataList.length, 'records');
            } else {
                console.log('📭 No energy data found in blockchain');
            }
        } catch (parseError) {
            console.error('❌ Parse error:', parseError);
            console.log('Raw result that failed to parse:', result);
            energyDataList = [];
        }

        res.json({
            success: true,
            data: energyDataList,
            count: energyDataList.length,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        console.error('💥 Error getting all energy data:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to get energy data',
            error: error.message,
            details: 'Check blockchain network connectivity'
        });
    }
});

// Get Energy Data by ID - UPDATED query method
router.get('/:energyDataId', async (req, res) => {
    try {
        const { energyDataId } = req.params;

        const cliCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:getEnergyDataById","Args":["${energyDataId}"]}'`;

        console.log('🔄 Executing CLI command for getEnergyDataById...');
        console.log('📝 Energy ID:', energyDataId);
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.error('❌ CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr && stderr.includes('does not exist')) {
                    reject(new Error(`Energy data ${energyDataId} not found`));
                    return;
                }
                console.log('✅ Query successful, raw output:', stdout);
                resolve(stdout);
            });
        });

        // Parse the result
        const energyData = JSON.parse(result.trim());

        res.json({
            success: true,
            data: energyData,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        console.error('💥 Error getting energy data:', error);
        
        if (error.message.includes('not found')) {
            res.status(404).json({
                success: false,
                message: 'Energy data not found',
                error: error.message
            });
        } else {
            res.status(500).json({
                success: false,
                message: 'Failed to get energy data',
                error: error.message
            });
        }
    }
});

module.exports = router;