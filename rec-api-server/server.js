const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const morgan = require('morgan');
const path = require('path');
require('dotenv').config();

// Import routes
const energyRoutes = require('./routes/energy');
const certificateRoutes = require('./routes/certificate');
const auditRoutes = require('./routes/audit');
const publicTrackingRoutes = require('./routes/public-tracking');
const trackingRoutes = require('./routes/tracking');

// Utility function untuk generate ID yang konsisten
function generateId(prefix, sequenceNumber = null) {
    const now = new Date();
    const date = now.toISOString().slice(0, 10).replace(/-/g, ''); // YYYYMMDD
    const time = now.toTimeString().slice(0, 8).replace(/:/g, ''); // HHMMSS
    const seq = sequenceNumber ? sequenceNumber.toString().padStart(3, '0') : Math.floor(Math.random() * 999).toString().padStart(3, '0');
    return `${prefix}_${date}_${time}_${seq}`;
}

// Utility functions untuk generate ID yang user-friendly
function generateUserFriendlyId(type, options = {}) {
    const year = new Date().getFullYear();
    
    // Auto-increment counter (dalam production, ini akan disimpan di database)
    const counters = {
        energy: Math.floor(Math.random() * 999) + 1,
        certificate: Math.floor(Math.random() * 999) + 1,
        transaction: Math.floor(Math.random() * 999) + 1
    };
    
    const sequence = counters[type] || Math.floor(Math.random() * 999) + 1;
    const paddedSequence = sequence.toString().padStart(3, '0');
    
    switch (type.toLowerCase()) {
        case 'energy':
            if (options.source && options.location) {
                // Format: SOLAR-JAKARTA-2025-001
                return `${options.source.toUpperCase()}-${options.location.toUpperCase()}-${year}-${paddedSequence}`;
            }
            // Format default: ENERGI-2025-001
            return `ENERGI-${year}-${paddedSequence}`;
            
        case 'certificate':
            // Format: SERTIFIKAT-2025-001
            return `SERTIFIKAT-${year}-${paddedSequence}`;
            
        case 'transaction':
            // Format: TRANSAKSI-2025-001
            return `TRANSAKSI-${year}-${paddedSequence}`;
            
        case 'audit':
            // Format: AUDIT-2025-001
            return `AUDIT-${year}-${paddedSequence}`;
            
        default:
            return `DATA-${year}-${paddedSequence}`;
    }
}

function generateOrgBasedId(orgType, orgName, sequence = null) {
    const year = new Date().getFullYear();
    const seq = sequence || Math.floor(Math.random() * 999) + 1;
    const paddedSequence = seq.toString().padStart(3, '0');
    
    const prefixes = {
        generator: 'GEN',
        issuer: 'ISS', 
        buyer: 'BUY'
    };
    
    const prefix = prefixes[orgType.toLowerCase()] || 'ORG';
    const cleanOrgName = orgName.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
    
    // Format: GEN-PLTSA-001
    return `${prefix}-${cleanOrgName}-${paddedSequence}`;
}

// Create Express app
const app = express();
const PORT = process.env.PORT || 3000;

// Security middleware
app.use(helmet());

// CORS configuration
app.use(cors({
    origin: ['http://localhost:3000', 'http://localhost:8000', 'http://127.0.0.1:8000'],
    credentials: true,
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization']
}));

// Body parsing middleware
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true }));

// Logging middleware
if (process.env.NODE_ENV !== 'production') {
    app.use(morgan('dev'));
} else {
    app.use(morgan('combined'));
}

// Health check endpoint
app.get('/health', (req, res) => {
    res.status(200).json({
        success: true,
        message: 'REC API Server is running',
        timestamp: new Date().toISOString(),
        version: '1.0.0'
    });
});

// API routes
app.use('/api/energy', energyRoutes);
app.use('/api/certificates', certificateRoutes);
app.use('/api/audit', auditRoutes);
app.use('/api', publicTrackingRoutes);
app.use('/api/tracking', trackingRoutes);

