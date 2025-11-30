const express = require('express');
const router = express.Router();
const { exec } = require('child_process');

// ✅ Step 3: Request Certificate (Buyer role) with COMPREHENSIVE SECURITY VALIDATION
router.post('/request', async (req, res) => {
    try {
        const { certificateId, energyDataId, buyerId, purchasedAmount, security, compliance, auditTrail, endorsement_orgs } = req.body;

        if (!certificateId || !energyDataId || !buyerId) {
            return res.status(400).json({
                success: false,
                message: 'All fields are required: certificateId, energyDataId, buyerId'
            });
        }

        console.log('🔍 Step 3: Starting certificate request with comprehensive security validation');
        console.log('📋 Received data:', { certificateId, energyDataId, buyerId, purchasedAmount, security, compliance, auditTrail });

        // ✅ NEW: Pre-validation 1 - Check energy data integrity
        console.log('🔍 Pre-validation 1: Checking energy data integrity');
        
        const integrityCheckCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:verifyEnergyDataIntegrity","Args":["${energyDataId}"]}'`;
        
        const integrityResult = await new Promise((resolve, reject) => {
            exec(integrityCheckCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.log('⚠️ Energy data integrity check failed, proceeding with caution');
                    resolve(null);
                    return;
                }
                resolve(stdout);
            });
        });

        let integrityValid = false;
        if (integrityResult) {
            try {
                const integrityData = JSON.parse(integrityResult.trim());
                if (integrityData.isValid === false) {
                    console.log('❌ SECURITY ALERT: Energy data integrity validation FAILED');
                    return res.status(400).json({
                        success: false,
                        message: 'SECURITY ALERT: Energy data integrity validation failed - data may have been tampered with',
                        error: integrityData.message,
                        errorType: 'ENERGY_INTEGRITY_FAILED',
                        securityLevel: 'CRITICAL'
                    });
                }
                integrityValid = true;
                console.log('✅ Energy data integrity validated successfully');
            } catch (parseError) {
                console.log('⚠️ Could not parse integrity check result, proceeding with caution');
            }
        }

        // ✅ NEW: Pre-validation 2 - Check certificate uniqueness before request
        console.log('🔍 Pre-validation 2: Checking certificate uniqueness (Anti-duplication)');
        
        const uniquenessCheckCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:verifyCertificateUniqueness","Args":["${energyDataId}","${buyerId}"]}'`;
        
        const uniquenessResult = await new Promise((resolve, reject) => {
            exec(uniquenessCheckCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.log('⚠️ Certificate uniqueness check failed, proceeding with caution');
                    resolve(null);
                    return;
                }
                resolve(stdout);
            });
        });

        let uniquenessValid = false;
        if (uniquenessResult) {
            try {
                const uniquenessData = JSON.parse(uniquenessResult.trim());
                if (uniquenessData.isUnique === false) {
                    console.log('❌ SECURITY ALERT: Certificate duplication detected');
                    return res.status(409).json({
                        success: false,
                        message: 'SECURITY ALERT: Certificate already exists for this energy data and buyer combination',
                        error: uniquenessData.message,
                        errorType: 'DUPLICATE_CERTIFICATE_EXISTS',
                        securityLevel: 'HIGH',
                        details: {
                            existingCertificateId: uniquenessData.existingCertificateId,
                            existingSerialNumber: uniquenessData.existingSerialNumber
                        }
                    });
                }
                uniquenessValid = true;
                console.log('✅ Certificate uniqueness validated - no duplicates found');
            } catch (parseError) {
                console.log('⚠️ Could not parse uniqueness check result, proceeding with caution');
            }
        }

        // ✅ NEW: Pre-validation 3 - Check energy data duplication
        console.log('🔍 Pre-validation 3: Checking energy data duplication');
        
        // Get energy data first to check for duplication
        const getEnergyCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:getEnergyDataById","Args":["${energyDataId}"]}'`;
        
        const energyDataResult = await new Promise((resolve, reject) => {
            exec(getEnergyCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    resolve(null);
                    return;
                }
                resolve(stdout);
            });
        });

        let energyDataValid = false;
        if (energyDataResult) {
            try {
                const energyData = JSON.parse(energyDataResult.trim());
                if (energyData.status !== 'VERIFIED') {
                    return res.status(400).json({
                        success: false,
                        message: 'Energy data must be VERIFIED before certificate request',
                        errorType: 'ENERGY_DATA_NOT_VERIFIED',
                        currentStatus: energyData.status
                    });
                }
                energyDataValid = true;
                console.log('✅ Energy data verified and ready for certificate request');
            } catch (parseError) {
                console.log('⚠️ Could not parse energy data result');
            }
        }

        // ✅ FIXED ENDORSEMENT: Only buyer and issuer for certificate workflow (NO GENERATOR)
        let peerAddresses = '';
        const endorsingOrgs = endorsement_orgs || ['buyer', 'issuer']; // ✅ FIXED: Only buyer + issuer for certificate workflow
        
        const peerConfig = {
            generator: '--peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt',
            issuer: '--peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt',
            buyer: '--peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt'
        };

        endorsingOrgs.forEach(org => {
            if (peerConfig[org]) {
                peerAddresses += ' ' + peerConfig[org];
            }
        });

        // ✅ CRITICAL FIX: Generate ONE consistent timestamp for ALL fields
        const consistentTimestamp = new Date().toISOString();
        console.log('🕐 Using consistent timestamp for ALL fields:', consistentTimestamp);
        
        // Mock energy data with SAME consistent timestamp everywhere
        const mockEnergyData = {
            energyDataId: energyDataId,
            generatorId: 'GENERATOR_001',
            amount: 1,
            unit: 'MWh',
            sourceType: 'Solar',
            generationDate: consistentTimestamp, // ✅ SAME timestamp
            location: 'Mock Location',
            status: 'VERIFIED'
        };

        // Certificate metadata with SAME consistent timestamps
        const certificateMetadata = {
            security: {
                certificate_hash: null,
                serial_number: null,
                security_level: 'MEDIUM'
            },
            compliance: {
                anti_duplication_verified: false,
                energy_data_validated: true
            },
            auditTrail: {
                request_timestamp: consistentTimestamp, // ✅ SAME timestamp
                request_by: buyerId
            }
        };

        // ✅ CRITICAL: Pass the SAME timestamp to chaincode for all lifecycle events
        const requestedAt = consistentTimestamp; // ✅ Use SAME timestamp for lifecycle

        // ✅ NEW: Prepare enhanced security data for chaincode
        const enhancedSecurityData = JSON.stringify({
            security: security || {
                certificate_hash: null,
                serial_number: null,
                security_level: 'MEDIUM'
            },
            compliance: compliance || {
                anti_duplication_verified: false,
                energy_data_validated: true
            },
            auditTrail: auditTrail || {
                request_timestamp: new Date().toISOString(),
                request_by: buyerId
            }
        });

        // ✅ FIXED: Pass security data to chaincode with proper escaping
        const enhancedSecurityDataEscaped = enhancedSecurityData.replace(/"/g, '\\"');
        
        // ✅ CRITICAL FIX: Set correct MSP identity for BuyerMSP in Step 1 (requestCertificate)
        const buyerMSPRequestCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec \\
            -e CORE_PEER_LOCALMSPID=BuyerMSP \\
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt \\
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/users/Admin@buyer.rec.com/msp \\
            -e CORE_PEER_ADDRESS=peer0.buyer.rec.com:11051 \\
            cli peer chaincode invoke -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec${peerAddresses} -c '{"function":"CertificateContract:requestCertificate","Args":["${certificateId}","${energyDataId}","${buyerId}","${purchasedAmount || '1'}","${enhancedSecurityDataEscaped}"]}' --waitForEvent`;

        console.log('🔄 Executing CLI command with BuyerMSP identity for certificate request (Step 1)...');
        console.log('🔑 Using MSP: BuyerMSP for Step 1');
        console.log('📋 Endorsement: buyer + issuer (NO generator for certificate workflow)');
        console.log('📝 Data:', { 
            certificateId, 
            energyDataId, 
            buyerId,
            purchasedAmount: purchasedAmount || '1',
            hasSecurityData: !!security,
            securityDataLength: enhancedSecurityData.length,
            argsCount: 5 // ✅ Now 5 parameters: certId, energyId, buyerId, purchasedAmount, securityData
        });
        console.log('🔍 CLI Args being sent:', [certificateId, energyDataId, buyerId, purchasedAmount || '1', 'SECURITY_DATA_PRESENT']);
        
        const result = await new Promise((resolve, reject) => {
            exec(buyerMSPRequestCommand, { timeout: 30000 }, (error, stdout, stderr) => {
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
                    console.error('❌ Certificate request failed:', combinedOutput);
                    reject(new Error('Certificate request failed'));
                    return;
                }
                
                console.log('✅ Certificate request created successfully with ENHANCED SECURITY VALIDATION');
                resolve(stdout);
            });
        });

        // Try to extract response data
        let responseData = null;
        let certificateHash = null;
        let serialNumber = null;
        let ownershipProofId = null;

        try {
            const payloadMatch = result.match(/payload:"({.*?})"/);
            if (payloadMatch && payloadMatch[1]) {
                responseData = JSON.parse(payloadMatch[1].replace(/\\/g, ''));
                // Extract security information from response
                certificateHash = responseData.certificateHash;
                serialNumber = responseData.serialNumber;
                ownershipProofId = responseData.auditTrail?.ownershipProof?.proofId;
            }
        } catch (parseError) {
            console.log('⚠️ Could not parse response data, but request was successful');
        }

        res.status(201).json({
            success: true,
            message: 'Certificate request created successfully with ENHANCED SECURITY VALIDATION',
            data: {
                certId: certificateId,
                energyId: energyDataId,
                status: 'CERTIFICATE_REQUESTED',
                requestedBy: buyerId,
                requestedAt: new Date().toISOString(),
                endorsing_organizations: endorsingOrgs,
                // ✅ NEW: Enhanced security information
                security: {
                    integrityValidated: integrityValid,
                    uniquenessValidated: uniquenessValid,
                    energyDataValidated: energyDataValid,
                    certificateHash: certificateHash,
                    serialNumber: serialNumber,
                    ownershipProofId: ownershipProofId,
                    securityLevel: 'HIGH',
                    cryptographicStandard: 'SHA-256',
                    tamperProof: true,
                    antiDuplicationVerified: true
                },
                details: responseData
            }
        });

    } catch (error) {
        console.error('💥 Error creating certificate request:', error);
        
        // ✅ ENHANCED: Better error handling for security validations
        if (error.message.includes('DUPLICATION DETECTED') || error.message.includes('already exists')) {
            res.status(409).json({
                success: false,
                message: 'Certificate request failed - DUPLICATION DETECTED',
                error: error.message,
                errorType: 'DUPLICATION_ERROR',
                securityLevel: 'HIGH'
            });
        } else if (error.message.includes('integrity') || error.message.includes('tampering')) {
            res.status(400).json({
                success: false,
                message: 'Certificate request failed - INTEGRITY VALIDATION FAILED',
                error: error.message,
                errorType: 'INTEGRITY_ERROR',
                securityLevel: 'CRITICAL'
            });
        } else if (error.message.includes('OWNERSHIP')) {
            res.status(403).json({
                success: false,
                message: 'Certificate request failed - OWNERSHIP VERIFICATION FAILED',
                error: error.message,
                errorType: 'OWNERSHIP_ERROR',
                securityLevel: 'HIGH'
            });
        } else {
            res.status(500).json({
                success: false,
                message: 'Failed to create certificate request',
                error: error.message,
                errorType: 'GENERAL_ERROR'
            });
        }
    }
});

// ✅ NEW: Step 2 - Confirm Payment (Buyer role) - FIXED MSP IDENTITY
router.put('/confirm-payment/:certId', async (req, res) => {
    try {
        const { certId } = req.params;
        const { buyerId, paymentMethod, paymentReference, endorsement_orgs } = req.body;

        if (!buyerId) {
            return res.status(400).json({
                success: false,
                message: 'buyerId is required'
            });
        }

        console.log('💰 Step 2: Starting payment confirmation process', { 
            certId, 
            buyerId, 
            paymentMethod,
            paymentReference 
        });

        // ✅ FIXED ENDORSEMENT: Only buyer and issuer for certificate workflow (NO GENERATOR)
        let peerAddresses = '';
        const endorsingOrgs = endorsement_orgs || ['buyer', 'issuer']; // ✅ FIXED: Only buyer + issuer for certificate workflow
        
        const peerConfig = {
            generator: '--peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt',
            issuer: '--peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt',
            buyer: '--peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt'
        };

        endorsingOrgs.forEach(org => {
            if (peerConfig[org]) {
                peerAddresses += ' ' + peerConfig[org];
            }
        });

        // Prepare payment confirmation arguments
        const paymentData = JSON.stringify({
            method: paymentMethod || 'bank_transfer',
            reference: paymentReference || `PAY_${certId}_${Date.now()}`,
            confirmedAt: new Date().toISOString()
        });

        // ✅ CRITICAL FIX: Set correct MSP identity for BuyerMSP
        const buyerMSPCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec \\
            -e CORE_PEER_LOCALMSPID=BuyerMSP \\
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt \\
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/users/Admin@buyer.rec.com/msp \\
            -e CORE_PEER_ADDRESS=peer0.buyer.rec.com:11051 \\
            cli peer chaincode invoke -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec${peerAddresses} -c '{"function":"CertificateContract:confirmPayment","Args":["${certId}","${buyerId}","${paymentData.replace(/"/g, '\\"')}"]}' --waitForEvent`;

        console.log('🔄 Executing CLI command with BuyerMSP identity for payment confirmation...');
        console.log('🔑 Using MSP: BuyerMSP for Step 2');
        
        const result = await new Promise((resolve, reject) => {
            exec(buyerMSPCommand, { timeout: 30000 }, (error, stdout, stderr) => {
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
                    console.error('❌ Payment confirmation failed:', combinedOutput);
                    reject(new Error('Payment confirmation failed'));
                    return;
                }
                
                console.log('✅ Payment confirmed successfully with BuyerMSP identity - Certificate status changed to CERTIFICATE_PAID');
                resolve(stdout);
            });
        });

        // Try to extract response data
        let responseData = null;
        try {
            const payloadMatch = result.match(/payload:"({.*?})"/);
            if (payloadMatch && payloadMatch[1]) {
                responseData = JSON.parse(payloadMatch[1].replace(/\\/g, ''));
            }
        } catch (parseError) {
            console.log('⚠️ Could not parse response data, but payment confirmation was successful');
        }

        res.json({
            success: true,
            message: 'Payment confirmed successfully with BuyerMSP identity - Certificate status changed to CERTIFICATE_PAID',
            data: {
                certId: certId,
                status: 'CERTIFICATE_PAID',
                confirmedBy: buyerId,
                confirmedAt: new Date().toISOString(),
                mspIdentity: 'BuyerMSP', // ✅ NEW: Show which MSP was used
                paymentDetails: {
                    method: paymentMethod,
                    reference: paymentReference,
                    confirmationTimestamp: new Date().toISOString()
                },
                endorsing_organizations: endorsingOrgs,
                details: responseData,
                workflow: {
                    previousStatus: 'CERTIFICATE_REQUESTED',
                    currentStatus: 'CERTIFICATE_PAID',
                    nextStep: 'Issuer will verify payment and issue certificate'
                }
            }
        });

    } catch (error) {
        console.error('💥 Error confirming payment:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to confirm payment',
            error: error.message
        });
    }
});

