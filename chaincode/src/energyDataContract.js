'use strict';

const { Contract } = require('fabric-contract-api');
const crypto = require('crypto');

/**
 * EnergyDataContract - Enhanced with SHA-256 validation and anti-duplication
 */
class EnergyDataContract extends Contract {
    constructor() {
        super('EnergyDataContract');
    }

    /**
     * Generate energy data hash for uniqueness validation
     */
    generateEnergyDataHash(amount_kwh, source_type, timestamp, location, generatorId) {
        const hashData = {
            generatorId: generatorId,
            location: location.toLowerCase().trim(),
            generationDate: timestamp,
            amount: parseFloat(amount_kwh),
            sourceType: source_type.toUpperCase()
        };
        
        return crypto.createHash('sha256')
            .update(JSON.stringify(hashData))
            .digest('hex');
    }

    /**
     * Submit renewable energy data with hash validation and anti-duplication
     */
    async submitEnergyData(ctx, id, amount_kwh, source_type, timestamp, location, generatorId) {
        const key = `ENERGY_${id}`;
        const exists = await ctx.stub.getState(key);
        if (exists && exists.length > 0) {
            throw new Error(`Energy data with ID ${id} already exists`);
        }

        // Generate energy data hash for uniqueness
        const energyDataHash = this.generateEnergyDataHash(amount_kwh, source_type, timestamp, location, generatorId);
        
        // Check for hash-based duplication
        const hashKey = `ENERGY_HASH_${energyDataHash}`;
        const hashExists = await ctx.stub.getState(hashKey);
        if (hashExists && hashExists.length > 0) {
            const existingData = JSON.parse(hashExists.toString());
            throw new Error(`Energy data with this generation profile already exists. Existing ID: ${existingData.energyDataId}, Hash: ${energyDataHash}`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const createdAt = new Date(txTimestamp.seconds * 1000).toISOString();

        const energyData = {
            docType: 'energyData',
            energyDataId: id,
            amount: parseFloat(amount_kwh),
            unit: 'kWh',
            sourceType: source_type.toUpperCase(),
            generationDate: timestamp,
            location: location,
            generatorId: generatorId,
            status: 'PENDING',
            createdAt: createdAt,
            transactionId: ctx.stub.getTxID(),
            // Security enhancements
            energyDataHash: energyDataHash,
            serialNumber: `ENERGY-${generatorId}-${energyDataHash.substring(0, 8)}`,
            integrity: {
                originalHash: energyDataHash,
                submissionTxId: ctx.stub.getTxID(),
                submissionTimestamp: createdAt,
                dataFingerprint: energyDataHash
            }
        };

        // Store energy data
        await ctx.stub.putState(key, Buffer.from(JSON.stringify(energyData)));
        
        // Store hash mapping for duplication prevention
        const hashMapping = {
            energyDataId: id,
            energyDataHash: energyDataHash,
            generatorId: generatorId,
            createdAt: createdAt,
            originalTxId: ctx.stub.getTxID()
        };
        await ctx.stub.putState(hashKey, Buffer.from(JSON.stringify(hashMapping)));
        
        console.log(`✅ Energy data ${id} submitted by ${generatorId} with hash ${energyDataHash}`);
        return energyData;
    }

    /**
     * Verify energy data with integrity checks
     */
    async verifyEnergyData(ctx, energyId, issuerId) {
        const energyKey = `ENERGY_${energyId}`;
        const energyDataBytes = await ctx.stub.getState(energyKey);
        
        if (!energyDataBytes || energyDataBytes.length === 0) {
            throw new Error(`Energy data with ID ${energyId} does not exist`);
        }

        const energyData = JSON.parse(energyDataBytes.toString());
        
        if (energyData.status !== 'PENDING') {
            throw new Error(`Energy data ${energyId} must be in PENDING status for verification`);
        }

        // Verify data integrity using hash
        if (energyData.energyDataHash) {
            const recalculatedHash = this.generateEnergyDataHash(
                energyData.amount,
                energyData.sourceType,
                energyData.generationDate,
                energyData.location,
                energyData.generatorId
            );
            
            if (recalculatedHash !== energyData.energyDataHash) {
                throw new Error(`Energy data integrity check FAILED. Original hash: ${energyData.energyDataHash}, Calculated: ${recalculatedHash}. Data may have been tampered with.`);
            }
            
            console.log(`✅ Energy data ${energyId} integrity verified with hash ${recalculatedHash}`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const verifiedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        energyData.status = 'VERIFIED';
        energyData.verifiedAt = verifiedAt;
        energyData.verifiedBy = issuerId;
        energyData.verificationTxId = ctx.stub.getTxID();
        
        // Add verification integrity
        energyData.verification = {
            verifiedAt: verifiedAt,
            verifiedBy: issuerId,
            verificationTxId: ctx.stub.getTxID(),
            integrityConfirmed: true,
            hashValidated: energyData.energyDataHash ? true : false
        };

        await ctx.stub.putState(energyKey, Buffer.from(JSON.stringify(energyData)));
        
        console.log(`✅ Energy data ${energyId} verified by ${issuerId} with integrity confirmation`);
        return {
            message: 'Energy data verified successfully with integrity confirmation',
            energyData: energyData
        };
    }

    /**
     * Verify energy data hash integrity
     */
    async verifyEnergyDataIntegrity(ctx, energyId) {
        const energyData = await this.getEnergyDataById(ctx, energyId);
        
        if (!energyData.energyDataHash) {
            return {
                isValid: false,
                message: 'Energy data does not have hash - legacy data'
            };
        }
        
        const recalculatedHash = this.generateEnergyDataHash(
            energyData.amount,
            energyData.sourceType,
            energyData.generationDate,
            energyData.location,
            energyData.generatorId
        );
        
        const isValid = (recalculatedHash === energyData.energyDataHash);
        
        return {
            isValid: isValid,
            originalHash: energyData.energyDataHash,
            recalculatedHash: recalculatedHash,
            message: isValid ? 'Energy data integrity verified' : 'Energy data integrity FAILED - data tampering detected'
        };
    }

    /**
     * Check if energy data hash already exists (duplication check)
     */
    async checkEnergyDataDuplication(ctx, amount_kwh, source_type, timestamp, location, generatorId) {
        const energyDataHash = this.generateEnergyDataHash(amount_kwh, source_type, timestamp, location, generatorId);
        const hashKey = `ENERGY_HASH_${energyDataHash}`;
        const hashExists = await ctx.stub.getState(hashKey);
        
        if (hashExists && hashExists.length > 0) {
            const existingData = JSON.parse(hashExists.toString());
            return {
                isDuplicate: true,
                existingEnergyDataId: existingData.energyDataId,
                hash: energyDataHash,
                message: 'Energy data with identical generation profile already exists'
            };
        }
        
        return {
            isDuplicate: false,
            hash: energyDataHash,
            message: 'Energy data is unique'
        };
    }

    /**
     * Get energy data by ID
     */
    async getEnergyDataById(ctx, id) {
        const key = `ENERGY_${id}`;
        const data = await ctx.stub.getState(key);
        
        if (!data || data.length === 0) {
            throw new Error(`Energy data with ID ${id} does not exist`);
        }
        
        return JSON.parse(data.toString());
    }

    /**
     * Get all energy data
     */
    async getAllEnergyData(ctx) {
        const iterator = await ctx.stub.getStateByRange('ENERGY_', 'ENERGY_~');
        const results = [];

        while (true) {
            const res = await iterator.next();
            
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString('utf8'));
                    if (record.docType === 'energyData') {
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

    /**
     * Get energy data history
     */
    async getEnergyDataHistory(ctx, id) {
        const key = `ENERGY_${id}`;
        const iterator = await ctx.stub.getHistoryForKey(key);
        const results = [];

        while (true) {
            const res = await iterator.next();
            
            if (res.value) {
                const record = {
                    txId: res.value.txId,
                    timestamp: res.value.timestamp,
                    isDelete: res.value.isDelete,
                    value: res.value.value.toString('utf8')
                };
                results.push(record);
            }
            
            if (res.done) {
                await iterator.close();
                break;
            }
        }

        return results;
    }

    /**
     * Get energy data by generator
     */
    async getEnergyDataByGenerator(ctx, generatorId) {
        const iterator = await ctx.stub.getStateByRange('ENERGY_', 'ENERGY_~');
        const results = [];

        while (true) {
            const res = await iterator.next();
            
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString('utf8'));
                    if (record.docType === 'energyData' && record.generatorId === generatorId) {
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
}

module.exports = EnergyDataContract;
