const express = require('express');
const rateLimit = require('express-rate-limit');
const { authenticate, login, getUserById, ROLES } = require('../middleware/auth');

const router = express.Router();

// Rate limiting for login attempts
const loginLimiter = rateLimit({
    windowMs: 15 * 60 * 1000, // 15 minutes
    max: 5, // limit each IP to 5 requests per windowMs
    message: {
        success: false,
        error: 'Too many login attempts, please try again after 15 minutes'
    },
    standardHeaders: true,
    legacyHeaders: false,
});

// Login endpoint
router.post('/login', loginLimiter, async (req, res) => {
    try {
        const { username, password } = req.body;
        
        if (!username || !password) {
            return res.status(400).json({
                success: false,
                error: 'Username and password are required'
            });
        }
        
        const result = await login(username, password);
        
        if (!result.success) {
            return res.status(401).json(result);
        }
        
        res.json({
            success: true,
            message: 'Login successful',
            data: result.data
        });
        
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({
            success: false,
            error: 'Internal server error'
        });
    }
});

// Get current user profile
router.get('/profile', authenticate, (req, res) => {
    try {
        const user = getUserById(req.user.id);
        
        if (!user) {
            return res.status(404).json({
                success: false,
                error: 'User not found'
            });
        }
        
        res.json({
            success: true,
            data: user
        });
        
    } catch (error) {
        console.error('Profile error:', error);
        res.status(500).json({
            success: false,
            error: 'Internal server error'
        });
    }
});

// Logout endpoint (client-side token removal, but we can log it)
router.post('/logout', authenticate, (req, res) => {
    try {
        // In a real application, you might want to blacklist the token
        // For now, we'll just return success
        console.log(`User ${req.user.username} logged out at ${new Date().toISOString()}`);
        
        res.json({
            success: true,
            message: 'Logout successful'
        });
        
    } catch (error) {
        console.error('Logout error:', error);
        res.status(500).json({
            success: false,
            error: 'Internal server error'
        });
    }
});

// Verify token endpoint
router.get('/verify', authenticate, (req, res) => {
    res.json({
        success: true,
        message: 'Token is valid',
        data: {
            user: req.user,
            expires_at: new Date(req.user.exp * 1000).toISOString()
        }
    });
});

// Get available roles (for admin use)
router.get('/roles', authenticate, (req, res) => {
    if (req.user.role !== ROLES.ADMIN) {
        return res.status(403).json({
            success: false,
            error: 'Admin access required'
        });
    }
    
    res.json({
        success: true,
        data: {
            roles: Object.values(ROLES),
            role_descriptions: {
                [ROLES.GENERATOR]: 'Can create energy records and request certificates',
                [ROLES.ISSUER]: 'Can issue and manage certificates',
                [ROLES.BUYER]: 'Can purchase and view certificates',
                [ROLES.AUDITOR]: 'Can audit all transactions and records',
                [ROLES.ADMIN]: 'Full system access'
            }
        }
    });
});

module.exports = router;