const express = require('express');
const cors = require('cors');
const { initializeDatabase } = require('../database/setup');

const app = express();

// Middleware
app.use(cors());
app.use(express.json());

// Initialize database
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

// Simple health check
app.get('/health', (req, res) => {
  res.json({ status: 'ok', message: 'API is running with database' });
});

// Simple test endpoint
app.get('/api/test', (req, res) => {
  res.json({ message: 'Test endpoint working with database' });
});

// API Routes
app.use('/api/v1/patient', require('../routes/patient'));

// 404 handler
app.use((req, res) => {
  res.status(404).json({ message: 'Route not found' });
});

// Error handler
app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ message: 'Internal server error', error: err.message });
});

module.exports = app;
