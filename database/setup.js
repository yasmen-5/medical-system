// Simple in-memory database using JavaScript arrays for Vercel compatibility
const { v4: uuidv4 } = require('uuid');

// Database tables as simple arrays
let users = [];
let otpCodes = [];
let sessions = [];
let aiChatSessions = [];
let aiChatMessages = [];
let medicalEncounters = [];
let encounterDetails = [];
let reminders = [];
let notifications = [];
let healthJournalDiagnoses = [];
let healthJournalNotes = [];
let emergencyContacts = [];

function initializeDatabase() {
  console.log('Using in-memory array-based database for Vercel');
  return Promise.resolve();
}

// Helper functions for database operations
const dbQuery = (table, filterFn = () => true) => {
  const tableMap = {
    users,
    otpCodes,
    sessions,
    aiChatSessions,
    aiChatMessages,
    medicalEncounters,
    encounterDetails,
    reminders,
    notifications,
    healthJournalDiagnoses,
    healthJournalNotes,
    emergencyContacts
  };
  
  const tableData = tableMap[table] || [];
  return tableData.filter(filterFn);
};

const dbRun = (table, item) => {
  const tableMap = {
    users,
    otpCodes,
    sessions,
    aiChatSessions,
    aiChatMessages,
    medicalEncounters,
    encounterDetails,
    reminders,
    notifications,
    healthJournalDiagnoses,
    healthJournalNotes,
    emergencyContacts
  };
  
  if (!tableMap[table]) {
    throw new Error(`Table ${table} not found`);
  }
  
  tableMap[table].push(item);
  return { success: true };
};

const dbGet = (table, filterFn) => {
  const tableMap = {
    users,
    otpCodes,
    sessions,
    aiChatSessions,
    aiChatMessages,
    medicalEncounters,
    encounterDetails,
    reminders,
    notifications,
    healthJournalDiagnoses,
    healthJournalNotes,
    emergencyContacts
  };
  
  const tableData = tableMap[table] || [];
  const results = tableData.filter(filterFn);
  return results.length > 0 ? results[0] : null;
};

const dbUpdate = (table, filterFn, updateFn) => {
  const tableMap = {
    users,
    otpCodes,
    sessions,
    aiChatSessions,
    aiChatMessages,
    medicalEncounters,
    encounterDetails,
    reminders,
    notifications,
    healthJournalDiagnoses,
    healthJournalNotes,
    emergencyContacts
  };
  
  const tableData = tableMap[table] || [];
  const index = tableData.findIndex(filterFn);
  
  if (index !== -1) {
    tableData[index] = { ...tableData[index], ...updateFn(tableData[index]) };
    return { success: true };
  }
  
  return { success: false };
};

const dbDelete = (table, filterFn) => {
  const tableMap = {
    users,
    otpCodes,
    sessions,
    aiChatSessions,
    aiChatMessages,
    medicalEncounters,
    encounterDetails,
    reminders,
    notifications,
    healthJournalDiagnoses,
    healthJournalNotes,
    emergencyContacts
  };
  
  const tableData = tableMap[table] || [];
  const initialLength = tableData.length;
  const filtered = tableData.filter(filterFn);
  
  tableMap[table] = filtered;
  return { success: true, deleted: initialLength - filtered.length };
};

module.exports = { 
  initializeDatabase, 
  dbQuery, 
  dbRun, 
  dbGet, 
  dbUpdate, 
  dbDelete 
};

