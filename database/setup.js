const initSqlJs = require('sql.js');
const path = require('path');
const fs = require('fs');

let db = null;
let dbInitialized = false;

async function initializeDatabase() {
  if (dbInitialized) {
    return db;
  }

  try {
    const SQL = await initSqlJs();
    
    // For Vercel, use in-memory database
    if (process.env.VERCEL) {
      db = new SQL.Database();
      console.log('Using in-memory database for Vercel');
    } else {
      // For local development, use file-based database
      const dbPath = path.join(__dirname, '../database.sqlite');
      const fileBuffer = fs.existsSync(dbPath) ? fs.readFileSync(dbPath) : null;
      db = new SQL.Database(fileBuffer);
      console.log('Connected to SQLite database');
    }

    // Create tables
    createTables();
    
    dbInitialized = true;
    return db;
  } catch (error) {
    console.error('Failed to initialize database:', error);
    throw error;
  }
}

function createTables() {
  try {
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    `);

    // AI Chat sessions
    db.run(`
      CREATE TABLE IF NOT EXISTS ai_chat_sessions (
        id TEXT PRIMARY KEY,
        user_id TEXT NOT NULL,
        title TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    `);

    // AI Chat messages
    db.run(`
      CREATE TABLE IF NOT EXISTS ai_chat_messages (
        id TEXT PRIMARY KEY,
        session_id TEXT NOT NULL,
        role TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    `);

    // Encounter details
    db.run(`
      CREATE TABLE IF NOT EXISTS encounter_details (
        id TEXT PRIMARY KEY,
        encounter_id TEXT NOT NULL,
        detail_type TEXT NOT NULL,
        detail_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    `);

    console.log('Database tables created successfully');
  } catch (error) {
    console.error('Error creating tables:', error);
    throw error;
  }
}

// Helper functions for database operations
const dbQuery = (sql, params = []) => {
  try {
    if (!db) {
      throw new Error('Database not initialized');
    }
    const stmt = db.prepare(sql);
    const result = stmt.bind(...params);
    return result.getAsObject({ columns: true });
  } catch (error) {
    console.error('Query error:', error);
    throw error;
  }
};

const dbRun = (sql, params = []) => {
  try {
    if (!db) {
      throw new Error('Database not initialized');
    }
    db.run(sql, params);
    return { success: true };
  } catch (error) {
    console.error('Run error:', error);
    throw error;
  }
};

const dbGet = (sql, params = []) => {
  try {
    if (!db) {
      throw new Error('Database not initialized');
    }
    const stmt = db.prepare(sql);
    const result = stmt.bind(...params);
    const rows = result.getAsObject({ columns: true });
    return rows.length > 0 ? rows[0] : null;
  } catch (error) {
    console.error('Get error:', error);
    throw error;
  }
};

module.exports = { initializeDatabase, dbQuery, dbRun, dbGet };

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