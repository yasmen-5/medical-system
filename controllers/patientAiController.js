const { dbGet, dbRun, dbQuery, dbUpdate, dbDelete } = require('../database/setup');
const { v4: uuidv4 } = require('uuid');

const patientAiController = {
  createSession: (req, res) => {
    try {
      const userId = req.userId;
      const { title } = req.body;
      
      const sessionId = uuidv4();
      
      dbRun('aiChatSessions', {
        id: sessionId,
        userId,
        title: title || 'New Chat',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      });

      res.status(201).json({
        id: sessionId,
        userId,
        title: title || 'New Chat',
        createdAt: new Date().toISOString()
      });
    } catch (error) {
      res.status(500).json({ message: 'Failed to create session', error: error.message });
    }
  },

  listSessions: (req, res) => {
    try {
      const userId = req.userId;
      
      const sessions = dbQuery('aiChatSessions', s => s.userId === userId)
        .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));

      res.json({ sessions });
    } catch (error) {
      res.status(500).json({ message: 'Failed to list sessions', error: error.message });
    }
  },

  getSession: (req, res) => {
    try {
      const userId = req.userId;
      const { sessionId } = req.params;
      
      const session = dbGet('aiChatSessions', s => s.id === sessionId && s.userId === userId);

      if (!session) {
        return res.status(404).json({ message: 'Session not found' });
      }

      const messages = dbQuery('aiChatMessages', m => m.session_id === sessionId)
        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));

      res.json({ session, messages });
    } catch (error) {
      res.status(500).json({ message: 'Failed to get session', error: error.message });
    }
  },

  updateSession: (req, res) => {
    try {
      const userId = req.userId;
      const { sessionId } = req.params;
      const { title } = req.body;
      
      const session = dbGet('aiChatSessions', s => s.id === sessionId && s.userId === userId);

      if (!session) {
        return res.status(404).json({ message: 'Session not found' });
      }

      dbUpdate('aiChatSessions', s => s.id === sessionId, s => ({
        ...s,
        title,
        updated_at: new Date().toISOString()
      }));

      res.json({ message: 'Session updated' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to update session', error: error.message });
    }
  },

  deleteAllSessions: (req, res) => {
    try {
      const userId = req.userId;
      
      dbDelete('aiChatSessions', s => s.userId === userId);
      
      res.json({ message: 'All sessions deleted' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to delete sessions', error: error.message });
    }
  },

  deleteSession: (req, res) => {
    try {
      const userId = req.userId;
      const { sessionId } = req.params;
      
      const session = dbGet('aiChatSessions', s => s.id === sessionId && s.userId === userId);

      if (!session) {
        return res.status(404).json({ message: 'Session not found' });
      }

      dbDelete('aiChatSessions', s => s.id === sessionId);
      
      res.json({ message: 'Session deleted' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to delete session', error: error.message });
    }
  },

  sendMessage: (req, res) => {
    try {
      const userId = req.userId;
      const { sessionId } = req.params;
      const { content } = req.body;
      
      const session = dbGet('aiChatSessions', s => s.id === sessionId && s.userId === userId);

      if (!session) {
        return res.status(404).json({ message: 'Session not found' });
      }

      // Save user message
      const userMessageId = uuidv4();
      dbRun('aiChatMessages', {
        id: userMessageId,
        session_id: sessionId,
        role: 'user',
        content,
        created_at: new Date().toISOString()
      });

      // Generate AI response (placeholder)
      const aiMessageId = uuidv4();
      const aiResponse = "This is a placeholder AI response. Connect to an AI service for real responses.";
      
      dbRun('aiChatMessages', {
        id: aiMessageId,
        session_id: sessionId,
        role: 'assistant',
        content: aiResponse,
        created_at: new Date().toISOString()
      });

      // Update session timestamp
      dbUpdate('aiChatSessions', s => s.id === sessionId, s => ({
        ...s,
        updated_at: new Date().toISOString()
      }));

      res.status(201).json({
        userMessage: { id: userMessageId, role: 'user', content },
        aiMessage: { id: aiMessageId, role: 'assistant', content: aiResponse }
      });
    } catch (error) {
      res.status(500).json({ message: 'Failed to send message', error: error.message });
    }
  }
};

module.exports = patientAiController;