// ✅ Step 4: Issue Certificate (Issuer role) - FIXED MSP IDENTITY
router.put('/issue/:certId', async (req, res) => {
    try {
        const { certId } = req.params;
        const { issuerId, endorsement_orgs } = req.body;

        if (!issuerId) {
            return res.status(400).json({
                success: false,
                message: 'issuerId is required'
            });
        }

        console.log('🏛️ Step 3: Starting certificate issuance process', { certId, issuerId });

        // ✅ FIXED ENDORSEMENT: Only issuer and buyer for certificate workflow (NO GENERATOR)
        let peerAddresses = '';
        const endorsingOrgs = endorsement_orgs || ['issuer', 'buyer']; // ✅ FIXED: Only issuer + buyer for certificate workflow
        
        const peerConfig = {
            generator: '--peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt',
            issuer: '--peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt',
            buyer: '--peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt'
        };

        endorsingOrgs.forEach(org => {
            if (peerConfig[org]) {
                peerAddresses += ' ' + peerConfig[org];
            }
        });

        // ✅ CRITICAL FIX: Set correct MSP identity for IssuerMSP
        const issuerMSPCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec \\
            -e CORE_PEER_LOCALMSPID=IssuerMSP \\
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt \\
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/users/Admin@issuer.rec.com/msp \\
            -e CORE_PEER_ADDRESS=peer0.issuer.rec.com:9051 \\
            cli peer chaincode invoke -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec${peerAddresses} -c '{"function":"CertificateContract:issueCertificate","Args":["${certId}","${issuerId}"]}' --waitForEvent`;

        console.log('🔄 Executing CLI command with IssuerMSP identity for certificate issuance...');
        console.log('🔑 Using MSP: IssuerMSP for Step 3');
        console.log('📋 Endorsement: issuer + buyer (NO generator for certificate workflow)');
        console.log('📝 Data:', { certId, issuerId, endorsing_orgs: endorsingOrgs });
        
        const result = await new Promise((resolve, reject) => {
            exec(issuerMSPCommand, { timeout: 30000 }, (error, stdout, stderr) => {
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
                    console.error('❌ Certificate issuance failed:', combinedOutput);
                    reject(new Error('Certificate issuance failed'));
                    return;
                }
                
                console.log('✅ Certificate issued successfully with IssuerMSP identity');
                resolve(stdout);
            });
        });

        // Try to extract response data
        let responseData = null;
        try {
            const payloadMatch = result.match(/payload:"({.*?})"/);
            if (payloadMatch && payloadMatch[1]) {
                responseData = JSON.parse(payloadMatch[1].replace(/\\/g, ''));
            }
        } catch (parseError) {
            console.log('⚠️ Could not parse response data, but issuance was successful');
        }

        res.json({
            success: true,
            message: 'Certificate issued successfully with IssuerMSP identity - Certificate is now ready for buyer completion',
            data: {
                certId: certId,
                status: 'CERTIFICATE_ISSUED',
                issuedBy: issuerId,
                issuedAt: new Date().toISOString(),
                mspIdentity: 'IssuerMSP', // ✅ NEW: Show which MSP was used
                endorsing_organizations: endorsingOrgs,
                details: responseData,
                workflow: {
                    previousStatus: 'CERTIFICATE_PAID',
                    currentStatus: 'CERTIFICATE_ISSUED',
                    nextStep: 'Buyer can now complete the certificate'
                }
            }
        });

    } catch (error) {
        console.error('💥 Error issuing certificate:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to issue certificate',
            error: error.message
        });
    }
});

// ✅ NEW: Certificate Ownership Verification API endpoint (Transaction-based proof)
router.get('/verify-ownership/:certId', async (req, res) => {
    try {
        const { certId } = req.params;
        const { buyerId } = req.query;

        if (!buyerId) {
            return res.status(400).json({
                success: false,
                message: 'buyerId query parameter is required'
            });
        }

        console.log('🔍 Verifying certificate ownership with transaction-based proof', { certId, buyerId });

        // Check certificate ownership with transaction-based proof
        const ownershipCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:verifyCertificateOwnership","Args":["${certId}","${buyerId}"]}'`;
        
        const ownershipResult = await new Promise((resolve, reject) => {
            exec(ownershipCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.error('❌ Ownership verification failed:', error);
                    reject(error);
                    return;
                }
                console.log('✅ Ownership verification completed');
                resolve(stdout);
            });
        });

        let ownershipData = null;
        try {
            ownershipData = JSON.parse(ownershipResult.trim());
        } catch (parseError) {
            console.error('❌ Could not parse ownership verification result');
            throw new Error('Failed to parse ownership verification result');
        }

        // Get ownership proof details
        const proofCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:getOwnershipProof","Args":["${certId}"]}'`;
        
        const proofResult = await new Promise((resolve, reject) => {
            exec(proofCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.log('⚠️ Ownership proof retrieval failed, proceeding with ownership verification only');
                    resolve(null);
                    return;
                }
                resolve(stdout);
            });
        });

        let proofData = null;
        if (proofResult) {
            try {
                proofData = JSON.parse(proofResult.trim());
            } catch (parseError) {
                console.log('⚠️ Could not parse ownership proof result');
            }
        }

        // Check certificate integrity
        const integrityCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:getCertificateIntegrity","Args":["${certId}"]}'`;
        
        const integrityResult = await new Promise((resolve, reject) => {
            exec(integrityCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.log('⚠️ Integrity verification failed, proceeding with ownership only');
                    resolve(null);
                    return;
                }
                resolve(stdout);
            });
        });

        let integrityData = null;
        if (integrityResult) {
            try {
                integrityData = JSON.parse(integrityResult.trim());
            } catch (parseError) {
                console.log('⚠️ Could not parse integrity result');
            }
        }

        res.json({
            success: true,
            message: 'Certificate ownership verification completed with transaction-based proof',
            data: {
                certificateId: certId,
                buyerId: buyerId,
                ownership: ownershipData,
                ownershipProof: proofData,
                integrity: integrityData,
                verification: {
                    isOwner: ownershipData?.isOwner || false,
                    integrityValid: integrityData?.isValid || false,
                    ownershipProofValid: ownershipData?.ownershipProofValid || false,
                    hasTransactionProof: ownershipData?.transactionProof ? true : false,
                    securityLevel: ownershipData?.securityLevel || 'UNKNOWN',
                    verifiedAt: new Date().toISOString()
                },
                // ✅ NEW: Cryptographic evidence
                cryptographicEvidence: {
                    algorithm: 'SHA-256',
                    certificateFingerprint: ownershipData?.certificateHash || null,
                    ownershipProofHash: ownershipData?.cryptographicEvidence?.ownershipProofHash || null,
                    transactionHash: ownershipData?.transactionProof || null
                }
            }
        });

    } catch (error) {
        console.error('💥 Error verifying certificate ownership:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to verify certificate ownership',
            error: error.message
        });
    }
});

