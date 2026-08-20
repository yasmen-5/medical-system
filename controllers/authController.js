const { dbGet, dbRun, dbUpdate, dbDelete } = require('../database/setup');
const { v4: uuidv4 } = require('uuid');
const crypto = require('crypto');
const jwt = require('jsonwebtoken');

// Simple SHA-256 hashing (for demo purposes - use bcrypt in production)
const hashPassword = (password) => {
  return crypto.createHash('sha256').update(password).digest('hex');
};

const generateOTP = () => Math.floor(100000 + Math.random() * 900000).toString();

const authController = {
  register: async (req, res) => {
    try {
      const { email, phone, password, name } = req.body;
      
      // Check if user exists
      const existingUser = dbGet('users', u => u.email === email || u.phone === phone);
      if (existingUser) {
        return res.status(400).json({ message: 'User already exists' });
      }

      const userId = uuidv4();
      const hashedPassword = hashPassword(password);
      
      dbRun('users', {
        id: userId,
        email,
        phone,
        password: hashedPassword,
        name,
        role: 'patient',
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      });

      // Generate OTP
      const otp = generateOTP();
      const otpId = uuidv4();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000); // 15 minutes
      
      dbRun('otpCodes', {
        id: otpId,
        user_id: userId,
        code: otp,
        type: 'register',
        expires_at: expiresAt.toISOString(),
        created_at: new Date().toISOString()
      });

      res.status(201).json({
        message: 'Registration successful. Please verify OTP.',
        userId,
        otp // In production, send via SMS/Email
      });
    } catch (error) {
      res.status(500).json({ message: 'Registration failed', error: error.message });
    }
  },

  registerResendOtp: async (req, res) => {
    try {
      const { userId } = req.body;
      
      const otp = generateOTP();
      const otpId = uuidv4();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000);
      
      dbRun('otpCodes', {
        id: otpId,
        user_id: userId,
        code: otp,
        type: 'register',
        expires_at: expiresAt.toISOString(),
        created_at: new Date().toISOString()
      });

      res.json({ message: 'OTP resent', otp });
    } catch (error) {
      res.status(500).json({ message: 'Failed to resend OTP', error: error.message });
    }
  },

  registerVerifyOtp: async (req, res) => {
    try {
      const { userId, otp } = req.body;
      
      const validOtp = dbGet('otpCodes', o => 
        o.user_id === userId && 
        o.code === otp && 
        o.type === 'register' && 
        new Date(o.expires_at) > new Date() && 
        !o.used_at
      );

      if (!validOtp) {
        return res.status(400).json({ message: 'Invalid or expired OTP' });
      }

      // Mark OTP as used
      dbUpdate('otpCodes', o => o.id === validOtp.id, o => ({ ...o, used_at: new Date().toISOString() }));
      
      // Activate user
      dbUpdate('users', u => u.id === userId, u => ({ ...u, status: 'active' }));

      res.json({ message: 'Account verified successfully' });
    } catch (error) {
      res.status(500).json({ message: 'Verification failed', error: error.message });
    }
  },

  login: async (req, res) => {
    try {
      const { phone, password } = req.body;
      
      const user = dbGet('users', u => u.phone === phone);
      if (!user) {
        return res.status(401).json({ message: 'Invalid credentials' });
      }

      const validPassword = user.password === hashPassword(password);
      if (!validPassword) {
        return res.status(401).json({ message: 'Invalid credentials' });
      }

      if (user.status !== 'active') {
        return res.status(403).json({ message: 'Account not active' });
      }

      // Generate OTP for login
      const otp = generateOTP();
      const otpId = uuidv4();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000);
      
      dbRun('otpCodes', {
        id: otpId,
        user_id: user.id,
        code: otp,
        type: 'login',
        expires_at: expiresAt.toISOString(),
        created_at: new Date().toISOString()
      });

      res.json({ message: 'OTP sent for login verification', userId: user.id, otp });
    } catch (error) {
      res.status(500).json({ message: 'Login failed', error: error.message });
    }
  },

  loginResendOtp: async (req, res) => {
    try {
      const { userId } = req.body;
      
      const otp = generateOTP();
      const otpId = uuidv4();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000);
      
      dbRun('otpCodes', {
        id: otpId,
        user_id: userId,
        code: otp,
        type: 'login',
        expires_at: expiresAt.toISOString(),
        created_at: new Date().toISOString()
      });

      res.json({ message: 'Login OTP resent', otp });
    } catch (error) {
      res.status(500).json({ message: 'Failed to resend OTP', error: error.message });
    }
  },

  loginVerifyOtp: async (req, res) => {
    try {
      const { userId, otp } = req.body;
      
      const validOtp = dbGet('otpCodes', o => 
        o.user_id === userId && 
        o.code === otp && 
        o.type === 'login' && 
        new Date(o.expires_at) > new Date() && 
        !o.used_at
      );

      if (!validOtp) {
        return res.status(400).json({ message: 'Invalid or expired OTP' });
      }

      // Mark OTP as used
      dbUpdate('otpCodes', o => o.id === validOtp.id, o => ({ ...o, used_at: new Date().toISOString() }));
      
      // Generate tokens
      const token = jwt.sign({ userId }, process.env.JWT_SECRET || 'your-secret-key', { expiresIn: '1h' });
      const refreshToken = jwt.sign({ userId }, process.env.JWT_SECRET || 'your-secret-key', { expiresIn: '7d' });
      
      // Save session
      const sessionId = uuidv4();
      const expiresAt = new Date(Date.now() + 60 * 60 * 1000); // 1 hour
      
      dbRun('sessions', {
        id: sessionId,
        user_id: userId,
        token,
        refresh_token: refreshToken,
        expires_at: expiresAt.toISOString(),
        created_at: new Date().toISOString()
      });

      res.json({ token, refreshToken, userId });
    } catch (error) {
      res.status(500).json({ message: 'Login verification failed', error: error.message });
    }
  },

  refresh: async (req, res) => {
    try {
      const { refreshToken } = req.body;
      
      const decoded = jwt.verify(refreshToken, process.env.JWT_SECRET || 'your-secret-key');
      
      const session = dbGet('sessions', s => 
        s.refresh_token === refreshToken && 
        s.user_id === decoded.userId && 
        new Date(s.expires_at) > new Date()
      );

      if (!session) {
        return res.status(401).json({ message: 'Invalid refresh token' });
      }

      const newToken = jwt.sign({ userId: decoded.userId }, process.env.JWT_SECRET || 'your-secret-key', { expiresIn: '1h' });
      
      dbUpdate('sessions', s => s.id === session.id, s => ({
        ...s,
        token: newToken,
        expires_at: new Date(Date.now() + 60 * 60 * 1000).toISOString()
      }));

      res.json({ token: newToken });
    } catch (error) {
      res.status(401).json({ message: 'Invalid refresh token' });
    }
  },

  logout: async (req, res) => {
    try {
      const { token } = req.body;
      
      dbDelete('sessions', s => s.token === token);
      
      res.json({ message: 'Logged out successfully' });
    } catch (error) {
      res.status(500).json({ message: 'Logout failed', error: error.message });
    }
  },

  passwordResetInitiate: async (req, res) => {
    try {
      const { phone } = req.body;
      
      const user = dbGet('users', u => u.phone === phone);
      if (!user) {
        return res.status(404).json({ message: 'User not found' });
      }

      const otp = generateOTP();
      const otpId = uuidv4();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000);
      
      dbRun('otpCodes', {
        id: otpId,
        user_id: user.id,
        code: otp,
        type: 'password_reset',
        expires_at: expiresAt.toISOString(),
        created_at: new Date().toISOString()
      });

      res.json({ message: 'Password reset OTP sent', userId: user.id, otp });
    } catch (error) {
      res.status(500).json({ message: 'Password reset failed', error: error.message });
    }
  },

  passwordResetResendOtp: async (req, res) => {
    try {
      const { userId } = req.body;
      
      const otp = generateOTP();
      const otpId = uuidv4();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000);
      
      dbRun('otpCodes', {
        id: otpId,
        user_id: userId,
        code: otp,
        type: 'password_reset',
        expires_at: expiresAt.toISOString(),
        created_at: new Date().toISOString()
      });

      res.json({ message: 'Password reset OTP resent', otp });
    } catch (error) {
      res.status(500).json({ message: 'Failed to resend OTP', error: error.message });
    }
  },

  passwordResetConfirm: async (req, res) => {
    try {
      const { userId, otp, newPassword } = req.body;
      
      const validOtp = dbGet('otpCodes', o => 
        o.user_id === userId && 
        o.code === otp && 
        o.type === 'password_reset' && 
        new Date(o.expires_at) > new Date() && 
        !o.used_at
      );

      if (!validOtp) {
        return res.status(400).json({ message: 'Invalid or expired OTP' });
      }

      // Mark OTP as used
      dbUpdate('otpCodes', o => o.id === validOtp.id, o => ({ ...o, used_at: new Date().toISOString() }));
      
      // Update password
      const hashedPassword = hashPassword(newPassword);
      dbUpdate('users', u => u.id === userId, u => ({ ...u, password: hashedPassword }));

      res.json({ message: 'Password reset successful' });
    } catch (error) {
      res.status(500).json({ message: 'Password reset failed', error: error.message });
    }
  }
};

module.exports = authController;