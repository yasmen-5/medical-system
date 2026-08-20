const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const { initializeDatabase } = require('../database/setup');

dotenv.config();

const app = express();

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Initialize database synchronously for serverless
let dbInitialized = false;
const ensureDbInitialized = (req, res, next) => {
  if (!dbInitialized) {
    initializeDatabase().then(() => {
      dbInitialized = true;
      next();
    }).catch(err => {
      console.error('Database initialization error:', err);
      res.status(500).json({ message: 'Database initialization failed' });
    });
  } else {
    next();
  }
};

app.use(ensureDbInitialized);

// Health check
app.get('/health', (req, res) => {
  res.json({ status: 'ok', message: 'Medical System API is running' });
});

// API Routes
app.use('/api/v1/patient', require('../routes/patient'));

// 404 handler
app.use((req, res) => {
  res.status(404).json({ message: 'Route not found' });
});

// Error handler
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({ message: 'Internal server error', error: err.message });
});

module.exports = app;
