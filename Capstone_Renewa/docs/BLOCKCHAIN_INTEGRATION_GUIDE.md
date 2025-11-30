# 🔗 REC Blockchain Integration Guide

## 📋 Overview

Dokumentasi ini menjelaskan cara menggunakan integrasi REC Blockchain API dengan Laravel web application. Integrasi ini mengotomatisasi workflow 6-step REC (Renewable Energy Certificate) menggunakan Hyperledger Fabric blockchain.

## 🏗️ Architecture

```
Laravel Web App ← HTTP → REC API Server ← gRPC → Hyperledger Fabric Network
```

### Workflow Steps:
1. **Submit Energy Data** - Generator mengirim data produksi energi
2. **Verify Energy Data** - Issuer memverifikasi data energi
3. **Request Certificate** - Generator meminta sertifikat
4. **Issue Certificate** - Issuer menerbitkan sertifikat
5. **Create Purchase Request** - Buyer mengajukan pembelian
6. **Confirm Purchase** - Issuer mengkonfirmasi transaksi

## 🚀 Quick Start

### 1. Environment Setup

Tambahkan konfigurasi ini ke file `.env`:

```env
# REC Blockchain API Configuration
BLOCKCHAIN_API_URL=http://localhost:3000
BLOCKCHAIN_API_TIMEOUT=30
BLOCKCHAIN_ENABLED=true

# Organization IDs
DEFAULT_GENERATOR_ID=GEN-PLTSA-001
DEFAULT_ISSUER_ID=ISSUER001
DEFAULT_BUYER_ID=BUYER001
```

### 2. Database Migration

Jalankan migration untuk menambahkan kolom blockchain:

```bash
php artisan migrate
```

### 3. Start REC API Server

Pastikan REC API Server berjalan di port 3000:

```bash
cd rec-api-server
npm start
```

### 4. Health Check

Test koneksi blockchain:

```bash
curl http://localhost:8000/api/blockchain/health
```

## 📝 API Endpoints

### Health Check
```http
GET /api/blockchain/health
```

### Energy Data Management
```http
# Submit Energy Data (Step 1)
POST /api/blockchain/energy/submit
Content-Type: application/json

{
    "energy_amount": 1000,
    "generation_date": "2025-10-02",
    "location": "Jakarta",
    "energy_source": "Solar",
    "generator_id": "GEN-PLTSA-001"
}

# Verify Energy Data (Step 2)
PUT /api/blockchain/energy/verify/{energyDataId}
Content-Type: application/json

{
    "issuer_id": "ISSUER001",
    "verification_notes": "Data terverifikasi"
}

# Get Energy Data
GET /api/blockchain/energy
GET /api/blockchain/energy/{energyDataId}
```

### Certificate Management
```http
# Request Certificate (Step 3)
POST /api/blockchain/certificates/request
Content-Type: application/json

{
    "energy_data_id": "ENERGI-2025-001",
    "generator_id": "GEN-PLTSA-001"
}

# Issue Certificate (Step 4)
PUT /api/blockchain/certificates/issue/{certId}
Content-Type: application/json

{
    "issuer_id": "ISSUER001"
}

# Create Purchase Request (Step 5)
POST /api/blockchain/certificates/purchase
Content-Type: application/json

{
    "certificate_id": "SERTIFIKAT-2025-001",
    "amount": 500,
    "buyer_id": "BUYER001"
}

# Confirm Purchase (Step 6)
PUT /api/blockchain/certificates/confirm/{certId}

# Get Certificates
GET /api/blockchain/certificates
GET /api/blockchain/certificates/{certId}
```

## 💻 Code Usage Examples

### Using BlockchainService in Controllers

