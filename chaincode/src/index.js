'use strict';

/**
 * REC (Renewable Energy Certificate) Chaincode
 * Node.js implementation for Hyperledger Fabric
 */

const AuditContract = require('./auditContract');
const EnergyDataContract = require('./energyDataContract');
const CertificateContract = require('./certificateContract');  // ✅ ADDED

module.exports.contracts = [
    AuditContract,
    EnergyDataContract,
    CertificateContract  // ✅ ADDED
];

module.exports.AuditContract = AuditContract;
module.exports.EnergyDataContract = EnergyDataContract;
module.exports.CertificateContract = CertificateContract;  // ✅ ADDED