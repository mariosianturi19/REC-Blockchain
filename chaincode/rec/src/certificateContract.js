'use strict';

const { Contract } = require('fabric-contract-api');

class CertificateContract extends Contract {
    constructor() {
        super('CertificateContract');
    }

    // ✅ Step 1: Buyer request certificate (status: CERTIFICATE_REQUESTED)
    async requestCertificate(ctx, certId, energyId, buyerId, securityDataStr) {
        const certKey = `CERTIFICATE_${certId}`;
        const exists = await ctx.stub.getState(certKey);
        if (exists && exists.length > 0) {
            throw new Error(`Certificate ${certId} already exists`);
        }

        // Cek apakah energy data sudah VERIFIED
        const energyKey = `ENERGY_${energyId}`;
        const energyDataBytes = await ctx.stub.getState(energyKey);
        if (!energyDataBytes || energyDataBytes.length === 0) {
            throw new Error(`EnergyData ${energyId} does not exist`);
        }

        const energyData = JSON.parse(energyDataBytes.toString());
        if (energyData.status !== 'VERIFIED') {
            throw new Error(`EnergyData ${energyId} must be VERIFIED before certificate request`);
        }

        // Parse security data
        let securityData = {};
        try {
            if (securityDataStr) {
                securityData = JSON.parse(securityDataStr);
            }
        } catch (e) {
            console.log('⚠️ Invalid security data format, using defaults');
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const requestedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        // Create certificate dengan format yang benar
        const certificate = {
            documentType: 'RENEWABLE_ENERGY_CERTIFICATE',
            certificateId: certId,
            version: '1.0',
            
            certificateInfo: {
                status: 'CERTIFICATE_REQUESTED',
                statusDescription: 'Certificate request submitted by buyer',
                amount: energyData.amount,
                unit: energyData.unit || 'MWh',
                sourceType: energyData.sourceType || 'SOLAR',
                location: energyData.location || 'Indonesia'
            },
            
            parties: {
                generator: {
                    generatorId: energyData.generatorId,
                    role: 'GENERATOR'
                },
                issuer: {
                    issuerId: 'ISSUER001',
                    role: 'ISSUER'
                },
                buyer: {
                    buyerId: buyerId,
                    role: 'BUYER'
                }
            },
            
            security: {
                certificate_hash: securityData.certificate_hash,
                serial_number: securityData.serial_number,
                security_level: securityData.security_level || 'HIGH'
            },

            auditTrail: {
                createdAt: requestedAt,
                createdBy: buyerId,
                lastModified: requestedAt,
                transactionId: ctx.stub.getTxID()
            }
        };

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        
        console.log('✅ Certificate created with status CERTIFICATE_REQUESTED:', {
            certificateId: certId,
            buyerId: buyerId,
            security: certificate.security
        });

        return certificate;
    }

    // ✅ Step 2: Buyer confirm payment (status: CERTIFICATE_PAID)
    async confirmPayment(ctx, certId, buyerId) {
        const certKey = `CERTIFICATE_${certId}`;
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }

        const certificate = JSON.parse(certBytes.toString());
        
        if (certificate.certificateInfo.status !== 'CERTIFICATE_REQUESTED') {
            throw new Error(`Certificate ${certId} must be in CERTIFICATE_REQUESTED status`);
        }

        if (certificate.parties.buyer.buyerId !== buyerId) {
            throw new Error(`Only the buyer can confirm payment`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const paidAt = new Date(txTimestamp.seconds * 1000).toISOString();

        certificate.certificateInfo.status = 'CERTIFICATE_PAID';
        certificate.certificateInfo.statusDescription = 'Payment confirmed by buyer';
        certificate.auditTrail.lastModified = paidAt;
        certificate.auditTrail.paymentTxId = ctx.stub.getTxID();

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        
        console.log('✅ Certificate payment confirmed:', {
            certificateId: certId,
            status: 'CERTIFICATE_PAID',
            paidAt: paidAt
        });

        return certificate;
    }

    // ✅ Step 3: Issuer verifies and issues certificate (status: CERTIFICATE_ISSUED)
    async issueCertificate(ctx, certId, issuerId) {
        const certKey = `CERTIFICATE_${certId}`;
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }

        const certificate = JSON.parse(certBytes.toString());
        
        if (certificate.certificateInfo.status !== 'CERTIFICATE_PAID') {
            throw new Error(`Certificate ${certId} must be CERTIFICATE_PAID before issuance`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const issuedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        certificate.certificateInfo.status = 'CERTIFICATE_ISSUED';
        certificate.certificateInfo.statusDescription = 'Certificate issued by authorized issuer';
        certificate.parties.issuer.issuerId = issuerId;
        certificate.auditTrail.lastModified = issuedAt;
        certificate.auditTrail.issuanceTxId = ctx.stub.getTxID();

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        
        console.log('✅ Certificate issued:', {
            certificateId: certId,
            status: 'CERTIFICATE_ISSUED',
            issuedAt: issuedAt
        });

        return certificate;
    }

    // ✅ Step 4: Buyer completes certificate (status: COMPLETED)
    async completeCertificate(ctx, certId, buyerId) {
        const certKey = `CERTIFICATE_${certId}`;
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }

        const certificate = JSON.parse(certBytes.toString());
        
        if (certificate.certificateInfo.status !== 'CERTIFICATE_ISSUED') {
            throw new Error(`Certificate ${certId} must be CERTIFICATE_ISSUED before completion`);
        }

        if (certificate.parties.buyer.buyerId !== buyerId) {
            throw new Error(`Only the buyer can complete this certificate`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const completedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        certificate.certificateInfo.status = 'COMPLETED';
        certificate.certificateInfo.statusDescription = 'Certificate completed by buyer';
        certificate.auditTrail.lastModified = completedAt;
        certificate.auditTrail.completionTxId = ctx.stub.getTxID();

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        
        console.log('✅ Certificate completed:', {
            certificateId: certId,
            status: 'COMPLETED',
            completedAt: completedAt
        });

        return certificate;
    }

    // Query functions
    async getCertificateById(ctx, certId) {
        const certKey = `CERTIFICATE_${certId}`;
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }
        return JSON.parse(certBytes.toString());
    }

    async getAllCertificates(ctx) {
        const iterator = await ctx.stub.getStateByRange('', '');
        const results = [];
        
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                let record;
                try {
                    record = JSON.parse(res.value.value.toString('utf8'));
                    results.push(record);
                } catch (err) {
                    console.log(err);
                    record = res.value.value.toString('utf8');
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
        return results;
    }

    async getCertificatesByStatus(ctx, status) {
        const iterator = await ctx.stub.getStateByRange('', '');
        const results = [];
        
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString('utf8'));
                    if (record.certificateInfo && record.certificateInfo.status === status) {
                        results.push(record);
                    }
                } catch (err) {
                    console.log(err);
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
        return results;
    }
}

module.exports = CertificateContract;
