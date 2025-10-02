'use strict';

const { Contract } = require('fabric-contract-api');

class CertificateContract extends Contract {
    constructor() {
        super('CertificateContract');
    }

    // Buat request sertifikat (oleh Generator)
    async createCertificateRequest(ctx, certId, energyId, generatorId) {
        const certKey = ctx.stub.createCompositeKey('Certificate', [certId]);
        const exists = await ctx.stub.getState(certKey);
        if (exists && exists.length > 0) {
            throw new Error(`Certificate ${certId} already exists`);
        }

        const energyKey = ctx.stub.createCompositeKey('EnergyData', [energyId]);
        const energyDataBytes = await ctx.stub.getState(energyKey);
        if (!energyDataBytes || energyDataBytes.length === 0) {
            throw new Error(`EnergyData ${energyId} does not exist`);
        }

        const energyData = JSON.parse(energyDataBytes.toString());
        if (energyData.status !== 'VERIFIED') {
            throw new Error(`EnergyData ${energyId} must be VERIFIED before certificate request`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const createdAt = new Date(txTimestamp.seconds * 1000).toISOString();

        const certificate = {
            certId,
            energyId,
            generatorId,
            status: 'REQUESTED',
            createdAt
        };

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        return certificate;
    }

    // Terbitkan sertifikat (oleh Issuer)
    async issueCertificate(ctx, certId, issuerId) {
        const certKey = ctx.stub.createCompositeKey('Certificate', [certId]);
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }

        const certificate = JSON.parse(certBytes.toString());
        if (certificate.status !== 'REQUESTED') {
            throw new Error(`Certificate ${certId} must be REQUESTED before issuance`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const issuedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        certificate.status = 'ISSUED';
        certificate.issuedBy = issuerId;
        certificate.issuedAt = issuedAt;

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        return certificate;
    }

    // Request pembelian sertifikat (oleh Buyer)
    async createPurchaseRequest(ctx, certId, buyerId, amount) {
        const certKey = ctx.stub.createCompositeKey('Certificate', [certId]);
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }

        const certificate = JSON.parse(certBytes.toString());
        if (certificate.status !== 'ISSUED') {
            throw new Error(`Certificate ${certId} must be ISSUED before purchase`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const requestedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        certificate.status = 'PURCHASE_REQUESTED';
        certificate.purchaseBy = buyerId;
        certificate.requestedAmount = amount;
        certificate.requestedAt = requestedAt;

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        return certificate;
    }

    // Konfirmasi pembelian (oleh Issuer)
    async confirmPurchase(ctx, certId) {
        const certKey = ctx.stub.createCompositeKey('Certificate', [certId]);
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }

        const certificate = JSON.parse(certBytes.toString());
        if (certificate.status !== 'PURCHASE_REQUESTED') {
            throw new Error(`Certificate ${certId} must be in PURCHASE_REQUESTED state before confirmation`);
        }

        const txTimestamp = ctx.stub.getTxTimestamp();
        const purchasedAt = new Date(txTimestamp.seconds * 1000).toISOString();

        certificate.status = 'PURCHASED';
        certificate.purchasedAt = purchasedAt;

        await ctx.stub.putState(certKey, Buffer.from(JSON.stringify(certificate)));
        return certificate;
    }

    // Ambil sertifikat by ID
    async getCertificateById(ctx, certId) {
        const certKey = ctx.stub.createCompositeKey('Certificate', [certId]);
        const certBytes = await ctx.stub.getState(certKey);
        if (!certBytes || certBytes.length === 0) {
            throw new Error(`Certificate ${certId} does not exist`);
        }
        return JSON.parse(certBytes.toString());
    }

    // Ambil semua sertifikat PURCHASED
    async getAllPurchasedCertificates(ctx) {
        const iterator = await ctx.stub.getStateByRange('', '');
        const results = [];
        while (true) {
            const res = await iterator.next();
            if (res.value && res.value.value.toString()) {
                try {
                    const record = JSON.parse(res.value.value.toString());
                    if (record.certId && record.status === 'PURCHASED') {
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

    // Riwayat sertifikat
    async getHistoryForCertificate(ctx, certId) {
        const certKey = ctx.stub.createCompositeKey('Certificate', [certId]);
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
}

module.exports = CertificateContract;
