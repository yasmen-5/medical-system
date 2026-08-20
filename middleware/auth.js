const jwt = require('jsonwebtoken');
const { dbGet } = require('../database/setup');

const patientAccess = (req, res, next) => {
  try {
    const token = req.headers.authorization?.replace('Bearer ', '');
    
    if (!token) {
      // Check for userId in header (for development/testing)
      const userId = req.headers['x-user-id'] || req.body.userId;
      if (userId) {
        req.userId = userId;
        return next();
      }
      
      return res.status(401).json({ message: 'No token provided' });
    }

    const decoded = jwt.verify(token, process.env.JWT_SECRET || 'your-secret-key');
    
    const session = dbGet('sessions', s => 
      s.token === token && 
      s.user_id === decoded.userId && 
      new Date(s.expires_at) > new Date()
    );

    if (!session) {
      return res.status(401).json({ message: 'Invalid or expired token' });
    }

    req.userId = decoded.userId;
    next();
  } catch (error) {
    res.status(401).json({ message: 'Invalid token' });
  }
};

module.exports = { patientAccess };