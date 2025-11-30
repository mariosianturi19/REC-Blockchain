const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');

// JWT Secret (should be in environment variables)
const JWT_SECRET = process.env.JWT_SECRET || 'your-secret-key-here';
const JWT_EXPIRES_IN = process.env.JWT_EXPIRES_IN || '24h';

// User roles and permissions
const ROLES = {
    GENERATOR: 'generator',
    ISSUER: 'issuer', 
    BUYER: 'buyer',
    AUDITOR: 'auditor',
    ADMIN: 'admin'
};

const PERMISSIONS = {
    [ROLES.GENERATOR]: ['energy:create', 'energy:read', 'certificate:request'],
    [ROLES.ISSUER]: ['certificate:create', 'certificate:read', 'certificate:issue', 'energy:read'],
    [ROLES.BUYER]: ['certificate:read', 'certificate:purchase', 'energy:read'],
    [ROLES.AUDITOR]: ['audit:create', 'audit:read', '*:read'],
    [ROLES.ADMIN]: ['*']
};

// Mock user database (in production, this would be a real database)
const users = [
    {
        id: 'gen001',
        username: 'pltsa_admin',
        email: 'admin@pltsa.com',
        password: '$2a$10$5Q1ZqK0mF1fXgXfXgXfXuOZGZGZGZGZGZGZGZGZGZGZGZGZGZGZGZ', // 'password123'
        role: ROLES.GENERATOR,
        organization: 'PLTSA',
        organizationType: 'generator'
    },
    {
        id: 'iss001',
        username: 'kemenlhk_admin',
        email: 'admin@kemenlhk.go.id',
        password: '$2a$10$5Q1ZqK0mF1fXgXfXgXfXuOZGZGZGZGZGZGZGZGZGZGZGZGZGZGZGZ',
        role: ROLES.ISSUER,
        organization: 'KEMENLHK',
        organizationType: 'issuer'
    },
    {
        id: 'buy001',
        username: 'pertamina_admin',
        email: 'admin@pertamina.com',
        password: '$2a$10$5Q1ZqK0mF1fXgXfXgXfXuOZGZGZGZGZGZGZGZGZGZGZGZGZGZGZGZ',
        role: ROLES.BUYER,
        organization: 'PERTAMINA',
        organizationType: 'buyer'
    }
];

// Generate JWT token
function generateToken(user) {
    const payload = {
        id: user.id,
        username: user.username,
        email: user.email,
        role: user.role,
        organization: user.organization,
        organizationType: user.organizationType
    };
    
    return jwt.sign(payload, JWT_SECRET, { expiresIn: JWT_EXPIRES_IN });
}

// Verify JWT token
function verifyToken(token) {
    try {
        return jwt.verify(token, JWT_SECRET);
    } catch (error) {
        return null;
    }
}

// Authentication middleware
function authenticate(req, res, next) {
    const authHeader = req.headers.authorization;
    const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN
    
    if (!token) {
        return res.status(401).json({
            success: false,
            error: 'Access token required'
        });
    }
    
    const decoded = verifyToken(token);
    if (!decoded) {
        return res.status(401).json({
            success: false,
            error: 'Invalid or expired token'
        });
    }
    
    req.user = decoded;
    next();
}

// Authorization middleware
function authorize(requiredPermissions = []) {
    return (req, res, next) => {
        if (!req.user) {
            return res.status(401).json({
                success: false,
                error: 'Authentication required'
            });
        }
        
        const userRole = req.user.role;
        const userPermissions = PERMISSIONS[userRole] || [];
        
        // Admin has all permissions
        if (userRole === ROLES.ADMIN) {
            return next();
        }
        
        // Check if user has required permissions
        const hasPermission = requiredPermissions.every(permission => {
            return userPermissions.includes(permission) || 
                   userPermissions.includes('*') ||
                   userPermissions.some(p => p.endsWith(':*') && permission.startsWith(p.replace('*', '')));
        });
        
        if (!hasPermission) {
            return res.status(403).json({
                success: false,
                error: 'Insufficient permissions',
                required: requiredPermissions,
                user_permissions: userPermissions
            });
        }
        
        next();
    };
}

// Login function
async function login(username, password) {
    const user = users.find(u => u.username === username || u.email === username);
    if (!user) {
        return { success: false, error: 'User not found' };
    }
    
    const isValidPassword = await bcrypt.compare(password, user.password);
    if (!isValidPassword) {
        return { success: false, error: 'Invalid password' };
    }
    
    const token = generateToken(user);
    const { password: _, ...userWithoutPassword } = user;
    
    return {
        success: true,
        data: {
            user: userWithoutPassword,
            token,
            expiresIn: JWT_EXPIRES_IN
        }
    };
}

// Get user by ID
function getUserById(id) {
    const user = users.find(u => u.id === id);
    if (user) {
        const { password: _, ...userWithoutPassword } = user;
        return userWithoutPassword;
    }
    return null;
}

module.exports = {
    authenticate,
    authorize,
    login,
    getUserById,
    generateToken,
    verifyToken,
    ROLES,
    PERMISSIONS
};