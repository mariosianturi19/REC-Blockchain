const express = require('express');
const router = express.Router();
const { exec } = require('child_process');
const path = require('path');

// Submit Energy Data (Generator role) - FIXED VERSION with proper error handling
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

        // ⭐ FIXED: More robust CLI command with better error detection
        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt --peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt --peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt -c '{"function":"EnergyDataContract:submitEnergyData","Args":["${energyDataId}","${energyAmount}","${energySource}","${generationDate}","${location}","${generatorId}"]}'`;

        console.log('🔄 Executing CLI command for submitEnergyData...');
        console.log('📝 Command:', cliCommand);
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, { timeout: 30000 }, (error, stdout, stderr) => {
                console.log('📤 Raw stdout:', stdout);
                console.log('📤 Raw stderr:', stderr);
                
                // ⭐ FIXED: Better response validation - check for actual success patterns
                if (error) {
                    console.error('❌ CLI command error:', error);
                    reject(new Error(`Command execution failed: ${error.message}`));
                    return;
                }
                
                // ⭐ CRITICAL: Check for actual blockchain errors in stderr
                if (stderr && (stderr.includes('Error:') || stderr.includes('FAILED') || stderr.includes('endorsement failure'))) {
                    console.error('❌ Blockchain endorsement error:', stderr);
                    reject(new Error(`Blockchain error: ${stderr}`));
                    return;
                }
                
                // ⭐ FIXED: Check BOTH stdout AND stderr for success patterns
                const combinedOutput = (stdout || '') + (stderr || '');
                const hasSuccess = combinedOutput && (
                    combinedOutput.includes('status:200') || 
                    combinedOutput.includes('Chaincode invoke successful') ||
                    combinedOutput.includes('result: status:200') ||
                    (combinedOutput.includes('payload:') && !combinedOutput.includes('Error:'))
                );
                
                if (!hasSuccess) {
                    console.error('❌ Transaction not confirmed. Combined output:', combinedOutput);
                    reject(new Error(`Transaction failed - no success confirmation in output`));
                    return;
                }
                
                console.log('✅ CLI command successful');
                resolve(stdout);
            });
        });

        // ⭐ IMPROVED: Add delay and retry mechanism for verification
        console.log('🔍 Verifying data was stored (with retry mechanism)...');
        const verifyCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:getEnergyDataById","Args":["${energyDataId}"]}'`;
        
        let verifyResult = '';
        let retryCount = 0;
        const maxRetries = 3;
        
        while (retryCount < maxRetries) {
            try {
                console.log(`🔄 Verification attempt ${retryCount + 1}/${maxRetries}...`);
                
                // Add delay between attempts
                if (retryCount > 0) {
                    await new Promise(resolve => setTimeout(resolve, 2000)); // 2 second delay
                }
                
                verifyResult = await new Promise((resolve, reject) => {
                    exec(verifyCommand, { timeout: 10000 }, (error, stdout, stderr) => {
                        console.log(`🔍 Attempt ${retryCount + 1} - stdout:`, stdout);
                        console.log(`🔍 Attempt ${retryCount + 1} - stderr:`, stderr);
                        
                        if (error && stderr.includes('does not exist')) {
                            console.log(`⏳ Data not yet available, will retry...`);
                            reject(new Error('Data not yet available'));
                            return;
                        }
                        
                        if (error) {
                            console.error(`❌ Attempt ${retryCount + 1} error:`, error);
                            reject(error);
                            return;
                        }
                        
                        if (!stdout || stdout.trim() === '') {
                            console.log(`📭 No data returned on attempt ${retryCount + 1}`);
                            reject(new Error('No data returned'));
                            return;
                        }
                        
                        console.log(`✅ Verification successful on attempt ${retryCount + 1}`);
                        resolve(stdout);
                    });
                });
                
                // If we reach here, verification was successful
                break;
                
            } catch (error) {
                retryCount++;
                console.log(`⚠️ Verification attempt ${retryCount} failed:`, error.message);
                
                if (retryCount >= maxRetries) {
                    console.log('❌ All verification attempts failed, but submission was successful');
                    // Don't fail the entire request - data was successfully submitted
                    res.status(201).json({
                        success: true,
                        message: 'Energy data submitted successfully (verification skipped due to timing)',
                        data: {
                            id: energyDataId,
                            status: 'SUBMITTED_UNVERIFIED',
                            note: 'Data submitted to blockchain but verification timed out - this is normal behavior'
                        }
                    });
                    return;
                }
            }
        }

        try {
            const storedData = JSON.parse(verifyResult.trim());
            if (storedData.id === energyDataId) {
                console.log('✅ Data verification successful - data is stored in blockchain');
                
                res.status(201).json({
                    success: true,
                    message: 'Energy data submitted and verified successfully',
                    data: {
                        id: energyDataId,
                        status: 'STORED_AND_VERIFIED',
                        storedData: storedData
                    }
                });
            } else {
                throw new Error('Verification failed - data mismatch');
            }
        } catch (verifyError) {
            console.error('❌ Data verification failed:', verifyError);
            throw new Error('Data submission succeeded but verification failed - data may not be properly stored');
        }

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

// Get All Energy Data - IMPROVED VERSION with better error handling
router.get('/', async (req, res) => {
    try {
        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:getAllEnergyData","Args":[]}'`;

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

        // Parse the result with better error handling
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

// Get Energy Data by ID - Using CLI approach
router.get('/:energyDataId', async (req, res) => {
    try {
        const { energyDataId } = req.params;

        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:getEnergyDataById","Args":["${energyDataId}"]}'`;

        console.log('Executing CLI command for getEnergyDataById...');
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, (error, stdout, stderr) => {
                if (error) {
                    console.error('CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr && stderr.includes('does not exist')) {
                    reject(new Error(`Energy data ${energyDataId} not found`));
                    return;
                }
                console.log('CLI command stdout:', stdout);
                resolve(stdout);
            });
        });

        // Parse the result
        const energyData = JSON.parse(result.trim());

        res.json({
            success: true,
            data: energyData
        });

    } catch (error) {
        console.error('Error getting energy data:', error);
        
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

// Verify Energy Data (Issuer role) - Using CLI approach
router.put('/verify/:energyDataId', async (req, res) => {
    try {
        const { energyDataId } = req.params;
        const { issuerId, verificationNotes } = req.body;

        if (!issuerId) {
            return res.status(400).json({
                success: false,
                message: 'issuerId is required'
            });
        }

        const cliCommand = `cd /home/najla/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec --peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt --peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt --peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt -c '{"function":"EnergyDataContract:verifyEnergyData","Args":["${energyDataId}","${issuerId}"]}'`;

        console.log('Executing CLI command for verifyEnergyData...');
        
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

        // Parse the result from CLI output
        const match = result.match(/payload:"(.+?)"/);
        if (match && match[1]) {
            const responseData = JSON.parse(match[1].replace(/\\/g, ''));
            res.json({
                success: true,
                message: 'Energy data verified successfully',
                data: responseData
            });
        } else {
            res.json({
                success: true,
                message: 'Energy data verified successfully',
                data: { message: 'Verification completed' }
            });
        }

    } catch (error) {
        console.error('Error verifying energy data:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to verify energy data',
            error: error.message
        });
    }
});

module.exports = router;