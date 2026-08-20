const { dbGet, dbRun, dbQuery } = require('../database/setup');
const { v4: uuidv4 } = require('uuid');
const path = require('path');
const fs = require('fs');

const patientController = {
  medicalIdentity: (req, res) => {
    try {
      const userId = req.userId;
      const user = dbGet('SELECT id, name, email, phone FROM users WHERE id = ?', [userId]);
      
      if (!user) {
        return res.status(404).json({ message: 'User not found' });
      }

      res.json({
        id: user.id,
        name: user.name,
        email: user.email,
        phone: user.phone,
        role: 'patient'
      });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get medical identity', error: error.message });
    }
  },

  name: (req, res) => {
    try {
      const userId = req.userId;
      const user = dbGet('SELECT name FROM users WHERE id = ?', [userId]);
      
      if (!user) {
        return res.status(404).json({ message: 'User not found' });
      }

      res.json({ name: user.name });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get name', error: error.message });
    }
  },

  medicalHistory: (req, res) => {
    try {
      const userId = req.userId;
      
      // Get all medical encounters for the user
      const encounters = dbQuery(`
        SELECT * FROM medical_encounters 
        WHERE user_id = ? 
        ORDER BY encounter_date DESC
      `, [userId]);

      res.json({ encounters });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get medical history', error: error.message });
    }
  },

  medicalHistoryEncounter: (req, res) => {
    try {
      const userId = req.userId;
      const { encounterId } = req.params;
      
      const encounter = dbGet(`
        SELECT * FROM medical_encounters 
        WHERE id = ? AND user_id = ?
      `, [encounterId, userId]);

      if (!encounter) {
        return res.status(404).json({ message: 'Encounter not found' });
      }

      // Get encounter details
      const details = dbQuery(`
        SELECT * FROM encounter_details 
        WHERE encounter_id = ?
      `, [encounterId]);

      res.json({ encounter, details });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get encounter details', error: error.message });
    }
  },

  reminders: (req, res) => {
    try {
      const userId = req.userId;
      
      const reminders = dbQuery(`
        SELECT * FROM reminders 
        WHERE user_id = ? 
        ORDER BY reminder_time ASC
      `, [userId]);

      res.json({ reminders });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get reminders', error: error.message });
    }
  },

  activeReminders: (req, res) => {
    try {
      const userId = req.userId;
      
      const reminders = dbQuery(`
        SELECT * FROM reminders 
        WHERE user_id = ? AND status = 'active' AND datetime(reminder_time) > datetime('now')
        ORDER BY reminder_time ASC
      `, [userId]);

      res.json({ reminders });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get active reminders', error: error.message });
    }
  },

  homeReminderCounters: (req, res) => {
    try {
      const userId = req.userId;
      
      const total = (dbGet('SELECT COUNT(*) as count FROM reminders WHERE user_id = ?', [userId])).count;
      const active = (dbGet('SELECT COUNT(*) as count FROM reminders WHERE user_id = ? AND status = "active"', [userId])).count;
      const completed = (dbGet('SELECT COUNT(*) as count FROM reminders WHERE user_id = ? AND status = "completed"', [userId])).count;

      res.json({ total, active, completed });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get reminder counters', error: error.message });
    }
  },

  todaySchedule: (req, res) => {
    try {
      const userId = req.userId;
      const today = new Date().toISOString().split('T')[0];
      
      const schedule = dbQuery(`
        SELECT * FROM reminders 
        WHERE user_id = ? AND date(reminder_time) = ?
        ORDER BY reminder_time ASC
      `, [userId, today]);

      res.json({ schedule });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get today schedule', error: error.message });
    }
  },

  updateReminder: (req, res) => {
    try {
      const userId = req.userId;
      const { reminderId } = req.params;
      const { status, reminder_time, notes } = req.body;
      
      const reminder = dbGet(`
        SELECT * FROM reminders 
        WHERE id = ? AND user_id = ?
      `, [reminderId, userId]);

      if (!reminder) {
        return res.status(404).json({ message: 'Reminder not found' });
      }

      dbRun(`
        UPDATE reminders 
        SET status = ?, reminder_time = ?, notes = ?, updated_at = datetime('now')
        WHERE id = ?
      `, [status || reminder.status, reminder_time || reminder.reminder_time, notes || reminder.notes, reminderId]);

      res.json({ message: 'Reminder updated successfully' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to update reminder', error: error.message });
    }
  },

  notifications: (req, res) => {
    try {
      const userId = req.userId;
      
      const notifications = dbQuery(`
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
        LIMIT 50
      `, [userId]);

      res.json({ notifications });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get notifications', error: error.message });
    }
  },

  pendingNotifications: (req, res) => {
    try {
      const userId = req.userId;
      
      const notifications = dbQuery(`
        SELECT * FROM notifications 
        WHERE user_id = ? AND read_at IS NULL
        ORDER BY created_at DESC
      `, [userId]);

      res.json({ notifications });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get pending notifications', error: error.message });
    }
  },

  markNotificationRead: (req, res) => {
    try {
      const userId = req.userId;
      const { notificationId } = req.params;
      
      const notification = dbGet(`
        SELECT * FROM notifications 
        WHERE id = ? AND user_id = ?
      `, [notificationId, userId]);

      if (!notification) {
        return res.status(404).json({ message: 'Notification not found' });
      }

      dbRun('UPDATE notifications SET read_at = datetime("now") WHERE id = ?', [notificationId]);

      res.json({ message: 'Notification marked as read' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to mark notification as read', error: error.message });
    }
  },

  healthJournalDiagnoses: (req, res) => {
    try {
      const userId = req.userId;
      
      const diagnoses = dbQuery(`
        SELECT * FROM health_journal_diagnoses 
        WHERE user_id = ? 
        ORDER BY diagnosed_date DESC
      `, [userId]);

      res.json({ diagnoses });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get health journal diagnoses', error: error.message });
    }
  },

  createHealthJournalNote: (req, res) => {
    try {
      const userId = req.userId;
      const { diagnosisId, note, symptoms, severity } = req.body;
      
      const noteId = uuidv4();
      
      dbRun(`
        INSERT INTO health_journal_notes (id, user_id, diagnosis_id, note, symptoms, severity)
        VALUES (?, ?, ?, ?, ?, ?)
      `, [noteId, userId, diagnosisId, note, symptoms, severity]);

      res.status(201).json({
        id: noteId,
        userId,
        diagnosisId,
        note,
        symptoms,
        severity,
        createdAt: new Date().toISOString()
      });
    } catch (error) {
      res.status(500).json({ message: 'Failed to create health journal note', error: error.message });
    }
  },

  healthJournalNoteSummary: (req, res) => {
    try {
      const userId = req.userId;
      
      const notes = dbQuery(`
        SELECT * FROM health_journal_notes 
        WHERE user_id = ? 
        ORDER BY created_at DESC
        LIMIT 20
      `, [userId]);

      res.json({ notes });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get health journal note summary', error: error.message });
    }
  },

  healthJournalNotes: (req, res) => {
    try {
      const userId = req.userId;
      const { diagnosisId } = req.params;
      
      const notes = dbQuery(`
        SELECT * FROM health_journal_notes 
        WHERE user_id = ? AND diagnosis_id = ?
        ORDER BY created_at DESC
      `, [userId, diagnosisId]);

      res.json({ notes });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get health journal notes', error: error.message });
    }
  },

  uploadProfilePicture: (req, res) => {
    try {
      const userId = req.userId;
      
      if (!req.file) {
        return res.status(400).json({ message: 'No file uploaded' });
      }

      // For Vercel, we'll just return a placeholder since we can't store files
      // In production, you would upload to a cloud service like Vercel Blob or AWS S3
      const profilePictureUrl = `/uploads/profile-${userId}.jpg`;
      
      dbRun('UPDATE users SET profile_picture = ? WHERE id = ?', [profilePictureUrl, userId]);

      res.json({ 
        message: 'Profile picture uploaded successfully',
        profilePicture: profilePictureUrl
      });
    } catch (error) {
      res.status(500).json({ message: 'Failed to upload profile picture', error: error.message });
    }
  },

  profilePicture: (req, res) => {
    try {
      const userId = req.userId;
      const user = dbGet('SELECT profile_picture FROM users WHERE id = ?', [userId]);
      
      if (!user) {
        return res.status(404).json({ message: 'User not found' });
      }

      res.json({ profilePicture: user.profile_picture });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get profile picture', error: error.message });
    }
  },

  addEmergencyContact: (req, res) => {
    try {
      const userId = req.userId;
      const { name, phone, relationship } = req.body;
      
      const contactId = uuidv4();
      
      dbRun(`
        INSERT INTO emergency_contacts (id, user_id, name, phone, relationship)
        VALUES (?, ?, ?, ?, ?)
      `, [contactId, userId, name, phone, relationship]);

      res.status(201).json({
        id: contactId,
        userId,
        name,
        phone,
        relationship,
        createdAt: new Date().toISOString()
      });
    } catch (error) {
      res.status(500).json({ message: 'Failed to add emergency contact', error: error.message });
    }
  },

  removeEmergencyContact: (req, res) => {
    try {
      const userId = req.userId;
      const { contactId } = req.params;
      
      const contact = dbGet(`
        SELECT * FROM emergency_contacts 
        WHERE id = ? AND user_id = ?
      `, [contactId, userId]);

      if (!contact) {
        return res.status(404).json({ message: 'Emergency contact not found' });
      }

      dbRun('DELETE FROM emergency_contacts WHERE id = ?', [contactId]);

      res.json({ message: 'Emergency contact removed successfully' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to remove emergency contact', error: error.message });
    }
  }
};

module.exports = patientController;