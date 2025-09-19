'use strict';

const { Contract } = require('fabric-contract-api');

class RECContract extends Contract {

    // Initialize the ledger with sample data
    async initLedger(ctx) {
        console.info('============= START : Initialize Ledger ===========');
        
        const recs = [
            {
                id: 'REC001',
                owner: 'Generator1',
                energyType: 'Solar',
                amount: 1000,
                issueDate: '2025-01-01',
                status: 'Active'
            },
            {
                id: 'REC002', 
                owner: 'Generator2',
                energyType: 'Wind',
                amount: 1500,
                issueDate: '2025-01-02',
                status: 'Active'
            }
        ];

        for (let i = 0; i < recs.length; i++) {
            recs[i].docType = 'rec';
            await ctx.stub.putState(recs[i].id, Buffer.from(JSON.stringify(recs[i])));
            console.info('Added <--> ', recs[i]);
        }
        
        console.info('============= END : Initialize Ledger ===========');
    }

    // Create a new REC
    async createREC(ctx, id, owner, energyType, amount, issueDate) {
        console.info('============= START : Create REC ===========');

        const rec = {
            id,
            docType: 'rec',
            owner,
            energyType,
            amount: parseInt(amount),
            issueDate,
            status: 'Active'
        };

        await ctx.stub.putState(id, Buffer.from(JSON.stringify(rec)));
        console.info('============= END : Create REC ===========');
    }

    // Query a specific REC
    async queryREC(ctx, recId) {
        const recAsBytes = await ctx.stub.getState(recId);
        if (!recAsBytes || recAsBytes.length === 0) {
            throw new Error(`${recId} does not exist`);
        }
        console.log(recAsBytes.toString());
        return recAsBytes.toString();
    }

    // Query all RECs
    async queryAllRECs(ctx) {
        const startKey = '';
        const endKey = '';
        const allResults = [];
        
        const iterator = await ctx.stub.getStateByRange(startKey, endKey);
        
        while (true) {
            const res = await iterator.next();
            
            if (res.value && res.value.value.toString()) {
                console.log(res.value.value.toString('utf8'));
                
                const Key = res.value.key;
                let Record;
                try {
                    Record = JSON.parse(res.value.value.toString('utf8'));
                } catch (err) {
                    console.log(err);
                    Record = res.value.value.toString('utf8');
                }
                allResults.push({ Key, Record });
            }
            if (res.done) {
                console.log('end of data');
                await iterator.close();
                console.info(allResults);
                return JSON.stringify(allResults);
            }
        }
    }

    // Transfer REC ownership
    async transferREC(ctx, recId, newOwner) {
        console.info('============= START : Transfer REC ===========');

        const recAsBytes = await ctx.stub.getState(recId);
        if (!recAsBytes || recAsBytes.length === 0) {
            throw new Error(`${recId} does not exist`);
        }
        
        const rec = JSON.parse(recAsBytes.toString());
        rec.owner = newOwner;

        await ctx.stub.putState(recId, Buffer.from(JSON.stringify(rec)));
        console.info('============= END : Transfer REC ===========');
    }

    // Retire a REC
    async retireREC(ctx, recId) {
        console.info('============= START : Retire REC ===========');

        const recAsBytes = await ctx.stub.getState(recId);
        if (!recAsBytes || recAsBytes.length === 0) {
            throw new Error(`${recId} does not exist`);
        }
        
        const rec = JSON.parse(recAsBytes.toString());
        rec.status = 'Retired';

        await ctx.stub.putState(recId, Buffer.from(JSON.stringify(rec)));
        console.info('============= END : Retire REC ===========');
    }

}

module.exports = RECContract;