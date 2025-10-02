'use strict';

const { Contract } = require('fabric-contract-api');

class EnergyDataContract extends Contract {
    constructor() {
        super('EnergyDataContract');
    }

    // Submit data energi (oleh Generator)
    async submitEnergyData(ctx, id, amount_kwh, source_type, timestamp, location, generatorId) {
        const key = ctx.stub.createCompositeKey('EnergyData', [id]);
        const exists = await ctx.stub.getState(key);
        if (exists && exists.length > 0) {
            throw new Error(`EnergyData ${id} already exists`);
        }

        const energyData = {
            id,
            amount_kwh,
            source_type,
            timestamp,
            location,
            generatorId,
            status: 'PENDING'
        };

        await ctx.stub.putState(key, Buffer.from(JSON.stringify(energyData)));
        return energyData;
    }

    // ✅ Verifikasi data energi (oleh Issuer)
    async verifyEnergyData(ctx, id, issuerId) {
      const key = ctx.stub.createCompositeKey('EnergyData', [id]);
      const data = await ctx.stub.getState(key);
      if (!data || data.length === 0) {
          throw new Error(`EnergyData ${id} does not exist`);
      }

      const energyData = JSON.parse(data.toString());

      if (energyData.status !== 'PENDING') {
          throw new Error(`EnergyData ${id} sudah diverifikasi atau dipakai`);
      }

      // pakai Fabric timestamp biar konsisten di semua peer
      const txTimestamp = ctx.stub.getTxTimestamp();
      const verifiedAt = new Date(txTimestamp.seconds * 1000).toISOString();

      energyData.status = 'VERIFIED';
      energyData.verifiedBy = issuerId;
      energyData.verifiedAt = verifiedAt;

      await ctx.stub.putState(key, Buffer.from(JSON.stringify(energyData)));

      return energyData;
    }

    // Ambil data energi by ID
    async getEnergyDataById(ctx, id) {
        const key = ctx.stub.createCompositeKey('EnergyData', [id]);
        const data = await ctx.stub.getState(key);
        if (!data || data.length === 0) {
            throw new Error(`EnergyData ${id} does not exist`);
        }
        return JSON.parse(data.toString());
    }

    // Ambil semua data energi
    async getAllEnergyData(ctx) {
        const iterator = await ctx.stub.getStateByPartialCompositeKey('EnergyData', []);
        const results = [];
    
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString('utf8'));
                    results.push(record);
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
    
    // Ambil riwayat perubahan
    async getHistoryForEnergyData(ctx, id) {
        const key = ctx.stub.createCompositeKey('EnergyData', [id]);
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
