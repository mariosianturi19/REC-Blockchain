'use strict';

const { Contract } = require('fabric-contract-api');

class EnergyDataContract extends Contract {
    constructor() {
        super('EnergyDataContract');
    }

    // Submit data energi (oleh Generator)
    async submitEnergyData(ctx, id, amount_kwh, source_type, timestamp, location, generatorId) {
        // Gunakan simple key tanpa composite untuk format yang lebih bersih
        const key = `ENERGY_${id}`;
        const exists = await ctx.stub.getState(key);
        if (exists && exists.length > 0) {
            throw new Error(`EnergyData ${id} already exists`);
        }

        // Timestamp untuk audit trail
        const txTimestamp = ctx.stub.getTxTimestamp();
        const createdAt = new Date(txTimestamp.seconds * 1000).toISOString();

        // Format data yang lebih profesional dan rapi
        const energyData = {
            // Header Information
            documentType: 'RENEWABLE_ENERGY_DATA',
            energyDataId: id,
            version: '1.0',
            
            // Energy Generation Details
            energyGeneration: {
                amount: parseFloat(amount_kwh),
                unit: 'kWh',
                sourceType: source_type.toUpperCase(),
                generationDate: timestamp,
                location: location
            },
            
            // Generator Information
            generator: {
                generatorId: generatorId,
                organizationType: 'RENEWABLE_ENERGY_GENERATOR'
            },
            
            // Certification Status
            certificationStatus: {
                status: 'PENDING_VERIFICATION',
                statusDescription: 'Awaiting verification by authorized issuer',
                submittedAt: createdAt,
                verifiedAt: null,
                verifiedBy: null,
                verificationNotes: null
            },
            
            // Audit Trail
            auditTrail: {
                createdAt: createdAt,
                createdBy: generatorId,
                lastModified: createdAt,
                transactionId: ctx.stub.getTxID()
            },
            
            // Compliance & Standards
            compliance: {
                regulatoryFramework: 'INTERNATIONAL_REC_STANDARD',
                certificationBody: 'AUTHORIZED_ISSUER',
                validityPeriod: '12_MONTHS'
            }
        };

        await ctx.stub.putState(key, Buffer.from(JSON.stringify(energyData)));
        return energyData;
    }

    // ✅ Verifikasi data energi (oleh Issuer)
    async verifyEnergyData(ctx, id, issuerId) {
        const key = `ENERGY_${id}`;
        const data = await ctx.stub.getState(key);
        if (!data || data.length === 0) {
            throw new Error(`EnergyData ${id} does not exist`);
        }

        const energyData = JSON.parse(data.toString());

        if (energyData.certificationStatus.status !== 'PENDING_VERIFICATION') {
            throw new Error(`EnergyData ${id} sudah diverifikasi atau tidak dalam status pending`);
        }

        // Update dengan format yang profesional
        const txTimestamp = ctx.stub.getTxTimestamp();
        const verifiedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        energyData.certificationStatus = {
            status: 'VERIFIED',
            statusDescription: 'Energy data successfully verified by authorized issuer',
            submittedAt: energyData.certificationStatus.submittedAt,
            verifiedAt: verifiedAt,
            verifiedBy: issuerId,
            verificationNotes: 'Data telah diverifikasi sesuai standar REC internasional'
        };

        energyData.auditTrail.lastModified = verifiedAt;
        energyData.auditTrail.verificationTxId = ctx.stub.getTxID();

        await ctx.stub.putState(key, Buffer.from(JSON.stringify(energyData)));
        return energyData;
    }

    // Ambil data energi by ID
    async getEnergyDataById(ctx, id) {
        const key = `ENERGY_${id}`;
        const data = await ctx.stub.getState(key);
        if (!data || data.length === 0) {
            throw new Error(`EnergyData ${id} does not exist`);
        }
        return JSON.parse(data.toString());
    }

    // Ambil semua data energi dengan format yang rapi
    async getAllEnergyData(ctx) {
        const iterator = await ctx.stub.getStateByRange('ENERGY_', 'ENERGY_~');
        const results = [];
    
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString('utf8'));
                    // Hanya return jika ini adalah energy data document
                    if (record.documentType === 'RENEWABLE_ENERGY_DATA') {
                        results.push(record);
                    }
                } catch (err) {
                    console.error('Error parsing energy data:', err);
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
    
        return results;
    }
    
    // Ambil riwayat perubahan dengan key yang benar
    async getHistoryForEnergyData(ctx, id) {
        const key = `ENERGY_${id}`;
        const iterator = await ctx.stub.getHistoryForKey(key);
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

module.exports = EnergyDataContract;
