'use strict';

const { Contract } = require('fabric-contract-api');

class BuyRequestContract extends Contract {
    constructor() {
        super('BuyRequestContract');
    }

    // 1. Buyer membuat buy request untuk REC yang sudah verified
    async createBuyRequest(ctx, requestId, energyDataId, buyerId, requestedAmount, pricePerKwh) {
        const requestKey = `BUY_REQUEST_${requestId}`;
        
        // Cek apakah request sudah ada
        const exists = await ctx.stub.getState(requestKey);
        if (exists && exists.length > 0) {
            throw new Error(`Buy request ${requestId} already exists`);
        }

        // Verifikasi energy data exists dan sudah verified
        const energyKey = `ENERGY_${energyDataId}`;
        const energyDataBytes = await ctx.stub.getState(energyKey);
        if (!energyDataBytes || energyDataBytes.length === 0) {
            throw new Error(`Energy data ${energyDataId} does not exist`);
        }

        const energyData = JSON.parse(energyDataBytes.toString());
        if (energyData.certificationStatus.status !== 'VERIFIED') {
            throw new Error(`Energy data ${energyDataId} has not been verified yet`);
        }

        // Cek apakah requested amount tidak melebihi available amount
        const requestedAmountFloat = parseFloat(requestedAmount);
        if (requestedAmountFloat > energyData.energyGeneration.amount) {
            throw new Error(`Requested amount (${requestedAmount} kWh) exceeds available amount (${energyData.energyGeneration.amount} kWh)`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const createdAt = new Date(txTimestamp.seconds * 1000).toISOString();
        const totalPrice = requestedAmountFloat * parseFloat(pricePerKwh);

        const buyRequest = {
            documentType: 'BUY_REQUEST',
            requestId: requestId,
            version: '1.0',
            
            // Request Details
            requestDetails: {
                energyDataId: energyDataId,
                requestedAmount: requestedAmountFloat,
                unit: 'kWh',
                pricePerKwh: parseFloat(pricePerKwh),
                totalPrice: totalPrice,
                currency: 'USD'
            },
            
            // Parties Involved
            parties: {
                buyer: {
                    buyerId: buyerId,
                    organizationType: 'ENERGY_BUYER'
                },
                generator: {
                    generatorId: energyData.generator.generatorId,
                    organizationType: 'RENEWABLE_ENERGY_GENERATOR'
                }
            },
            
            // Request Status
            requestStatus: {
                status: 'PENDING_PAYMENT',
                statusDescription: 'Buy request created, awaiting payment',
                createdAt: createdAt,
                paidAt: null,
                verifiedAt: null,
                completedAt: null
            },
            
            // Payment Information
            paymentInfo: {
                paymentStatus: 'PENDING',
                paymentMethod: null,
                paymentReference: null,
                paymentVerifiedBy: null
            },
            
            // Audit Trail
            auditTrail: {
                createdAt: createdAt,
                createdBy: buyerId,
                lastModified: createdAt,
                transactionId: ctx.stub.getTxID()
            }
        };

        await ctx.stub.putState(requestKey, Buffer.from(JSON.stringify(buyRequest)));
        return buyRequest;
    }

    // 2. Buyer melakukan payment
    async makePayment(ctx, requestId, paymentMethod, paymentReference) {
        const requestKey = `BUY_REQUEST_${requestId}`;
        const requestBytes = await ctx.stub.getState(requestKey);
        
        if (!requestBytes || requestBytes.length === 0) {
            throw new Error(`Buy request ${requestId} does not exist`);
        }

        const buyRequest = JSON.parse(requestBytes.toString());
        
        if (buyRequest.requestStatus.status !== 'PENDING_PAYMENT') {
            throw new Error(`Buy request ${requestId} is not in pending payment status`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const paidAt = new Date(txTimestamp.seconds * 1000).toISOString();

        // Update payment info
        buyRequest.paymentInfo = {
            paymentStatus: 'PAID',
            paymentMethod: paymentMethod,
            paymentReference: paymentReference,
            paymentVerifiedBy: null,
            paidAt: paidAt
        };

        buyRequest.requestStatus = {
            ...buyRequest.requestStatus,
            status: 'PAYMENT_VERIFICATION',
            statusDescription: 'Payment made, awaiting verification by issuer',
            paidAt: paidAt
        };

        buyRequest.auditTrail.lastModified = paidAt;
        buyRequest.auditTrail.paymentTxId = ctx.stub.getTxID();

        await ctx.stub.putState(requestKey, Buffer.from(JSON.stringify(buyRequest)));
        return buyRequest;
    }

    // 3. Issuer memverifikasi pembayaran
    async verifyPayment(ctx, requestId, issuerId, verificationNotes = '') {
        const requestKey = `BUY_REQUEST_${requestId}`;
        const requestBytes = await ctx.stub.getState(requestKey);
        
        if (!requestBytes || requestBytes.length === 0) {
            throw new Error(`Buy request ${requestId} does not exist`);
        }

        const buyRequest = JSON.parse(requestBytes.toString());
        
        if (buyRequest.requestStatus.status !== 'PAYMENT_VERIFICATION') {
            throw new Error(`Buy request ${requestId} is not in payment verification status`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const verifiedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        // Update payment verification
        buyRequest.paymentInfo.paymentVerifiedBy = issuerId;
        buyRequest.paymentInfo.verificationNotes = verificationNotes;

        buyRequest.requestStatus = {
            ...buyRequest.requestStatus,
            status: 'VERIFIED',
            statusDescription: 'Payment verified, ready for certificate issuance',
            verifiedAt: verifiedAt
        };

        buyRequest.auditTrail.lastModified = verifiedAt;
        buyRequest.auditTrail.verificationTxId = ctx.stub.getTxID();

        await ctx.stub.putState(requestKey, Buffer.from(JSON.stringify(buyRequest)));
        return buyRequest;
    }

    // 4. Complete buy request (setelah certificate issued)
    async completeBuyRequest(ctx, requestId, certificateId) {
        const requestKey = `BUY_REQUEST_${requestId}`;
        const requestBytes = await ctx.stub.getState(requestKey);
        
        if (!requestBytes || requestBytes.length === 0) {
            throw new Error(`Buy request ${requestId} does not exist`);
        }

        const buyRequest = JSON.parse(requestBytes.toString());
        
        if (buyRequest.requestStatus.status !== 'VERIFIED') {
            throw new Error(`Buy request ${requestId} is not in verified status`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const completedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        buyRequest.requestStatus = {
            ...buyRequest.requestStatus,
            status: 'COMPLETED',
            statusDescription: 'Buy request completed, certificate issued',
            completedAt: completedAt
        };

        buyRequest.certificateId = certificateId;
        buyRequest.auditTrail.lastModified = completedAt;
        buyRequest.auditTrail.completionTxId = ctx.stub.getTxID();

        await ctx.stub.putState(requestKey, Buffer.from(JSON.stringify(buyRequest)));
        return buyRequest;
    }

    // Query methods
    async getBuyRequestById(ctx, requestId) {
        const requestKey = `BUY_REQUEST_${requestId}`;
        const requestBytes = await ctx.stub.getState(requestKey);
        
        if (!requestBytes || requestBytes.length === 0) {
            throw new Error(`Buy request ${requestId} does not exist`);
        }
        
        return JSON.parse(requestBytes.toString());
    }

    async getAllBuyRequests(ctx) {
        const iterator = await ctx.stub.getStateByRange('BUY_REQUEST_', 'BUY_REQUEST_~');
        const results = [];

        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString('utf8'));
                    if (record.documentType === 'BUY_REQUEST') {
                        results.push(record);
                    }
                } catch (err) {
                    console.error('Error parsing buy request data:', err);
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
        return results;
    }

    async getBuyRequestsByStatus(ctx, status) {
        const allRequests = await this.getAllBuyRequests(ctx);
        return allRequests.filter(request => request.requestStatus.status === status);
    }

    async getBuyRequestsByBuyer(ctx, buyerId) {
        const allRequests = await this.getAllBuyRequests(ctx);
        return allRequests.filter(request => request.parties.buyer.buyerId === buyerId);
    }

    async getHistoryForBuyRequest(ctx, requestId) {
        const requestKey = `BUY_REQUEST_${requestId}`;
        const iterator = await ctx.stub.getHistoryForKey(requestKey);
        const results = [];

        while (true) {
            const res = await iterator.next();
            if (res.value) {
                const txId = res.value.tx_id;
                const timestamp = new Date(res.value.timestamp.seconds * 1000).toISOString();
                const record = res.value.value.toString('utf8');

                results.push({
                    txId,
                    timestamp,
                    data: record ? JSON.parse(record) : null
                });
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
        return results;
    }
}

module.exports = BuyRequestContract;