// Root endpoint
app.get('/', (req, res) => {
    res.json({
        success: true,
        message: 'Welcome to REC Blockchain API Server',
        version: '1.0.0',
        endpoints: {
            health: '/health',
            energy: '/api/energy',
            certificates: '/api/certificates',
            audit: '/api/audit'
        }
    });
});

// Enhanced utility endpoint untuk generate user-friendly ID
app.get('/utils/generate-id/:type', (req, res) => {
    const { type } = req.params;
    const { source, location, orgType, orgName, sequence } = req.query;
    
    let id;
    
    if (orgType && orgName) {
        // Generate organization-based ID
        id = generateOrgBasedId(orgType, orgName, sequence);
    } else {
        // Generate user-friendly ID
        const options = { source, location };
        id = generateUserFriendlyId(type, options);
    }
    
    res.json({
        success: true,
        data: {
            id: id,
            type: type,
            format: 'user-friendly',
            timestamp: new Date().toISOString(),
            example_usage: getExampleUsage(type)
        }
    });
});

function getExampleUsage(type) {
    const examples = {
        energy: {
            simple: "ENERGI-2025-001",
            with_source: "SOLAR-JAKARTA-2025-001",
            organization: "GEN-PLTSA-001"
        },
        certificate: {
            simple: "SERTIFIKAT-2025-001",
            organization: "ISS-KEMENLHK-001"
        },
        transaction: {
            simple: "TRANSAKSI-2025-001",
            organization: "BUY-PERTAMINA-001"
        }
    };
    
    return examples[type] || examples.energy;
}

// Endpoint untuk mendapatkan contoh format ID
app.get('/utils/id-examples', (req, res) => {
    res.json({
        success: true,
        data: {
            formats: {
                simple: {
                    description: "Format sederhana untuk orang awam",
                    examples: {
                        energy: "ENERGI-2025-001",
                        certificate: "SERTIFIKAT-2025-001", 
                        transaction: "TRANSAKSI-2025-001"
                    }
                },
                location_based: {
                    description: "Format berdasarkan sumber energi dan lokasi",
                    examples: {
                        solar: "SOLAR-JAKARTA-2025-001",
                        wind: "ANGIN-BANDUNG-2025-001",
                        hydro: "HIDRO-SURABAYA-2025-001"
                    }
                },
                organization_based: {
                    description: "Format berdasarkan organisasi",
                    examples: {
                        generator: "GEN-PLTSA-001",
                        issuer: "ISS-KEMENLHK-001",
                        buyer: "BUY-PERTAMINA-001"
                    }
                }
            },
            usage_guide: {
                for_beginners: "Gunakan format 'simple' untuk kemudahan",
                for_advanced: "Gunakan format 'organization_based' untuk tracking yang lebih detail",
                for_location_tracking: "Gunakan format 'location_based' untuk monitoring per daerah"
            }
        }
    });
});

// 404 handler
app.use((req, res) => {
    res.status(404).json({
        success: false,
        message: 'Endpoint not found',
        path: req.originalUrl
    });
});

// Global error handler
app.use((error, req, res, next) => {
    console.error('Unhandled error:', error);
    res.status(500).json({
        success: false,
        message: 'Internal server error',
        error: process.env.NODE_ENV === 'development' ? error.message : 'Something went wrong'
    });
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('SIGTERM received, shutting down gracefully');
    server.close(() => {
        console.log('Process terminated');
    });
});

process.on('SIGINT', () => {
    console.log('SIGINT received, shutting down gracefully');
    server.close(() => {
        console.log('Process terminated');
    });
});

// Start server
const server = app.listen(PORT, () => {
    console.log(`🚀 REC API Server running on port ${PORT}`);
    console.log(`📝 Health check: http://localhost:${PORT}/health`);
    console.log(`🌐 API Documentation: http://localhost:${PORT}/`);
    console.log(`🔗 Environment: ${process.env.NODE_ENV || 'development'}`);
});

module.exports = app;