```php
<?php

use App\Services\BlockchainService;

class YourController extends Controller
{
    protected $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    public function submitEnergyData(Request $request)
    {
        try {
            $data = [
                'energyDataId' => 'ENERGI-' . date('Ymd-His'),
                'generatorId' => 'GEN-PLTSA-001',
                'energyAmount' => $request->amount,
                'generationDate' => $request->date,
                'location' => $request->location,
                'energySource' => $request->source
            ];

            $result = $this->blockchainService->submitEnergyData($data);
            
            if ($result['success']) {
                // Handle success
                return response()->json(['message' => 'Data submitted to blockchain']);
            }
        } catch (\Exception $e) {
            // Handle error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

### Automatic Integration Example

Integrasi otomatis sudah dikonfigurasi di controller existing:

- **EnergyReportController**: Otomatis submit ke blockchain saat Generator membuat laporan energi
- **Issuer/CertificateController**: Otomatis verify dan issue certificate saat Issuer approve laporan
- **Buyer/CheckoutController**: Otomatis create purchase request saat Buyer konfirmasi pembayaran
- **Issuer/DashboardController**: Otomatis confirm purchase saat Issuer approve pembayaran

## 🔍 Monitoring & Debugging

### Database Columns untuk Tracking

**energy_reports table:**
- `blockchain_energy_id`: ID unik di blockchain
- `blockchain_status`: Status submit (submitted/failed)
- `blockchain_verification_status`: Status verifikasi (verified/failed)

**certificates table:**
- `blockchain_cert_id`: ID sertifikat di blockchain
- `blockchain_status`: Status sertifikat (issued/failed)
- `blockchain_purchase_status`: Status pembelian (requested/purchased/failed)

### Log Monitoring

Monitor file log Laravel untuk tracking blockchain operations:

```bash
tail -f storage/logs/laravel.log | grep "blockchain"
```

### Common Log Messages

```
[INFO] Energy data submitted to blockchain: {"id": "ENERGI-2025-001"}
[INFO] Energy data verified on blockchain: {"id": "ENERGI-2025-001"}
[INFO] Certificate issued on blockchain: {"certId": "SERTIFIKAT-2025-001"}
[INFO] Purchase request created on blockchain: {"certId": "SERTIFIKAT-2025-001"}
[INFO] Purchase confirmed on blockchain: {"certId": "SERTIFIKAT-2025-001"}
```

## 🧪 Testing

### Run Integration Tests

```bash
php tests/Integration/BlockchainIntegrationTest.php
```

### Manual Testing dengan Postman

Import collection dari file: `docs/postman/REC-Blockchain-API.postman_collection.json`

### Test Complete Workflow

1. Submit energy data sebagai Generator
2. Verify data sebagai Issuer  
3. Request certificate sebagai Generator
4. Issue certificate sebagai Issuer
5. Create purchase request sebagai Buyer
6. Confirm purchase sebagai Issuer

## 🚨 Troubleshooting

### Common Issues

**1. Connection Refused**
```
Error: Connection refused to localhost:3000
```
**Solution**: Pastikan REC API Server berjalan di port 3000

**2. Blockchain Disabled**
```
Info: Blockchain disabled, skipping energy data submission
```
**Solution**: Set `BLOCKCHAIN_ENABLED=true` di file `.env`

**3. Invalid Organization ID**
```
Error: Organization not found: INVALID-ORG-001
```
**Solution**: Pastikan organization ID valid di konfigurasi

**4. Migration Error**
```
Error: Column already exists: blockchain_energy_id
```
**Solution**: Rollback migration atau skip jika kolom sudah ada

### Debug Mode

Enable debug mode untuk detail error:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Health Check Endpoints

- Laravel Health: `GET /api/blockchain/health`
- REC API Health: `GET http://localhost:3000/health`
- Fabric Network: Check dengan `peer channel list`

## 📞 Support

Untuk bantuan teknis:
1. Check log files di `storage/logs/`
2. Verify blockchain network status
3. Test individual API endpoints
4. Run integration tests

## 🔄 Update & Maintenance

### Update Blockchain Configuration

1. Update environment variables
2. Restart Laravel application
3. Run health check
4. Test workflow

### Backup Considerations

- Database: Backup tables dengan kolom blockchain_*
- Logs: Archive blockchain operation logs
- Config: Backup environment configuration

---

**📚 Additional Resources:**
- [Hyperledger Fabric Documentation](https://hyperledger-fabric.readthedocs.io/)
- [Laravel HTTP Client Documentation](https://laravel.com/docs/http-client)
- [REC Standard Documentation](https://www.recs.org/)