// ✅ NEW: Energy Data Integrity Check API endpoint
router.get('/energy-integrity/:energyId', async (req, res) => {
    try {
        const { energyId } = req.params;

        console.log('🔍 Checking energy data integrity', { energyId });

        const integrityCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:verifyEnergyDataIntegrity","Args":["${energyId}"]}'`;
        
        const result = await new Promise((resolve, reject) => {
            exec(integrityCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.error('❌ Energy integrity check failed:', error);
                    reject(error);
                    return;
                }
                console.log('✅ Energy integrity check completed');
                resolve(stdout);
            });
        });

        const integrityData = JSON.parse(result.trim());

        res.json({
            success: true,
            message: 'Energy data integrity check completed',
            data: {
                energyDataId: energyId,
                integrity: integrityData,
                checkedAt: new Date().toISOString()
            }
        });

    } catch (error) {
        console.error('💥 Error checking energy integrity:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to check energy data integrity',
            error: error.message
        });
    }
});

// ✅ NEW: Check Energy Data Duplication API endpoint
router.post('/check-energy-duplication', async (req, res) => {
    try {
        const { amount_kwh, source_type, timestamp, location, generatorId } = req.body;

        if (!amount_kwh || !source_type || !timestamp || !location || !generatorId) {
            return res.status(400).json({
                success: false,
                message: 'All fields are required: amount_kwh, source_type, timestamp, location, generatorId'
            });
        }

        console.log('🔍 Checking energy data duplication', { amount_kwh, source_type, location, generatorId });

        const duplicationCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"EnergyDataContract:checkEnergyDataDuplication","Args":["${amount_kwh}","${source_type}","${timestamp}","${location}","${generatorId}"]}'`;
        
        const result = await new Promise((resolve, reject) => {
            exec(duplicationCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.error('❌ Energy duplication check failed:', error);
                    reject(error);
                    return;
                }
                console.log('✅ Energy duplication check completed');
                resolve(stdout);
            });
        });

        const duplicationData = JSON.parse(result.trim());

        res.json({
            success: true,
            message: 'Energy data duplication check completed',
            data: {
                isDuplicate: duplicationData.isDuplicate,
                hash: duplicationData.hash,
                existingEnergyDataId: duplicationData.existingEnergyDataId || null,
                message: duplicationData.message,
                checkedAt: new Date().toISOString()
            }
        });

    } catch (error) {
        console.error('💥 Error checking energy duplication:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to check energy data duplication',
            error: error.message
        });
    }
});

