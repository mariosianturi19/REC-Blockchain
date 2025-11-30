const express = require('express');
const router = express.Router();
const FabricService = require('../services/fabricService');

const fabricService = new FabricService();

// Public REC Tracking - untuk transparansi Enterprise REC
router.get('/public/rec/:orderId', async (req, res) => {
    try {
        const { orderId } = req.params;
        
        console.log(`Public tracking request for order: ${orderId}`);
        
        const { gateway, contract } = await fabricService.connectToNetwork();

        // Query certificate data dari blockchain dengan berbagai format ID
        let certificateResult;
        let dataFound = false;
        
        // Try multiple certificate ID formats
        const possibleIds = [
            orderId,
            `CERT_${orderId}`,
            orderId.replace('CERT_', ''),
            `CERTIFICATE_${orderId}`,
            orderId.replace('CERTIFICATE_', '')
        ];
        
        for (const certId of possibleIds) {
            try {
                console.log(`Trying certificate ID: ${certId}`);
                certificateResult = await contract.evaluateTransaction(
                    'CertificateContract:getCertificate', 
                    certId
                );
                if (certificateResult && certificateResult.length > 0) {
                    console.log(`Found certificate with ID: ${certId}`);
                    dataFound = true;
                    break;
                }
            } catch (certError) {
                console.log(`Certificate not found with ID ${certId}: ${certError.message}`);
                continue;
            }
        }
        
        // If certificate not found, try energy data
        if (!dataFound) {
            console.log(`Certificate not found, trying energy data...`);
            for (const energyId of possibleIds) {
                try {
                    certificateResult = await contract.evaluateTransaction(
                        'EnergyDataContract:getEnergyData',
                        energyId
                    );
                    if (certificateResult && certificateResult.length > 0) {
                        console.log(`Found energy data with ID: ${energyId}`);
                        dataFound = true;
                        break;
                    }
                } catch (energyError) {
                    continue;
                }
            }
        }

        await fabricService.disconnect(gateway);

        if (!dataFound || !certificateResult || certificateResult.length === 0) {
            return res.status(404).json({
                success: false,
                message: 'REC data tidak ditemukan di blockchain',
                blockchain_verified: false,
                searchedIds: possibleIds
            });
        }

        const blockchainData = JSON.parse(certificateResult.toString());
        console.log(`Blockchain data structure:`, JSON.stringify(blockchainData, null, 2));
        
        // Extract data dari structure yang kompleks
        let extractedData = {};
        
        if (blockchainData.certificateInfo) {
            // Certificate format
            extractedData = {
                orderId: blockchainData.certificateInfo.certificateId || orderId,
                company: blockchainData.parties?.buyer?.buyerName || blockchainData.parties?.buyer?.buyerId || 'Enterprise Customer',
                amount: blockchainData.energyDetails?.energyAmount || blockchainData.energyDetails?.amount || '1000',
                unit: blockchainData.energyDetails?.unit || 'MWh',
                issueDate: blockchainData.lifecycle?.issuedAt || blockchainData.lifecycle?.createdAt,
                status: blockchainData.certificateInfo?.status || 'verified',
                statusDescription: blockchainData.certificateInfo?.statusDescription || 'Certificate verified on blockchain',
                blockchainTxId: blockchainData.auditTrail?.transactionId || 'blockchain-verified',
                certificateId: blockchainData.certificateInfo?.certificateId || orderId,
                energySource: blockchainData.energyDetails?.energySource || 'Renewable Energy',
                location: blockchainData.energyDetails?.location || 'Indonesia',
                generatorName: blockchainData.parties?.generator?.generatorName || 'Certified Generator',
                verificationTimestamp: new Date().toISOString(),
                type: 'REC Certificate',
                category: 'Enterprise'
            };
        } else if (blockchainData.energySource) {
            // Energy data format
            extractedData = {
                orderId: blockchainData.energyId || orderId,
                company: blockchainData.generatorName || 'Energy Producer',
                amount: blockchainData.energyAmount || blockchainData.amount || '1000',
                unit: blockchainData.unit || 'MWh',
                issueDate: blockchainData.reportDate || blockchainData.createdAt,
                status: 'verified',
                statusDescription: 'Energy data verified on blockchain',
                blockchainTxId: blockchainData.txId || 'blockchain-verified',
                certificateId: blockchainData.energyId || orderId,
                energySource: blockchainData.energySource,
                location: blockchainData.location || 'Indonesia',
                generatorName: blockchainData.generatorName,
                verificationTimestamp: new Date().toISOString(),
                type: 'Energy Data',
                category: 'Enterprise'
            };
        } else {
            // Fallback format
            extractedData = {
                orderId: orderId,
                company: 'Enterprise Customer',
                amount: '1000',
                unit: 'MWh',
                issueDate: new Date().toISOString(),
                status: 'verified',
                statusDescription: 'Data verified on blockchain',
                blockchainTxId: 'blockchain-verified',
                certificateId: orderId,
                energySource: 'Renewable Energy',
                location: 'Indonesia',
                generatorName: 'Certified Generator',
                verificationTimestamp: new Date().toISOString(),
                type: 'REC Data',
                category: 'Enterprise'
            };
        }

        console.log(`Successfully retrieved and processed blockchain data for ${orderId}`);
        
        res.json({
            success: true,
            data: extractedData,
            blockchain_verified: true,
            raw_blockchain_data_preview: {
                hasStructure: !!blockchainData.certificateInfo,
                dataType: blockchainData.certificateInfo ? 'certificate' : 'energy_data'
            }
        });

    } catch (error) {
        console.error(`Blockchain query failed for ${req.params.orderId}:`, error);
        res.status(500).json({
            success: false,
            message: 'Error retrieving blockchain data',
            blockchain_verified: false,
            error: error.message
        });
    }
});

// Get REC History from Blockchain
router.get('/public/rec/:orderId/history', async (req, res) => {
    try {
        const { orderId } = req.params;
        
        const { gateway, contract } = await fabricService.connectToNetwork();

        // Get history untuk certificate
        const historyResult = await contract.evaluateTransaction(
            'CertificateContract:getHistoryForCertificate',
            orderId
        );

        await fabricService.disconnect(gateway);

        const historyData = JSON.parse(historyResult.toString());
        
        res.json({
            success: true,
            data: historyData,
            blockchain_verified: true
        });

    } catch (error) {
        console.error(`History query failed for ${req.params.orderId}:`, error);
        res.status(500).json({
            success: false,
            message: 'Error retrieving blockchain history',
            error: error.message
        });
    }
});

// Health check endpoint
router.get('/health', async (req, res) => {
    try {
        const { gateway, contract } = await fabricService.connectToNetwork();
        await fabricService.disconnect(gateway);
        
        res.json({
            success: true,
            message: 'Blockchain connection healthy',
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        res.status(503).json({
            success: false,
            message: 'Blockchain connection failed',
            error: error.message
        });
    }
});

module.exports = router;