# Medical System API

A comprehensive medical system API built with Node.js, Express, and SQLite, designed for patient management, health tracking, and AI-powered medical assistance.

## Features

- 🔐 **Authentication System**: OTP-based registration and login
- 👤 **Patient Management**: Medical identity, profile management
- 📋 **Medical History**: Track medical encounters and diagnoses
- ⏰ **Reminders**: Medication and appointment reminders
- 🔔 **Notifications**: Real-time health notifications
- 📝 **Health Journal**: Track symptoms and health notes
- 🤖 **AI Chat**: Intelligent medical assistance chat
- 📱 **Emergency Contacts**: Manage emergency contacts
- 🖼️ **Profile Pictures**: Upload and manage profile images

## Tech Stack

- **Runtime**: Node.js
- **Framework**: Express.js
- **Database**: SQLite (better-sqlite3)
- **Authentication**: JWT + OTP
- **File Upload**: Multer
- **Deployment**: Vercel

## API Endpoints

### Authentication
- `POST /api/v1/patient/auth/register` - Register new user
- `POST /api/v1/patient/auth/register/resend-otp` - Resend registration OTP
- `POST /api/v1/patient/auth/register/verify-otp` - Verify registration OTP
- `POST /api/v1/patient/auth/login` - Login with phone
- `POST /api/v1/patient/auth/login/resend-otp` - Resend login OTP
- `POST /api/v1/patient/auth/login/verify-otp` - Verify login OTP
- `POST /api/v1/patient/auth/refresh` - Refresh access token
- `POST /api/v1/patient/auth/logout` - Logout
- `POST /api/v1/patient/auth/password-reset` - Initiate password reset
- `POST /api/v1/patient/auth/password-reset/resend-otp` - Resend reset OTP
- `POST /api/v1/patient/auth/password-reset/confirm` - Confirm password reset

### Patient Profile
- `GET /api/v1/patient/medical-identity` - Get medical identity
- `GET /api/v1/patient/name` - Get patient name
- `POST /api/v1/patient/profile-picture` - Upload profile picture
- `GET /api/v1/patient/profile-picture` - Get profile picture
- `POST /api/v1/patient/emergency-contacts` - Add emergency contact
- `DELETE /api/v1/patient/emergency-contacts/:contactId` - Remove emergency contact

### Medical History
- `GET /api/v1/patient/medical-history` - Get medical history
- `GET /api/v1/patient/medical-history/:encounterId` - Get specific encounter

### Reminders
- `GET /api/v1/patient/reminders` - Get all reminders
- `GET /api/v1/patient/reminders/active` - Get active reminders
- `GET /api/v1/patient/home/reminders/counters` - Get reminder counters
- `GET /api/v1/patient/home/today-schedule` - Get today's schedule
- `PATCH /api/v1/patient/reminders/:reminderId` - Update reminder

### Notifications
- `GET /api/v1/patient/notifications` - Get all notifications
- `GET /api/v1/patient/notifications/pending` - Get pending notifications
- `PATCH /api/v1/patient/notifications/:notificationId/read` - Mark as read

### Health Journal
- `GET /api/v1/patient/health-journal/diagnoses` - Get diagnoses
- `POST /api/v1/patient/health-journal/notes` - Create health note
- `GET /api/v1/patient/health-journal/notes` - Get note summary
- `GET /api/v1/patient/health-journal/notes/:diagnosisId` - Get notes for diagnosis

### AI Chat
- `POST /api/v1/patient/ai/chat/sessions` - Create chat session
- `GET /api/v1/patient/ai/chat/sessions` - List chat sessions
- `GET /api/v1/patient/ai/chat/sessions/:sessionId` - Get session details
- `PATCH /api/v1/patient/ai/chat/sessions/:sessionId` - Update session
- `DELETE /api/v1/patient/ai/chat/sessions` - Delete all sessions
- `DELETE /api/v1/patient/ai/chat/sessions/:sessionId` - Delete session
- `POST /api/v1/patient/ai/chat/sessions/:sessionId/messages` - Send message

## Database Schema

The application uses SQLite with the following tables:
- `users` - User accounts and profiles
- `otp_codes` - OTP verification codes
- `sessions` - User authentication sessions
- `ai_chat_sessions` - AI chat sessions
- `ai_chat_messages` - AI chat messages
- `medical_encounters` - Medical encounters
- `encounter_details` - Encounter details
- `reminders` - Patient reminders
- `notifications` - User notifications
- `health_journal_diagnoses` - Health journal diagnoses
- `health_journal_notes` - Health journal notes
- `emergency_contacts` - Emergency contacts

## Installation

```bash
# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Start development server
npm run dev

# Start production server
npm start
```

## Environment Variables

```env
PORT=3000
JWT_SECRET=your-secret-key-change-in-production
NODE_ENV=production
```

## Deployment

This project is configured for automatic deployment on Vercel:

1. Connect your GitHub repository to Vercel
2. Vercel will automatically detect the Node.js configuration
3. Deploy with one click

The application uses:
- `vercel.json` for deployment configuration
- SQLite database (file-based, no external database needed)
- Automatic builds on git push

## Security Features

- OTP-based authentication
- JWT token authentication
- Password hashing with bcrypt
- File upload validation
- Request validation
- CORS protection

## Development

```bash
# Run in development mode with auto-reload
npm run dev

# Run tests (when implemented)
npm test

# Lint code (when implemented)
npm run lint
```

## Contributing

This is a medical system API designed for healthcare applications. Please ensure all changes maintain patient data privacy and security standards.

## License

MIT License