const express = require('express');
const cors = require('cors');

const app = express();

// Middleware
app.use(cors());
app.use(express.json());

// Simple health check
app.get('/health', (req, res) => {
  res.json({ status: 'ok', message: 'API is running' });
});

// Simple test endpoint
app.get('/api/test', (req, res) => {
  res.json({ message: 'Test endpoint working' });
});

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