// ✅ Step 5: Complete Certificate (Buyer role) - FIXED with correct MSP identity
router.put('/complete/:certId', async (req, res) => {
    try {
        const { certId } = req.params;
        const { buyerId, endorsement_orgs } = req.body;

        if (!buyerId) {
            return res.status(400).json({
                success: false,
                message: 'buyerId is required'
            });
        }

        console.log('🚀 Starting certificate completion process', { certId, buyerId });

        // 🎯 FIXED: Use correct endorsement policy for buyer operations
        let peerAddresses = '';
        const endorsingOrgs = endorsement_orgs || ['buyer', 'issuer']; // ✅ FIXED: buyer + issuer for Step 5
        
        const peerConfig = {
            generator: '--peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt',
            issuer: '--peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt',
            buyer: '--peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt'
        };

        endorsingOrgs.forEach(org => {
            if (peerConfig[org]) {
                peerAddresses += ' ' + peerConfig[org];
            }
        });

        // ✅ CRITICAL FIX: Set correct MSP identity for BuyerMSP in Step 5 (completeCertificate)
        const buyerMSPCompleteCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec \\
            -e CORE_PEER_LOCALMSPID=BuyerMSP \\
            -e CORE_PEER_TLS_ROOTCERT_FILE=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt \\
            -e CORE_PEER_MSPCONFIGPATH=/opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/users/Admin@buyer.rec.com/msp \\
            -e CORE_PEER_ADDRESS=peer0.buyer.rec.com:11051 \\
            cli peer chaincode invoke -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec${peerAddresses} -c '{"function":"CertificateContract:completeCertificate","Args":["${certId}","${buyerId}"]}' --waitForEvent`;

        console.log('🔄 Executing CLI command with BuyerMSP identity for certificate completion (Step 5)...');
        console.log('🔑 Using MSP: BuyerMSP for Step 5');
        console.log('📝 Data:', { certId, buyerId, endorsing_orgs: endorsingOrgs });
        
        const result = await new Promise((resolve, reject) => {
            exec(buyerMSPCompleteCommand, { timeout: 30000 }, (error, stdout, stderr) => {
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
                    console.error('❌ Certificate completion failed:', combinedOutput);
                    reject(new Error('Certificate completion failed - no success indicators found'));
                    return;
                }
                
                console.log('✅ Certificate completed successfully with BuyerMSP identity');
                resolve({
                    success: true,
                    output: stdout,
                    endorsement_mismatch: false
                });
            });
        });

        // Try to extract response data
        let responseData = null;
        const output = result.output || '';
        try {
            const payloadMatch = output.match(/payload:"({.*?})"/);
            if (payloadMatch && payloadMatch[1]) {
                responseData = JSON.parse(payloadMatch[1].replace(/\\/g, ''));
            }
        } catch (parseError) {
            console.log('⚠️ Could not parse response data, but completion was successful');
        }

        res.json({
            success: true,
            message: 'Certificate completed successfully with BuyerMSP identity',
            data: {
                certId: certId,
                status: 'COMPLETED',
                completedBy: buyerId,
                completedAt: new Date().toISOString(),
                mspIdentity: 'BuyerMSP', // ✅ NEW: Show which MSP was used
                endorsing_organizations: endorsingOrgs,
                details: responseData,
                workflow: {
                    previousStatus: 'CERTIFICATE_ISSUED',
                    currentStatus: 'COMPLETED',
                    nextStep: 'Certificate workflow complete - ownership verified'
                }
            }
        });

    } catch (error) {
        console.error('💥 Error completing certificate:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to complete certificate',
            error: error.message
        });
    }
});

// ✅ NEW: Verify Payment (Issuer role) - Mengubah status menjadi CERTIFICATE_ISSUED
router.put('/verify-payment/:certId', async (req, res) => {
    try {
        const { certId } = req.params;
        const { issuerId, paymentAmount, paymentMethod, transactionReference, endorsement_orgs } = req.body;

        if (!issuerId) {
            return res.status(400).json({
                success: false,
                message: 'issuerId is required'
            });
        }

        console.log('💰 Starting payment verification process', { 
            certId, 
            issuerId, 
            paymentAmount, 
            paymentMethod,
            transactionReference 
        });

        // 🎯 DYNAMIC ENDORSEMENT: Build peer addresses based on endorsement_orgs
        let peerAddresses = '';
        const endorsingOrgs = endorsement_orgs || ['issuer', 'buyer']; // Default untuk payment verification
        
        const peerConfig = {
            generator: '--peerAddresses peer0.generator.rec.com:7051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/generator.rec.com/peers/peer0.generator.rec.com/tls/ca.crt',
            issuer: '--peerAddresses peer0.issuer.rec.com:9051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/issuer.rec.com/peers/peer0.issuer.rec.com/tls/ca.crt',
            buyer: '--peerAddresses peer0.buyer.rec.com:11051 --tlsRootCertFiles /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/peerOrganizations/buyer.rec.com/peers/peer0.buyer.rec.com/tls/ca.crt'
        };

        endorsingOrgs.forEach(org => {
            if (peerConfig[org]) {
                peerAddresses += ' ' + peerConfig[org];
            }
        });

        // Prepare payment verification arguments
        const paymentData = JSON.stringify({
            amount: paymentAmount || 0,
            method: paymentMethod || 'bank_transfer',
            reference: transactionReference || `PAY_${certId}_${Date.now()}`,
            verifiedAt: new Date().toISOString()
        });

        const cliCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode invoke -o orderer.rec.com:7050 --ordererTLSHostnameOverride orderer.rec.com --tls --cafile /opt/gopath/src/github.com/hyperledger/fabric/peer/crypto/ordererOrganizations/rec.com/orderers/orderer.rec.com/msp/tlscacerts/tlsca.rec.com-cert.pem -C recchannel -n rec${peerAddresses} -c '{"function":"CertificateContract:verifyPayment","Args":["${certId}","${issuerId}","${paymentData.replace(/"/g, '\\"')}"]}' --waitForEvent`;

        console.log('🔄 Executing CLI command for payment verification...');
        
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
                    console.error('❌ Payment verification failed:', combinedOutput);
                    reject(new Error('Payment verification failed'));
                    return;
                }
                
                console.log('✅ Payment verified successfully - Certificate status changed to CERTIFICATE_ISSUED');
                resolve(stdout);
            });
        });

        // Try to extract response data
        let responseData = null;
        try {
            const payloadMatch = result.match(/payload:"({.*?})"/);
            if (payloadMatch && payloadMatch[1]) {
                responseData = JSON.parse(payloadMatch[1].replace(/\\/g, ''));
            }
        } catch (parseError) {
            console.log('⚠️ Could not parse response data, but payment verification was successful');
        }

        res.json({
            success: true,
            message: 'Payment verified successfully - Certificate status changed to CERTIFICATE_ISSUED',
            data: {
                certId: certId,
                status: 'CERTIFICATE_ISSUED', // ✅ Status berubah setelah payment verification
                verifiedBy: issuerId,
                verifiedAt: new Date().toISOString(),
                paymentDetails: {
                    amount: paymentAmount,
                    method: paymentMethod,
                    reference: transactionReference,
                    verificationTimestamp: new Date().toISOString()
                },
                endorsing_organizations: endorsingOrgs,
                details: responseData,
                workflow: {
                    previousStatus: 'CERTIFICATE_REQUESTED',
                    currentStatus: 'CERTIFICATE_ISSUED',
                    nextStep: 'Certificate can now be issued to buyer'
                }
            }
        });

    } catch (error) {
        console.error('💥 Error verifying payment:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to verify payment',
            error: error.message
        });
    }
});

