const axios = require('axios');

class WebhookService {
    constructor() {
        // Laravel webhook endpoint
        this.webhookUrl = process.env.LARAVEL_WEBHOOK_URL || 'http://localhost:8000/api/webhook/blockchain-sync';
        this.webhookSecret = process.env.WEBHOOK_SECRET || 'blockchain-sync-secret';
        this.enabled = process.env.WEBHOOK_ENABLED !== 'false';
    }

    async notifyEnergyDataSubmitted(energyData) {
        if (!this.enabled) return;
        
        try {
            await this.sendWebhook('energy_data_submitted', {
                energyDataId: energyData.energyDataId,
                generatorId: energyData.generatorId,
                energyAmount: energyData.energyAmount,
                generationDate: energyData.generationDate,
                location: energyData.location,
                energySource: energyData.energySource,
                status: 'Submitted',
                timestamp: new Date().toISOString()
            });
            console.log('✅ Webhook sent: energy_data_submitted');
        } catch (error) {
            console.error('❌ Webhook failed for energy_data_submitted:', error.message);
        }
    }

    async notifyEnergyDataVerified(energyDataId, verificationData) {
        if (!this.enabled) return;
        
        try {
            await this.sendWebhook('energy_data_verified', {
                energyDataId,
                issuerId: verificationData.issuerId,
                verificationNotes: verificationData.verificationNotes,
                status: 'Verified',
                timestamp: new Date().toISOString()
            });
            console.log('✅ Webhook sent: energy_data_verified');
        } catch (error) {
            console.error('❌ Webhook failed for energy_data_verified:', error.message);
        }
    }

    async notifyCertificateRequested(certificateData) {
        if (!this.enabled) return;
        
        try {
            await this.sendWebhook('certificate_requested', {
                certificateId: certificateData.certificateId,
                generatorId: certificateData.generatorId,
                energyDataId: certificateData.energyDataId,
                requestedAmount: certificateData.requestedAmount,
                status: 'Requested',
                timestamp: new Date().toISOString()
            });
            console.log('✅ Webhook sent: certificate_requested');
        } catch (error) {
            console.error('❌ Webhook failed for certificate_requested:', error.message);
        }
    }

    async notifyCertificateIssued(certificateId, issueData) {
        if (!this.enabled) return;
        
        try {
            await this.sendWebhook('certificate_issued', {
                certificateId,
                issuerId: issueData.issuerId,
                issueDate: issueData.issueDate,
                expiryDate: issueData.expiryDate,
                status: 'Issued',
                timestamp: new Date().toISOString()
            });
            console.log('✅ Webhook sent: certificate_issued');
        } catch (error) {
            console.error('❌ Webhook failed for certificate_issued:', error.message);
        }
    }

    async notifyPurchaseRequested(purchaseData) {
        if (!this.enabled) return;
        
        try {
            await this.sendWebhook('purchase_requested', {
                certificateId: purchaseData.certificateId,
                buyerId: purchaseData.buyerId,
                purchaseAmount: purchaseData.purchaseAmount,
                requestDate: purchaseData.requestDate,
                status: 'Purchase Requested',
                timestamp: new Date().toISOString()
            });
            console.log('✅ Webhook sent: purchase_requested');
        } catch (error) {
            console.error('❌ Webhook failed for purchase_requested:', error.message);
        }
    }

    async notifyPurchaseConfirmed(certificateId, confirmData) {
        if (!this.enabled) return;
        
        try {
            await this.sendWebhook('purchase_confirmed', {
                certificateId,
                issuerId: confirmData.issuerId,
                purchaseDate: confirmData.purchaseDate,
                purchasePrice: confirmData.purchasePrice,
                status: 'Purchased',
                timestamp: new Date().toISOString()
            });
            console.log('✅ Webhook sent: purchase_confirmed');
        } catch (error) {
            console.error('❌ Webhook failed for purchase_confirmed:', error.message);
        }
    }

    async sendWebhook(eventType, data) {
        const payload = {
            event: eventType,
            data: data,
            timestamp: new Date().toISOString(),
            source: 'blockchain-api'
        };

        const response = await axios.post(this.webhookUrl, payload, {
            headers: {
                'Content-Type': 'application/json',
                'X-Webhook-Secret': this.webhookSecret,
                'User-Agent': 'REC-Blockchain-API/1.0'
            },
            timeout: 10000
        });

        if (response.status !== 200) {
            throw new Error(`Webhook failed with status: ${response.status}`);
        }

        return response.data;
    }

    // Batch sync untuk data yang mungkin terlewat
    async syncAllData() {
        if (!this.enabled) return;
        
        try {
            await this.sendWebhook('full_sync_requested', {
                timestamp: new Date().toISOString(),
                message: 'Full blockchain sync requested'
            });
            console.log('✅ Full sync webhook sent');
        } catch (error) {
            console.error('❌ Full sync webhook failed:', error.message);
        }
    }
}

module.exports = new WebhookService();