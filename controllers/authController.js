const { db } = require('../database/setup');
const { v4: uuidv4 } = require('uuid');
const bcrypt = require('bcrypt');
const jwt = require('jsonwebtoken');

const generateOTP = () => Math.floor(100000 + Math.random() * 900000).toString();

const authController = {
  register: async (req, res) => {
    try {
      const { email, phone, password, name } = req.body;
      
      // Check if user exists
      const existingUser = db.prepare('SELECT id FROM users WHERE email = ? OR phone = ?').get(email, phone);
      if (existingUser) {
        return res.status(400).json({ message: 'User already exists' });
      }

      const userId = uuidv4();
      const hashedPassword = await bcrypt.hash(password, 12);
      
      db.prepare(`
        INSERT INTO users (id, email, phone, password, name, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
      `).run(userId, email, phone, hashedPassword, name);

      // Generate OTP
      const otp = generateOTP();
      const otpId = uuidv4();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000); // 15 minutes
      
      db.prepare(`
        INSERT INTO otp_codes (id, user_id, code, type, expires_at)
        VALUES (?, ?, ?, 'register', ?)
      `).run(otpId, userId, otp, expiresAt.toISOString());

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
      
      db.prepare(`
        INSERT INTO otp_codes (id, user_id, code, type, expires_at)
        VALUES (?, ?, ?, 'register', ?)
      `).run(otpId, userId, otp, expiresAt.toISOString());

      res.json({ message: 'OTP resent', otp });
    } catch (error) {
      res.status(500).json({ message: 'Failed to resend OTP', error: error.message });
    }
  },

  registerVerifyOtp: async (req, res) => {
    try {
      const { userId, otp } = req.body;
      
      const validOtp = db.prepare(`
        SELECT * FROM otp_codes 
        WHERE user_id = ? AND code = ? AND type = 'register' 
        AND expires_at > datetime('now') AND used_at IS NULL
        ORDER BY created_at DESC LIMIT 1
      `).get(userId, otp);

      if (!validOtp) {
        return res.status(400).json({ message: 'Invalid or expired OTP' });
      }

      // Mark OTP as used
      db.prepare('UPDATE otp_codes SET used_at = datetime("now") WHERE id = ?').run(validOtp.id);
      
      // Activate user
      db.prepare('UPDATE users SET status = "active" WHERE id = ?').run(userId);

      res.json({ message: 'Account verified successfully' });
    } catch (error) {
      res.status(500).json({ message: 'Verification failed', error: error.message });
    }
  },

  login: async (req, res) => {
    try {
      const { phone, password } = req.body;
      
      const user = db.prepare('SELECT * FROM users WHERE phone = ?').get(phone);
      if (!user) {
        return res.status(401).json({ message: 'Invalid credentials' });
      }

      const validPassword = await bcrypt.compare(password, user.password);
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
      
      db.prepare(`
        INSERT INTO otp_codes (id, user_id, code, type, expires_at)
        VALUES (?, ?, ?, 'login', ?)
      `).run(otpId, user.id, otp, expiresAt.toISOString());

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
      
      db.prepare(`
        INSERT INTO otp_codes (id, user_id, code, type, expires_at)
        VALUES (?, ?, ?, 'login', ?)
      `).run(otpId, userId, otp, expiresAt.toISOString());

      res.json({ message: 'Login OTP resent', otp });
    } catch (error) {
      res.status(500).json({ message: 'Failed to resend OTP', error: error.message });
    }
  },

  loginVerifyOtp: async (req, res) => {
    try {
      const { userId, otp } = req.body;
      
      const validOtp = db.prepare(`
        SELECT * FROM otp_codes 
        WHERE user_id = ? AND code = ? AND type = 'login' 
        AND expires_at > datetime('now') AND used_at IS NULL
        ORDER BY created_at DESC LIMIT 1
      `).get(userId, otp);

      if (!validOtp) {
        return res.status(400).json({ message: 'Invalid or expired OTP' });
      }

      // Mark OTP as used
      db.prepare('UPDATE otp_codes SET used_at = datetime("now") WHERE id = ?').run(validOtp.id);
      
      // Generate tokens
      const token = jwt.sign({ userId }, process.env.JWT_SECRET || 'your-secret-key', { expiresIn: '1h' });
      const refreshToken = jwt.sign({ userId }, process.env.JWT_SECRET || 'your-secret-key', { expiresIn: '7d' });
      
      // Save session
      const sessionId = uuidv4();
      const expiresAt = new Date(Date.now() + 60 * 60 * 1000); // 1 hour
      
      db.prepare(`
        INSERT INTO sessions (id, user_id, token, refresh_token, expires_at)
        VALUES (?, ?, ?, ?, ?)
      `).run(sessionId, userId, token, refreshToken, expiresAt.toISOString());

      res.json({ token, refreshToken, userId });
    } catch (error) {
      res.status(500).json({ message: 'Login verification failed', error: error.message });
    }
  },

  refresh: async (req, res) => {
    try {
      const { refreshToken } = req.body;
      
      const decoded = jwt.verify(refreshToken, process.env.JWT_SECRET || 'your-secret-key');
      
      const session = db.prepare(`
        SELECT * FROM sessions 
        WHERE refresh_token = ? AND user_id = ? AND expires_at > datetime('now')
      `).get(refreshToken, decoded.userId);

      if (!session) {
        return res.status(401).json({ message: 'Invalid refresh token' });
      }

      const newToken = jwt.sign({ userId: decoded.userId }, process.env.JWT_SECRET || 'your-secret-key', { expiresIn: '1h' });
      
      db.prepare('UPDATE sessions SET token = ?, expires_at = ? WHERE id = ?')
        .run(newToken, new Date(Date.now() + 60 * 60 * 1000).toISOString(), session.id);

      res.json({ token: newToken });
    } catch (error) {
      res.status(401).json({ message: 'Invalid refresh token' });
    }
  },

  logout: async (req, res) => {
    try {
      const { token } = req.body;
      
      db.prepare('DELETE FROM sessions WHERE token = ?').run(token);
      
      res.json({ message: 'Logged out successfully' });
    } catch (error) {
      res.status(500).json({ message: 'Logout failed', error: error.message });
    }
  },

  passwordResetInitiate: async (req, res) => {
    try {
      const { phone } = req.body;
      
      const user = db.prepare('SELECT * FROM users WHERE phone = ?').get(phone);
      if (!user) {
        return res.status(404).json({ message: 'User not found' });
      }

      const otp = generateOTP();
      const otpId = uuidv4();
      const expiresAt = new Date(Date.now() + 15 * 60 * 1000);
      
      db.prepare(`
        INSERT INTO otp_codes (id, user_id, code, type, expires_at)
        VALUES (?, ?, ?, 'password_reset', ?)
      `).run(otpId, user.id, otp, expiresAt.toISOString());

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
      
      db.prepare(`
        INSERT INTO otp_codes (id, user_id, code, type, expires_at)
        VALUES (?, ?, ?, 'password_reset', ?)
      `).run(otpId, userId, otp, expiresAt.toISOString());

      res.json({ message: 'Password reset OTP resent', otp });
    } catch (error) {
      res.status(500).json({ message: 'Failed to resend OTP', error: error.message });
    }
  },

  passwordResetConfirm: async (req, res) => {
    try {
      const { userId, otp, newPassword } = req.body;
      
      const validOtp = db.prepare(`
        SELECT * FROM otp_codes 
        WHERE user_id = ? AND code = ? AND type = 'password_reset' 
        AND expires_at > datetime('now') AND used_at IS NULL
        ORDER BY created_at DESC LIMIT 1
      `).get(userId, otp);

      if (!validOtp) {
        return res.status(400).json({ message: 'Invalid or expired OTP' });
      }

      // Mark OTP as used
      db.prepare('UPDATE otp_codes SET used_at = datetime("now") WHERE id = ?').run(validOtp.id);
      
      // Update password
      const hashedPassword = await bcrypt.hash(newPassword, 12);
      db.prepare('UPDATE users SET password = ? WHERE id = ?').run(hashedPassword, userId);

      res.json({ message: 'Password reset successful' });
    } catch (error) {
      res.status(500).json({ message: 'Password reset failed', error: error.message });
    }
  }
};

module.exports = authController;