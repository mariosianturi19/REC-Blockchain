const express = require('express');
const router = express.Router();
const FabricService = require('../services/fabricService');

const fabricService = new FabricService();

// Log Audit Entry
router.post('/log', async (req, res) => {
    try {
        const { auditId, action, entityType, entityId, userId, timestamp, details } = req.body;

        if (!auditId || !action || !entityType || !entityId || !userId || !timestamp) {
            return res.status(400).json({
                success: false,
                message: 'All fields are required: auditId, action, entityType, entityId, userId, timestamp'
            });
        }

        const { gateway, contract } = await fabricService.connectToNetwork();

        const result = await contract.submitTransaction(
            'AuditContract:logAuditEntry',
            auditId,
            action,
            entityType,
            entityId,
            userId,
            timestamp,
            details || ''
        );

        await fabricService.disconnect(gateway);

        res.status(201).json({
            success: true,
            message: 'Audit entry logged successfully',
            data: JSON.parse(result.toString())
        });

    } catch (error) {
        console.error('Error logging audit entry:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to log audit entry',
            error: error.message
        });
    }
});

// Get Audit Entry by ID
router.get('/:auditId', async (req, res) => {
    try {
        const { auditId } = req.params;

        const { gateway, contract } = await fabricService.connectToNetwork();

        const result = await contract.evaluateTransaction(
            'AuditContract:getAuditEntry',
            auditId
        );

        await fabricService.disconnect(gateway);

        const auditEntry = JSON.parse(result.toString());

        res.json({
            success: true,
            data: auditEntry
        });

    } catch (error) {
        console.error('Error getting audit entry:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to get audit entry',
            error: error.message
        });
    }
});

// Get Audit Entries by Entity
router.get('/entity/:entityType/:entityId', async (req, res) => {
    try {
        const { entityType, entityId } = req.params;

        const { gateway, contract } = await fabricService.connectToNetwork();

        const result = await contract.evaluateTransaction(
            'AuditContract:getAuditEntriesByEntity',
            entityType,
            entityId
        );

        await fabricService.disconnect(gateway);

        const auditEntries = JSON.parse(result.toString());

        res.json({
            success: true,
            data: auditEntries
        });

    } catch (error) {
        console.error('Error getting audit entries by entity:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to get audit entries',
            error: error.message
        });
    }
});

// Get Audit Entries by User
router.get('/user/:userId', async (req, res) => {
    try {
        const { userId } = req.params;

        const { gateway, contract } = await fabricService.connectToNetwork();

        const result = await contract.evaluateTransaction(
            'AuditContract:getAuditEntriesByUser',
            userId
        );

        await fabricService.disconnect(gateway);

        const auditEntries = JSON.parse(result.toString());

        res.json({
            success: true,
            data: auditEntries
        });

    } catch (error) {
        console.error('Error getting audit entries by user:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to get audit entries',
            error: error.message
        });
    }
});

// Get All Audit Entries
router.get('/', async (req, res) => {
    try {
        const { gateway, contract } = await fabricService.connectToNetwork();

        const result = await contract.evaluateTransaction(
            'AuditContract:getAllAuditEntries'
        );

        await fabricService.disconnect(gateway);

        const auditEntries = JSON.parse(result.toString());

        res.json({
            success: true,
            data: auditEntries
        });

    } catch (error) {
        console.error('Error getting all audit entries:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to get audit entries',
            error: error.message
        });
    }
});

module.exports = router;