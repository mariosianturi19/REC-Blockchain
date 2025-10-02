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

        // Query certificate data dari blockchain
        let certificateResult;
        try {
            certificateResult = await contract.evaluateTransaction(
                'CertificateContract:getCertificate', 
                orderId
            );
        } catch (certError) {
            console.log(`Certificate not found for ${orderId}, trying energy data...`);
            // Fallback ke energy data jika certificate tidak ada
            certificateResult = await contract.evaluateTransaction(
                'EnergyDataContract:getEnergyData',
                orderId
            );
        }

        await fabricService.disconnect(gateway);

        if (!certificateResult || certificateResult.length === 0) {
            return res.status(404).json({
                success: false,
                message: 'REC data tidak ditemukan di blockchain'
            });
        }

        const blockchainData = JSON.parse(certificateResult.toString());
        
        // Hanya expose data public untuk kategori Enterprise
        if (blockchainData.category === 'Enterprise' || blockchainData.isPublic) {
            const publicData = {
                orderId: blockchainData.orderId || blockchainData.id,
                company: blockchainData.buyerCompany || blockchainData.generatorName,
                amount: blockchainData.amount || blockchainData.energyAmount,
                issueDate: blockchainData.issueDate || blockchainData.reportDate,
                status: blockchainData.status || 'verified',
                blockchainTxId: blockchainData.txId || 'blockchain-verified',
                certificateId: blockchainData.certificateId || blockchainData.id,
                verificationTimestamp: new Date().toISOString(),
                type: blockchainData.type || 'REC'
            };

            console.log(`Successfully retrieved blockchain data for ${orderId}`);
            
            res.json({
                success: true,
                data: publicData,
                blockchain_verified: true
            });
        } else {
            res.status(403).json({
                success: false,
                message: 'REC ini tidak tersedia untuk tracking publik'
            });
        }

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