// Initialize database tables
function initializeDatabase() {
  return new Promise((resolve, reject) => {
    db.serialize(() => {
      // Users table
      db.run(`
        CREATE TABLE IF NOT EXISTS users (
          id TEXT PRIMARY KEY,
          email TEXT UNIQUE NOT NULL,
          phone TEXT UNIQUE NOT NULL,
          password TEXT NOT NULL,
          name TEXT,
          role TEXT DEFAULT 'patient',
          status TEXT DEFAULT 'pending',
          profile_picture TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
      `);

      // OTP codes table
      db.run(`
        CREATE TABLE IF NOT EXISTS otp_codes (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          code TEXT NOT NULL,
          type TEXT NOT NULL,
          expires_at DATETIME NOT NULL,
          used_at DATETIME,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id)
        )
      `);

      // Sessions table
      db.run(`
        CREATE TABLE IF NOT EXISTS sessions (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          token TEXT UNIQUE NOT NULL,
          refresh_token TEXT UNIQUE NOT NULL,
          expires_at DATETIME NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id)
        )
      `);

      // AI Chat sessions
      db.run(`
        CREATE TABLE IF NOT EXISTS ai_chat_sessions (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          title TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id)
        )
      `);

      // AI Chat messages
      db.run(`
        CREATE TABLE IF NOT EXISTS ai_chat_messages (
          id TEXT PRIMARY KEY,
          session_id TEXT NOT NULL,
          role TEXT NOT NULL,
          content TEXT NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (session_id) REFERENCES ai_chat_sessions(id)
        )
      `);

      // Medical encounters
      db.run(`
        CREATE TABLE IF NOT EXISTS medical_encounters (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          encounter_date DATETIME NOT NULL,
          encounter_type TEXT,
          provider_name TEXT,
          chief_complaint TEXT,
          notes TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id)
        )
      `);

      // Encounter details
      db.run(`
        CREATE TABLE IF NOT EXISTS encounter_details (
          id TEXT PRIMARY KEY,
          encounter_id TEXT NOT NULL,
          detail_type TEXT NOT NULL,
          detail_value TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (encounter_id) REFERENCES medical_encounters(id)
        )
      `);

      // Reminders
      db.run(`
        CREATE TABLE IF NOT EXISTS reminders (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          reminder_time DATETIME NOT NULL,
          reminder_type TEXT,
          title TEXT,
          description TEXT,
          status TEXT DEFAULT 'active',
          notes TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id)
        )
      `);

      // Notifications
      db.run(`
        CREATE TABLE IF NOT EXISTS notifications (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          title TEXT NOT NULL,
          message TEXT,
          notification_type TEXT,
          read_at DATETIME,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id)
        )
      `);

      // Health journal diagnoses
      db.run(`
        CREATE TABLE IF NOT EXISTS health_journal_diagnoses (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          diagnosis_name TEXT NOT NULL,
          diagnosis_code TEXT,
          diagnosed_date DATETIME,
          notes TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id)
        )
      `);

      // Health journal notes
      db.run(`
        CREATE TABLE IF NOT EXISTS health_journal_notes (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          diagnosis_id TEXT,
          note TEXT NOT NULL,
          symptoms TEXT,
          severity TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id),
          FOREIGN KEY (diagnosis_id) REFERENCES health_journal_diagnoses(id)
        )
      `);

      // Emergency contacts
      db.run(`
        CREATE TABLE IF NOT EXISTS emergency_contacts (
          id TEXT PRIMARY KEY,
          user_id TEXT NOT NULL,
          name TEXT NOT NULL,
          phone TEXT NOT NULL,
          relationship TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id)
        )
      `, (err) => {
        if (err) {
          console.error('Error initializing database:', err.message);
          reject(err);
        } else {
          console.log('Database initialized successfully');
          resolve();
        }
      });
    });
  });
}

// Helper functions for database operations
const dbQuery = (sql, params = []) => {
  return new Promise((resolve, reject) => {
    db.all(sql, params, (err, rows) => {
      if (err) reject(err);
      else resolve(rows);
    });
  });
};

const dbRun = (sql, params = []) => {
  return new Promise((resolve, reject) => {
    db.run(sql, params, function(err) {
      if (err) reject(err);
      else resolve({ lastID: this.lastID, changes: this.changes });
    });
  });
};

const dbGet = (sql, params = []) => {
  return new Promise((resolve, reject) => {
    db.get(sql, params, (err, row) => {
      if (err) reject(err);
      else resolve(row);
    });
  });
};

module.exports = { db, initializeDatabase, dbQuery, dbRun, dbGet };