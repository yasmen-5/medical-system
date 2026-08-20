const express = require('express');
const router = express.Router();
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const authController = require('../controllers/authController');
const patientController = require('../controllers/patientController');
const patientAiController = require('../controllers/patientAiController');
const { patientAccess } = require('../middleware/auth');

// File upload configuration
const storage = multer.memoryStorage(); // Use memory storage for Vercel

const upload = multer({ 
  storage: storage,
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB limit
  fileFilter: (req, file, cb) => {
    const allowedTypes = /jpeg|jpg|png|gif/;
    const extname = allowedTypes.test(path.extname(file.originalname).toLowerCase());
    const mimetype = allowedTypes.test(file.mimetype);
    
    if (mimetype && extname) {
      return cb(null, true);
    } else {
      cb(new Error('Images only!'));
    }
  }
});

// Auth routes
router.post('/auth/register', authController.register);
router.post('/auth/register/resend-otp', authController.registerResendOtp);
router.post('/auth/register/verify-otp', authController.registerVerifyOtp);
router.post('/auth/login', authController.login);
router.post('/auth/login/resend-otp', authController.loginResendOtp);
router.post('/auth/login/verify-otp', authController.loginVerifyOtp);
router.post('/auth/refresh', authController.refresh);
router.post('/auth/logout', authController.logout);
router.post('/auth/password-reset', authController.passwordResetInitiate);
router.post('/auth/password-reset/resend-otp', authController.passwordResetResendOtp);
router.post('/auth/password-reset/confirm', authController.passwordResetConfirm);

// Patient routes (require authentication)
router.use(patientAccess);

router.get('/medical-identity', patientController.medicalIdentity);
router.get('/name', patientController.name);
router.get('/medical-history', patientController.medicalHistory);
router.get('/medical-history/:encounterId', patientController.medicalHistoryEncounter);
router.get('/reminders', patientController.reminders);
router.get('/reminders/active', patientController.activeReminders);
router.get('/home/reminders/counters', patientController.homeReminderCounters);
router.get('/home/today-schedule', patientController.todaySchedule);
router.patch('/reminders/:reminderId', patientController.updateReminder);
router.get('/notifications', patientController.notifications);
router.get('/notifications/pending', patientController.pendingNotifications);
router.patch('/notifications/:notificationId/read', patientController.markNotificationRead);
router.get('/health-journal/diagnoses', patientController.healthJournalDiagnoses);
router.post('/health-journal/notes', patientController.createHealthJournalNote);
router.get('/health-journal/notes', patientController.healthJournalNoteSummary);
router.get('/health-journal/notes/:diagnosisId', patientController.healthJournalNotes);
router.post('/profile-picture', upload.single('profilePicture'), patientController.uploadProfilePicture);
router.get('/profile-picture', patientController.profilePicture);
router.post('/emergency-contacts', patientController.addEmergencyContact);
router.delete('/emergency-contacts/:contactId', patientController.removeEmergencyContact);

// AI Chat routes
router.post('/ai/chat/sessions', patientAiController.createSession);
router.get('/ai/chat/sessions', patientAiController.listSessions);
router.get('/ai/chat/sessions/:sessionId', patientAiController.getSession);
router.patch('/ai/chat/sessions/:sessionId', patientAiController.updateSession);
router.delete('/ai/chat/sessions', patientAiController.deleteAllSessions);
router.delete('/ai/chat/sessions/:sessionId', patientAiController.deleteSession);
router.post('/ai/chat/sessions/:sessionId/messages', patientAiController.sendMessage);

module.exports = router;