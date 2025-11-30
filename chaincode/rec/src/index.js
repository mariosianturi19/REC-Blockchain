'use strict';

const AuditContract = require('./auditContract');
const CertificateContract = require('./certificateContract');
const EnergyDataContract = require('./energyDataContract');

module.exports.contracts = [
  AuditContract,
  CertificateContract,
  EnergyDataContract
];