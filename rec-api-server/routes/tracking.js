const express = require('express');
const router = express.Router();
const { Gateway, Wallets } = require('fabric-network');
const path = require('path');

// Public tracking endpoint - tidak perlu autentikasi
router.get('/public/:transactionId', async (req, res) => {
    try {
        const { transactionId } = req.params;
        
        if (!transactionId) {
            return res.status(400).json({
                success: false,
                error: 'Transaction ID is required'
            });
        }

        // Connect to gateway
        const gateway = new Gateway();
        const walletPath = path.join(__dirname, '..', 'wallets');
        const wallet = await Wallets.newFileSystemWallet(walletPath);

        // Check if admin user exists in wallet
        const adminExists = await wallet.get('admin');
        if (!adminExists) {
            return res.status(500).json({
                success: false,
                error: 'Admin user not found in wallet'
            });
        }

        const connectionProfile = require('../config/connection-profile.json');
        const connectionOptions = {
            wallet,
            identity: 'admin',
            discovery: { enabled: true, asLocalhost: true }
        };

        await gateway.connect(connectionProfile, connectionOptions);
        const network = await gateway.getNetwork('recchannel');
        const contract = network.getContract('rec');

        // Query transaction by ID
        const result = await contract.evaluateTransaction('GetRECTransaction', transactionId);
        const transaction = JSON.parse(result.toString());

        // Get transaction history/audit trail
        const historyResult = await contract.evaluateTransaction('GetRECHistory', transactionId);
        const history = JSON.parse(historyResult.toString());

        await gateway.disconnect();

        res.json({
            success: true,
            data: {
                transaction,
                history,
                blockchain_verified: true,
                verification_time: new Date().toISOString()
            }
        });

    } catch (error) {
        console.error('Public tracking error:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to track transaction',
            details: error.message
        });
    }
});

// Get company transactions
router.get('/company/:companyName', async (req, res) => {
    try {
        const { companyName } = req.params;
        
        if (!companyName) {
            return res.status(400).json({
                success: false,
                error: 'Company name is required'
            });
        }

        const gateway = new Gateway();
        const walletPath = path.join(__dirname, '..', 'wallets');
        const wallet = await Wallets.newFileSystemWallet(walletPath);

        const adminExists = await wallet.get('admin');
        if (!adminExists) {
            return res.status(500).json({
                success: false,
                error: 'Admin user not found in wallet'
            });
        }

        const connectionProfile = require('../config/connection-profile.json');
        const connectionOptions = {
            wallet,
            identity: 'admin',
            discovery: { enabled: true, asLocalhost: true }
        };

        await gateway.connect(connectionProfile, connectionOptions);
        const network = await gateway.getNetwork('recchannel');
        const contract = network.getContract('rec');

        // Query transactions by company
        const result = await contract.evaluateTransaction('GetRECTransactionsByCompany', companyName);
        const transactions = JSON.parse(result.toString());

        await gateway.disconnect();

        res.json({
            success: true,
            data: {
                company: companyName,
                transactions,
                total_transactions: transactions.length,
                blockchain_verified: true
            }
        });

    } catch (error) {
        console.error('Company tracking error:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to get company transactions',
            details: error.message
        });
    }
});

// Verify transaction authenticity
router.get('/verify/:transactionId', async (req, res) => {
    try {
        const { transactionId } = req.params;
        
        const gateway = new Gateway();
        const walletPath = path.join(__dirname, '..', 'wallets');
        const wallet = await Wallets.newFileSystemWallet(walletPath);

        const adminExists = await wallet.get('admin');
        if (!adminExists) {
            return res.status(500).json({
                success: false,
                error: 'Admin user not found in wallet'
            });
        }

        const connectionProfile = require('../config/connection-profile.json');
        const connectionOptions = {
            wallet,
            identity: 'admin',
            discovery: { enabled: true, asLocalhost: true }
        };

        await gateway.connect(connectionProfile, connectionOptions);
        const network = await gateway.getNetwork('recchannel');
        const contract = network.getContract('rec');

        // Verify transaction exists and get metadata
        const result = await contract.evaluateTransaction('VerifyRECTransaction', transactionId);
        const verification = JSON.parse(result.toString());

        await gateway.disconnect();

        res.json({
            success: true,
            data: verification
        });

    } catch (error) {
        console.error('Verification error:', error);
        res.status(404).json({
            success: false,
            verified: false,
            error: 'Transaction not found or invalid'
        });
    }
});

module.exports = router;