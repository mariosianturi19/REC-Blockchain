# REC Blockchain REST API Server

REST API server untuk REC (Renewable Energy Certificate) Blockchain system yang terintegrasi dengan Hyperledger Fabric.

## 🚀 Quick Start

### Prerequisites
- Node.js (v14 atau lebih tinggi)
- Hyperledger Fabric network yang sedang berjalan
- Chaincode REC yang sudah di-deploy

### Installation
```bash
cd rec-api-server
npm install
```

### Configuration
Update file `.env` dengan konfigurasi network Anda:
```env
PORT=3000
CHANNEL_NAME=recchannel
CHAINCODE_NAME=rec-chaincode
MSP_ID=Org1MSP
```

### Start Server
```bash
# Development mode
npm run dev

# Production mode
npm start
```

## 📚 API Endpoints

### Health Check
- `GET /health` - Server health status
- `GET /` - API documentation

### Energy Data Contract
- `POST /api/energy/submit` - Submit energy data (Generator)
- `PUT /api/energy/verify/:energyDataId` - Verify energy data (Issuer)
- `GET /api/energy/:energyDataId` - Get energy data by ID
- `GET /api/energy` - Get all energy data
- `GET /api/energy/:energyDataId/history` - Get energy data history

### Certificate Contract
- `POST /api/certificates/request` - Create certificate request (Generator)
- `PUT /api/certificates/issue/:certificateId` - Issue certificate (Issuer)
- `POST /api/certificates/purchase-request` - Create purchase request (Buyer)
- `PUT /api/certificates/confirm-purchase/:certificateId` - Confirm purchase (Issuer)
- `GET /api/certificates/:certificateId` - Get certificate by ID
- `GET /api/certificates/purchased/all` - Get all purchased certificates
- `GET /api/certificates/:certificateId/history` - Get certificate history

### Audit Contract
- `POST /api/audit/log` - Log audit entry
- `GET /api/audit/:auditId` - Get audit entry by ID
- `GET /api/audit/entity/:entityType/:entityId` - Get audit entries by entity
- `GET /api/audit/user/:userId` - Get audit entries by user
- `GET /api/audit` - Get all audit entries

## 🔄 Complete Workflow Example

### 1. Submit Energy Data (Generator)
```bash
curl -X POST http://localhost:3000/api/energy/submit \
  -H "Content-Type: application/json" \
  -d '{
    "energyDataId": "ENERGY001",
    "generatorId": "GENERATOR001",
    "energyAmount": 1000,
    "generationDate": "2024-01-15",
    "location": "Jakarta Solar Farm",
    "energySource": "Solar"
  }'
```

### 2. Verify Energy Data (Issuer)
```bash
curl -X PUT http://localhost:3000/api/energy/verify/ENERGY001 \
  -H "Content-Type: application/json" \
  -d '{
    "issuerId": "ISSUER001",
    "verificationNotes": "Data verified and approved"
  }'
```

### 3. Request Certificate (Generator)
```bash
curl -X POST http://localhost:3000/api/certificates/request \
  -H "Content-Type: application/json" \
  -d '{
    "certificateId": "CERT001",
    "generatorId": "GENERATOR001",
    "energyDataId": "ENERGY001",
    "requestedAmount": 1000
  }'
```

### 4. Issue Certificate (Issuer)
```bash
curl -X PUT http://localhost:3000/api/certificates/issue/CERT001 \
  -H "Content-Type: application/json" \
  -d '{
    "issuerId": "ISSUER001",
    "issueDate": "2024-01-16",
    "expiryDate": "2025-01-16"
  }'
```

### 5. Purchase Request (Buyer)
```bash
curl -X POST http://localhost:3000/api/certificates/purchase-request \
  -H "Content-Type: application/json" \
  -d '{
    "certificateId": "CERT001",
    "buyerId": "BUYER001",
    "purchaseAmount": 500,
    "requestDate": "2024-01-17"
  }'
```

### 6. Confirm Purchase (Issuer)
```bash
curl -X PUT http://localhost:3000/api/certificates/confirm-purchase/CERT001 \
  -H "Content-Type: application/json" \
  -d '{
    "issuerId": "ISSUER001",
    "purchaseDate": "2024-01-17",
    "purchasePrice": 75000
  }'
```

## 🛠️ Development

### Project Structure
```
rec-api-server/
├── server.js              # Main server file
├── package.json           # Dependencies
├── .env                   # Environment variables
├── config/
│   └── connection.json    # Fabric network connection profile
├── services/
│   └── fabricService.js   # Fabric network service
├── routes/
│   ├── energy.js         # Energy data routes
│   ├── certificate.js    # Certificate routes
│   └── audit.js          # Audit routes
└── wallets/              # Fabric user wallets
```

### Error Handling
Semua endpoint menggunakan format response yang konsisten:

**Success Response:**
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { /* response data */ }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message"
}
```

## 🔐 Security Features
- Helmet.js untuk HTTP security headers
- CORS configuration
- Input validation
- Error handling yang aman
- Graceful shutdown

## 📈 Monitoring
- Morgan logging untuk request tracking
- Health check endpoint untuk monitoring
- Environment-based logging levels

## 🤝 Integration dengan Website
API ini dirancang untuk terintegrasi dengan website Laravel Anda di folder `Capstone_Renewa/`.