const { db } = require('../database/setup');

const patientController = {
  medicalIdentity: (req, res) => {
    try {
      const userId = req.userId;
      const user = db.prepare('SELECT id, name, email, phone FROM users WHERE id = ?').get(userId);
      
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
      const user = db.prepare('SELECT name FROM users WHERE id = ?').get(userId);
      
      if (!user) {
        return res.status(404).json({ message: 'User not found' });
      }

      res.json({ name: user.name });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get name', error: error.message });
    }
  },

  medicalHistory: (req, res) => {
    res.json({ message: 'Medical history not implemented yet' });
  },

  medicalHistoryEncounter: (req, res) => {
    res.json({ message: 'Medical history encounter not implemented yet' });
  },

  reminders: (req, res) => {
    res.json({ reminders: [] });
  },

  activeReminders: (req, res) => {
    res.json({ reminders: [] });
  },

  homeReminderCounters: (req, res) => {
    res.json({ total: 0, active: 0, completed: 0 });
  },

  todaySchedule: (req, res) => {
    res.json({ schedule: [] });
  },

  updateReminder: (req, res) => {
    res.json({ message: 'Update reminder not implemented yet' });
  },

  notifications: (req, res) => {
    res.json({ notifications: [] });
  },

  pendingNotifications: (req, res) => {
    res.json({ notifications: [] });
  },

  markNotificationRead: (req, res) => {
    res.json({ message: 'Mark notification read not implemented yet' });
  },

  healthJournalDiagnoses: (req, res) => {
    res.json({ diagnoses: [] });
  },

  createHealthJournalNote: (req, res) => {
    res.json({ message: 'Create health journal note not implemented yet' });
  },

  healthJournalNoteSummary: (req, res) => {
    res.json({ notes: [] });
  },

  healthJournalNotes: (req, res) => {
    res.json({ notes: [] });
  },

  uploadProfilePicture: (req, res) => {
    res.json({ message: 'Upload profile picture not implemented yet' });
  },

  profilePicture: (req, res) => {
    res.json({ profilePicture: null });
  },

  addEmergencyContact: (req, res) => {
    res.json({ message: 'Add emergency contact not implemented yet' });
  },

  removeEmergencyContact: (req, res) => {
    res.json({ message: 'Remove emergency contact not implemented yet' });
  }
};

module.exports = patientController;