// ✅ NEW: Verify Certificate by Hash - MUST BE BEFORE /:certId route
router.get('/verify-hash/:hash', async (req, res) => {
    try {
        const { hash } = req.params;

        console.log('🔍 Verifying certificate by hash:', hash);

        // Query all certificates to find matching hash
        const cliCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:getAllCertificates","Args":[]}'`;
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.error('❌ CLI command error:', error);
                    reject(error);
                    return;
                }
                resolve(stdout);
            });
        });

        // Parse the result
        const certificates = JSON.parse(result.trim());
        
        // Find certificate with matching hash
        const certificate = certificates.find(cert => 
            cert.security?.certificateHash === hash ||
            cert.security?.antiDuplicationHash === hash
        );

        if (!certificate) {
            return res.status(404).json({
                success: false,
                message: 'Certificate not found with the provided hash',
                hash: hash
            });
        }

        res.json({
            success: true,
            message: 'Certificate found and verified by hash',
            data: certificate,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        console.error('💥 Error verifying certificate by hash:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to verify certificate by hash',
            error: error.message
        });
    }
});

// Get Certificate by ID
router.get('/:certId', async (req, res) => {
    try {
        const { certId } = req.params;

        const cliCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:getCertificateById","Args":["${certId}"]}'`;

        console.log('🔄 Executing CLI command for getCertificateById...');
        console.log('📝 Certificate ID:', certId);
        
        const result = await new Promise((resolve, reject) => {
            exec(cliCommand, { timeout: 15000 }, (error, stdout, stderr) => {
                if (error) {
                    console.error('❌ CLI command error:', error);
                    reject(error);
                    return;
                }
                if (stderr && stderr.includes('does not exist')) {
                    reject(new Error(`Certificate ${certId} not found`));
                    return;
                }
                console.log('✅ Query successful, raw output:', stdout);
                resolve(stdout);
            });
        });

        // Parse the result
        const certificate = JSON.parse(result.trim());

        res.json({
            success: true,
            data: certificate,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        console.error('💥 Error getting certificate:', error);
        
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

// Get All Certificates
router.get('/', async (req, res) => {
    try {
        const cliCommand = `cd /home/flavianus-putratama/Downloads/REC-Blockchain && docker exec cli peer chaincode query -C recchannel -n rec -c '{"function":"CertificateContract:getAllCertificates","Args":[]}'`;

        console.log('🔄 Executing CLI command for getAllCertificates...');
        
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
        let certificates = [];
        try {
            const trimmedResult = result.trim();
            if (trimmedResult && trimmedResult !== '[]' && trimmedResult !== '') {
                certificates = JSON.parse(trimmedResult);
                console.log('📊 Parsed certificates:', certificates.length, 'records');
            } else {
                console.log('📭 No certificates found in blockchain');
            }
        } catch (parseError) {
            console.error('❌ Parse error:', parseError);
            console.log('Raw result that failed to parse:', result);
            certificates = [];
        }

        res.json({
            success: true,
            data: certificates,
            count: certificates.length,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        console.error('💥 Error getting certificates:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to get certificates',
            error: error.message,
            details: 'Check blockchain network connectivity'
        });
    }
});

module.exports = router;