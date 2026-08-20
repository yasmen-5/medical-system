const { dbGet, dbRun, dbQuery, dbUpdate, dbDelete } = require('../database/setup');
const { v4: uuidv4 } = require('uuid');
const path = require('path');
const fs = require('fs');

const patientController = {
  medicalIdentity: (req, res) => {
    try {
      const userId = req.userId;
      const user = dbGet('users', u => u.id === userId);
      
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
      const user = dbGet('users', u => u.id === userId);
      
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
      const encounters = dbQuery('medicalEncounters', e => e.user_id === userId);

      res.json({ encounters });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get medical history', error: error.message });
    }
  },

  medicalHistoryEncounter: (req, res) => {
    try {
      const userId = req.userId;
      const { encounterId } = req.params;
      
      const encounter = dbGet('medicalEncounters', e => e.id === encounterId && e.user_id === userId);

      if (!encounter) {
        return res.status(404).json({ message: 'Encounter not found' });
      }

      // Get encounter details
      const details = dbQuery('encounterDetails', d => d.encounter_id === encounterId);

      res.json({ encounter, details });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get encounter details', error: error.message });
    }
  },

  reminders: (req, res) => {
    try {
      const userId = req.userId;
      
      const reminders = dbQuery('reminders', r => r.user_id === userId);

      res.json({ reminders });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get reminders', error: error.message });
    }
  },

  activeReminders: (req, res) => {
    try {
      const userId = req.userId;
      
      const reminders = dbQuery('reminders', r => 
        r.user_id === userId && 
        r.status === 'active' && 
        new Date(r.reminder_time) > new Date()
      );

      res.json({ reminders });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get active reminders', error: error.message });
    }
  },

  homeReminderCounters: (req, res) => {
    try {
      const userId = req.userId;
      
      const total = dbQuery('reminders', r => r.user_id === userId).length;
      const active = dbQuery('reminders', r => r.user_id === userId && r.status === 'active').length;
      const completed = dbQuery('reminders', r => r.user_id === userId && r.status === 'completed').length;

      res.json({ total, active, completed });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get reminder counters', error: error.message });
    }
  },

  todaySchedule: (req, res) => {
    try {
      const userId = req.userId;
      const today = new Date().toISOString().split('T')[0];
      
      const schedule = dbQuery('reminders', r => 
        r.user_id === userId && 
        new Date(r.reminder_time).toISOString().split('T')[0] === today
      );

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
      
      const reminder = dbGet('reminders', r => r.id === reminderId && r.user_id === userId);

      if (!reminder) {
        return res.status(404).json({ message: 'Reminder not found' });
      }

      dbUpdate('reminders', r => r.id === reminderId, r => ({
        ...r,
        status: status || r.status,
        reminder_time: reminder_time || r.reminder_time,
        notes: notes || r.notes,
        updated_at: new Date().toISOString()
      }));

      res.json({ message: 'Reminder updated successfully' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to update reminder', error: error.message });
    }
  },

  notifications: (req, res) => {
    try {
      const userId = req.userId;
      
      const notifications = dbQuery('notifications', n => n.user_id === userId)
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 50);

      res.json({ notifications });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get notifications', error: error.message });
    }
  },

  pendingNotifications: (req, res) => {
    try {
      const userId = req.userId;
      
      const notifications = dbQuery('notifications', n => n.user_id === userId && !n.read_at)
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

      res.json({ notifications });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get pending notifications', error: error.message });
    }
  },

  markNotificationRead: (req, res) => {
    try {
      const userId = req.userId;
      const { notificationId } = req.params;
      
      const notification = dbGet('notifications', n => n.id === notificationId && n.user_id === userId);

      if (!notification) {
        return res.status(404).json({ message: 'Notification not found' });
      }

      dbUpdate('notifications', n => n.id === notificationId, n => ({ ...n, read_at: new Date().toISOString() }));

      res.json({ message: 'Notification marked as read' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to mark notification as read', error: error.message });
    }
  },

  healthJournalDiagnoses: (req, res) => {
    try {
      const userId = req.userId;
      
      const diagnoses = dbQuery('healthJournalDiagnoses', d => d.user_id === userId)
        .sort((a, b) => new Date(b.diagnosed_date) - new Date(a.diagnosed_date));

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
      
      dbRun('healthJournalNotes', {
        id: noteId,
        userId,
        diagnosisId,
        note,
        symptoms,
        severity,
        created_at: new Date().toISOString()
      });

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
      
      const notes = dbQuery('healthJournalNotes', n => n.user_id === userId)
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 20);

      res.json({ notes });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get health journal note summary', error: error.message });
    }
  },

  healthJournalNotes: (req, res) => {
    try {
      const userId = req.userId;
      const { diagnosisId } = req.params;
      
      const notes = dbQuery('healthJournalNotes', n => n.user_id === userId && n.diagnosis_id === diagnosisId)
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

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
      
      dbUpdate('users', u => u.id === userId, u => ({ ...u, profile_picture: profilePictureUrl }));

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
      const user = dbGet('users', u => u.id === userId);
      
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
      
      dbRun('emergencyContacts', {
        id: contactId,
        userId,
        name,
        phone,
        relationship,
        created_at: new Date().toISOString()
      });

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
      
      const contact = dbGet('emergencyContacts', c => c.id === contactId && c.userId === userId);

      if (!contact) {
        return res.status(404).json({ message: 'Emergency contact not found' });
      }

      dbDelete('emergencyContacts', c => c.id === contactId);

      res.json({ message: 'Emergency contact removed successfully' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to remove emergency contact', error: error.message });
    }
  }
};

module.exports = patientController;