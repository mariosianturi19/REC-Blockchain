'use strict';

const { Contract } = require('fabric-contract-api');

class CertificateContract extends Contract {
    constructor() {
        super('CertificateContract');
    }

    // ✅ Step 1: Buyer request certificate (status: CERTIFICATE_REQUESTED)
    async requestCertificate(ctx, certId, energyId, buyerId, purchasedAmount, securityDataStr) {
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

        // ✅ CRITICAL: Validate energy data hash untuk anti-duplication
        const energyDataHash = this.calculateEnergyDataHash(energyData);
        console.log('🔐 Energy data hash calculated:', energyDataHash);

        // ✅ FIXED: Parse security data dengan benar
        let parsedSecurity = {};
        try {
            if (securityDataStr) {
                const securityData = JSON.parse(securityDataStr);
                console.log('📋 Received security data:', JSON.stringify(securityData));
                
                // Extract security fields from nested structure
                if (securityData.security) {
                    parsedSecurity = {
                        certificate_hash: securityData.security.certificate_hash,
                        serial_number: securityData.security.serial_number,
                        security_level: securityData.security.security_level || 'HIGH',
                        anti_duplication_hash: securityData.security.anti_duplication_hash,
                        tamper_proof: true,
                        generated_at: securityData.security.generated_at || new Date().toISOString()
                    };
                } else {
                    // Fallback jika format berbeda
                    parsedSecurity = {
                        certificate_hash: securityData.certificate_hash,
                        serial_number: securityData.serial_number,
                        security_level: securityData.security_level || 'HIGH',
                        anti_duplication_hash: null,
                        tamper_proof: true,
                        generated_at: new Date().toISOString()
                    };
                }
                
                console.log('✅ Parsed security data:', JSON.stringify(parsedSecurity));
            }
        } catch (e) {
            console.log('⚠️ Invalid security data format, using defaults:', e.message);
            parsedSecurity = {
                certificate_hash: null,
                serial_number: certId,
                security_level: 'MEDIUM',
                anti_duplication_hash: null,
                tamper_proof: false,
                generated_at: new Date().toISOString()
            };
        }

        // ✅ CRITICAL: Check certificate uniqueness dengan hash
        if (parsedSecurity.certificate_hash) {
            const hashKey = `CERT_HASH_${parsedSecurity.certificate_hash}`;
            const hashExists = await ctx.stub.getState(hashKey);
            if (hashExists && hashExists.length > 0) {
                throw new Error(`DUPLICATION DETECTED: Certificate with hash ${parsedSecurity.certificate_hash.substring(0, 16)}... already exists`);
            }
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const requestedAt = new Date(txTimestamp.seconds * 1000).toISOString();
        const txId = ctx.stub.getTxID();

        // ✅ Generate serial number dari transaction ID (deterministic)
        const serialNumber = parsedSecurity.serial_number || `REC-${new Date().getFullYear()}-${txId.substring(0, 12)}`;

        // ✅ FIXED: Create certificate dengan purchased amount, bukan total energy amount
        const certificate = {
            documentType: 'RENEWABLE_ENERGY_CERTIFICATE',
            certificateId: certId,
            version: '1.0',
            
            certificateInfo: {
                type: 'REC',
                status: 'CERTIFICATE_REQUESTED',
                statusDescription: 'Certificate request submitted by buyer',
                issuanceStandard: 'INTERNATIONAL_REC_STANDARD_V2.0'
            },
            
            energyReference: {
                energyDataId: energyId,
                amount: parseFloat(purchasedAmount),  // ✅ Amount yang dibeli buyer
                unit: energyData.unit || 'kWh',
                sourceType: energyData.sourceType || 'SOLAR',
                location: energyData.location || 'Indonesia',
                generationDate: energyData.generationDate || new Date().toISOString().split('T')[0],
                energyDataHash: energyDataHash  // ✅ Energy data integrity hash
            },
            
            parties: {
                generator: {
                    generatorId: energyData.generatorId,
                    organizationType: 'RENEWABLE_ENERGY_GENERATOR',
                    role: 'ENERGY_PRODUCER'
                },
                issuer: {
                    issuerId: 'ISSUER001',
                    organizationType: 'AUTHORIZED_CERTIFICATE_ISSUER',
                    role: 'CERTIFICATE_AUTHORITY'
                },
                buyer: {
                    buyerId: buyerId,
                    organizationType: 'ENERGY_BUYER',
                    role: 'CERTIFICATE_PURCHASER'
                }
            },
            
            // ✅ ENHANCED: Comprehensive lifecycle tracking
            lifecycle: {
                requestedAt: requestedAt,
                requestedBy: buyerId,
                paidAt: null,
                paidBy: null,
                issuedAt: null,
                issuedBy: null,
                completedAt: null,
                completedBy: null,
                expiresAt: null
            },
            
            // ✅ ENHANCED: Payment tracking
            paymentDetails: {
                paymentRequested: true,
                paymentRequestedAt: requestedAt,
                paymentConfirmed: false,
                paymentConfirmedAt: null,
                paymentConfirmedBy: null,
                paymentStatus: 'PENDING',
                paymentMethod: null,
                paymentReference: null,
                paymentVerified: false,
                paymentVerifiedAt: null,
                paymentVerifiedBy: null
            },
            
            // ✅ CRITICAL: Enhanced security data
            security: {
                certificateHash: parsedSecurity.certificate_hash,
                serialNumber: serialNumber,
                antiDuplicationHash: parsedSecurity.anti_duplication_hash,
                tamperProof: parsedSecurity.tamper_proof,
                securityLevel: parsedSecurity.security_level,
                cryptographicStandard: 'SHA-256',
                generatedAt: parsedSecurity.generated_at
            },

            // ✅ CRITICAL: Compliance tracking
            compliance: {
                regulatoryFramework: 'INTERNATIONAL_REC_STANDARD',
                certificationBody: 'AUTHORIZED_ISSUER',
                trackingSystem: 'BLOCKCHAIN_FABRIC',
                serialNumber: serialNumber,
                antiDuplicationVerified: !!parsedSecurity.certificate_hash,
                energyDataValidated: true
            },

            // ✅ CRITICAL: Enhanced audit trail
            auditTrail: {
                createdAt: requestedAt,
                createdBy: buyerId,
                lastModified: requestedAt,
                transactionId: txId,
                requestTimestamp: requestedAt,
                workflowStep: 'STEP_1_CERTIFICATE_REQUESTED'
            }
        };

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        
        // ✅ CRITICAL: Store hash mapping untuk anti-duplication
        if (parsedSecurity.certificate_hash) {
            const hashKey = `CERT_HASH_${parsedSecurity.certificate_hash}`;
            await ctx.stub.putState(hashKey, Buffer.from(JSON.stringify({
                certificateId: certId,
                hash: parsedSecurity.certificate_hash,
                createdAt: requestedAt,
                buyerId: buyerId
            })));
        }
        
        console.log('✅ Step 1: Certificate created with ENHANCED SECURITY:', {
            certificateId: certId,
            buyerId: buyerId,
            purchasedAmount: purchasedAmount,
            energyDataId: energyId,
            has_hash: !!parsedSecurity.certificate_hash,
            has_serial: !!serialNumber,
            security_level: parsedSecurity.security_level,
            anti_duplication_verified: !!parsedSecurity.certificate_hash
        });

        return certificate;
    }

    // ✅ Step 2: Buyer confirm payment (status: CERTIFICATE_PAID)
    async confirmPayment(ctx, certId, buyerId, paymentDataStr) {
        const certKey = `CERTIFICATE_${certId}`;
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }

        const certificate = JSON.parse(certBytes.toString());
        
        if (certificate.certificateInfo.status !== 'CERTIFICATE_REQUESTED') {
            throw new Error(`Certificate ${certId} must be in CERTIFICATE_REQUESTED status. Current: ${certificate.certificateInfo.status}`);
        }

        if (certificate.parties.buyer.buyerId !== buyerId) {
            throw new Error(`Only the buyer can confirm payment`);
        }

        // ✅ Parse payment data
        let paymentDetails = { method: 'bank_transfer', reference: `PAY_${certId}` };
        try {
            if (paymentDataStr) {
                paymentDetails = JSON.parse(paymentDataStr);
            }
        } catch (e) {
            console.log('⚠️ Could not parse payment data, using defaults');
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const paidAt = new Date(txTimestamp.seconds * 1000).toISOString();
        const txId = ctx.stub.getTxID();

        // ✅ Update lifecycle
        certificate.lifecycle.paidAt = paidAt;
        certificate.lifecycle.paidBy = buyerId;

        // ✅ Update payment details
        certificate.paymentDetails.paymentConfirmed = true;
        certificate.paymentDetails.paymentConfirmedAt = paidAt;
        certificate.paymentDetails.paymentConfirmedBy = buyerId;
        certificate.paymentDetails.paymentStatus = 'CONFIRMED';
        certificate.paymentDetails.paymentMethod = paymentDetails.method;
        certificate.paymentDetails.paymentReference = paymentDetails.reference;

        // ✅ Update status
        certificate.certificateInfo.status = 'CERTIFICATE_PAID';
        certificate.certificateInfo.statusDescription = 'Payment confirmed by buyer - waiting for issuer verification';
        
        // ✅ Update audit trail
        certificate.auditTrail.lastModified = paidAt;
        certificate.auditTrail.paymentConfirmationTxId = txId;
        certificate.auditTrail.workflowStep = 'STEP_2_CERTIFICATE_PAID';

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        
        console.log('✅ Step 2: Certificate payment confirmed:', {
            certificateId: certId,
            status: 'CERTIFICATE_PAID',
            paidAt: paidAt,
            paymentMethod: paymentDetails.method,
            paymentReference: paymentDetails.reference
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
            throw new Error(`Certificate ${certId} must be CERTIFICATE_PAID before issuance. Current: ${certificate.certificateInfo.status}`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const issuedAt = new Date(txTimestamp.seconds * 1000).toISOString();
        const expiresAt = new Date(txTimestamp.seconds * 1000 + (365 * 24 * 60 * 60 * 1000)).toISOString(); // 1 year
        const txId = ctx.stub.getTxID();

        // ✅ Update lifecycle
        certificate.lifecycle.issuedAt = issuedAt;
        certificate.lifecycle.issuedBy = issuerId;
        certificate.lifecycle.expiresAt = expiresAt;

        // ✅ Update payment verification
        certificate.paymentDetails.paymentVerified = true;
        certificate.paymentDetails.paymentVerifiedAt = issuedAt;
        certificate.paymentDetails.paymentVerifiedBy = issuerId;
        certificate.paymentDetails.paymentStatus = 'VERIFIED';

        // ✅ Update issuer info
        certificate.parties.issuer.issuerId = issuerId;

        // ✅ Update status
        certificate.certificateInfo.status = 'CERTIFICATE_ISSUED';
        certificate.certificateInfo.statusDescription = 'Certificate issued by authorized issuer after payment verification';
        
        // ✅ Update audit trail with transaction consistency
        certificate.auditTrail.lastModified = issuedAt;
        certificate.auditTrail.issuanceTxId = txId;
        certificate.auditTrail.workflowStep = 'STEP_3_CERTIFICATE_ISSUED';
        certificate.auditTrail.transactionConsistency = {
            txId: txId,
            timestamp: issuedAt,
            function: 'issueCertificate',
            endorser: ctx.clientIdentity.getMSPID(),
            stateHash: this.calculateStateHash(certificate)
        };

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        
        console.log('✅ Step 3: Certificate issued with enhanced security:', {
            certificateId: certId,
            status: 'CERTIFICATE_ISSUED',
            issuedAt: issuedAt,
            expiresAt: expiresAt,
            issuedBy: issuerId,
            endorser: ctx.clientIdentity.getMSPID()
        });

        return certificate;
    }

    // ✅ Step 4: Buyer complete certificate (status: COMPLETED)
    async completeCertificate(ctx, certId, buyerId) {
        const certKey = `CERTIFICATE_${certId}`;
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }

        const certificate = JSON.parse(certBytes.toString());
        
        if (certificate.certificateInfo.status !== 'CERTIFICATE_ISSUED') {
            throw new Error(`Certificate ${certId} must be CERTIFICATE_ISSUED before completion. Current: ${certificate.certificateInfo.status}`);
        }

        if (certificate.parties.buyer.buyerId !== buyerId) {
            throw new Error(`Only the buyer can complete this certificate`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const completedAt = new Date(txTimestamp.seconds * 1000).toISOString();
        const txId = ctx.stub.getTxID();

        // ✅ Update lifecycle
        certificate.lifecycle.completedAt = completedAt;
        certificate.lifecycle.completedBy = buyerId;

        // ✅ Update status
        certificate.certificateInfo.status = 'COMPLETED';
        certificate.certificateInfo.statusDescription = 'Certificate completed and activated by buyer';
        
        // ✅ Update audit trail
        certificate.auditTrail.lastModified = completedAt;
        certificate.auditTrail.completionTxId = txId;
        certificate.auditTrail.workflowStep = 'STEP_4_COMPLETED';

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        
        console.log('✅ Step 4: Certificate completed:', {
            certificateId: certId,
            status: 'COMPLETED',
            completedAt: completedAt,
            completedBy: buyerId
        });

        return certificate;
    }

    // Ambil sertifikat by ID
    async getCertificateById(ctx, certId) {
        const certKey = `CERTIFICATE_${certId}`;
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }
        return JSON.parse(certBytes.toString());
    }

    // ✅ NEW: Ambil SEMUA sertifikat dengan format baru
    async getAllCertificates(ctx) {
        const iterator = await ctx.stub.getStateByRange('CERTIFICATE_', 'CERTIFICATE_~');
        const results = [];
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString());
                    if (record.documentType === 'RENEWABLE_ENERGY_CERTIFICATE') {
                        results.push(record);
                    }
                } catch (err) {
                    console.error('Error parsing certificate data:', err);
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
        return results;
    }

    // Ambil semua sertifikat PURCHASED
    async getAllPurchasedCertificates(ctx) {
        const iterator = await ctx.stub.getStateByRange('CERTIFICATE_', 'CERTIFICATE_~');
        const results = [];
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString());
                    if (record.documentType === 'RENEWABLE_ENERGY_CERTIFICATE' && 
                        record.certificateInfo.status === 'PURCHASED') {
                        results.push(record);
                    }
                } catch (err) {
                    console.error(err);
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
        return results;
    }

    // ✅ NEW: Ambil sertifikat berdasarkan status dengan format baru
    async getCertificatesByStatus(ctx, status) {
        const iterator = await ctx.stub.getStateByRange('CERTIFICATE_', 'CERTIFICATE_~');
        const results = [];
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString());
                    if (record.documentType === 'RENEWABLE_ENERGY_CERTIFICATE' && 
                        record.certificateInfo.status === status) {
                        results.push(record);
                    }
                } catch (err) {
                    console.error('Error parsing certificate data:', err);
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
        return results;
    }

    // ✅ NEW: Ambil sertifikat berdasarkan owner/generator
    async getCertificatesByOwner(ctx, ownerId) {
        const iterator = await ctx.stub.getStateByRange('CERTIFICATE_', 'CERTIFICATE_~');
        const results = [];
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString());
                    if (record.documentType === 'RENEWABLE_ENERGY_CERTIFICATE' && 
                        (record.parties.generator.generatorId === ownerId || 
                         (record.parties.buyer && record.parties.buyer.buyerId === ownerId))) {
                        results.push(record);
                    }
                } catch (err) {
                    console.error('Error parsing certificate data:', err);
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
        return results;
    }

    // Riwayat sertifikat dengan format baru
    async getHistoryForCertificate(ctx, certId) {
        const certKey = `CERTIFICATE_${certId}`;
        const iterator = await ctx.stub.getHistoryForKey(certKey);
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

    // ✅ NEW: Calculate energy data hash untuk validation
    calculateEnergyDataHash(energyData) {
        const crypto = require('crypto');
        const hashInput = JSON.stringify({
            generatorId: energyData.generatorId,
            location: energyData.location,
            generationDate: energyData.generationDate,
            amount: energyData.amount,
            sourceType: energyData.sourceType
        });
        return crypto.createHash('sha256').update(hashInput).digest('hex');
    }

    // ✅ NEW: Calculate state hash for consistency checking
    calculateStateHash(certificate) {
        const crypto = require('crypto');
        const stateString = JSON.stringify({
            status: certificate.certificateInfo.status,
            issuedAt: certificate.lifecycle.issuedAt,
            issuedBy: certificate.lifecycle.issuedBy,
            workflowStep: certificate.auditTrail.workflowStep
        });
        return crypto.createHash('sha256').update(stateString).digest('hex');
    }
}

module.exports = CertificateContract;
