'use strict';

const { Contract } = require('fabric-contract-api');

class AuditContract extends Contract {
    constructor() {
        super('AuditContract');
    }

    // Tambah audit record baru
    async addAudit(ctx, auditId, action, entity) {
        const key = ctx.stub.createCompositeKey('Audit', [auditId]);
        const exists = await ctx.stub.getState(key);
        if (exists && exists.length > 0) {
            throw new Error(`Audit record ${auditId} already exists`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const timestamp = new Date(txTimestamp.seconds * 1000).toISOString();

        const auditRecord = {
            id: auditId,
            action,
            entity,
            timestamp
        };

        await ctx.stub.putState(key, Buffer.from(JSON.stringify(auditRecord)));
        return auditRecord;
    }

    // Ambil audit record by ID
    async getAuditById(ctx, auditId) {
        const key = ctx.stub.createCompositeKey('Audit', [auditId]);
        const data = await ctx.stub.getState(key);
        if (!data || data.length === 0) {
            throw new Error(`Audit record ${auditId} not found`);
        }
        return JSON.parse(data.toString());
    }

// Ambil semua audit record
async getAllAudits(ctx) {
    const iterator = await ctx.stub.getStateByPartialCompositeKey('Audit', []);
    const results = [];

    try {
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const value = JSON.parse(res.value.value.toString('utf8'));
                    // Pastikan ini adalah audit record
                    if (value.id && value.action && value.entity && value.timestamp) {
                        results.push(value);
                    }
                } catch (err) {
                    console.error(`Error parsing audit record: ${err.message}`);
                }
            }
            if (res.done) {
                await iterator.close();
                break;
            }
        }
    } catch (err) {
        console.error(`Error in getAllAudits: ${err.message}`);
        throw new Error(`Failed to retrieve audits: ${err.message}`);
    }

    return results;
}

// Riwayat audit
    async getHistoryForAudit(ctx, auditId) {
        const key = ctx.stub.createCompositeKey('Audit', [auditId]);
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

module.exports = AuditContract;
