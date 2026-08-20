const { db } = require('../database/setup');
const { v4: uuidv4 } = require('uuid');

const patientAiController = {
  createSession: (req, res) => {
    try {
      const userId = req.userId;
      const { title } = req.body;
      
      const sessionId = uuidv4();
      
      db.prepare(`
        INSERT INTO ai_chat_sessions (id, user_id, title)
        VALUES (?, ?, ?)
      `).run(sessionId, userId, title || 'New Chat');

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
      
      const sessions = db.prepare(`
        SELECT id, title, created_at, updated_at 
        FROM ai_chat_sessions 
        WHERE user_id = ? 
        ORDER BY updated_at DESC
      `).all(userId);

      res.json({ sessions });
    } catch (error) {
      res.status(500).json({ message: 'Failed to list sessions', error: error.message });
    }
  },

  getSession: (req, res) => {
    try {
      const userId = req.userId;
      const { sessionId } = req.params;
      
      const session = db.prepare(`
        SELECT * FROM ai_chat_sessions 
        WHERE id = ? AND user_id = ?
      `).get(sessionId, userId);

      if (!session) {
        return res.status(404).json({ message: 'Session not found' });
      }

      const messages = db.prepare(`
        SELECT * FROM ai_chat_messages 
        WHERE session_id = ? 
        ORDER BY created_at ASC
      `).all(sessionId);

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
      
      const session = db.prepare(`
        SELECT * FROM ai_chat_sessions 
        WHERE id = ? AND user_id = ?
      `).get(sessionId, userId);

      if (!session) {
        return res.status(404).json({ message: 'Session not found' });
      }

      db.prepare(`
        UPDATE ai_chat_sessions 
        SET title = ?, updated_at = datetime('now') 
        WHERE id = ?
      `).run(title, sessionId);

      res.json({ message: 'Session updated' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to update session', error: error.message });
    }
  },

  deleteAllSessions: (req, res) => {
    try {
      const userId = req.userId;
      
      db.prepare('DELETE FROM ai_chat_sessions WHERE user_id = ?').run(userId);
      
      res.json({ message: 'All sessions deleted' });
    } catch (error) {
      res.status(500).json({ message: 'Failed to delete sessions', error: error.message });
    }
  },

  deleteSession: (req, res) => {
    try {
      const userId = req.userId;
      const { sessionId } = req.params;
      
      const session = db.prepare(`
        SELECT * FROM ai_chat_sessions 
        WHERE id = ? AND user_id = ?
      `).get(sessionId, userId);

      if (!session) {
        return res.status(404).json({ message: 'Session not found' });
      }

      db.prepare('DELETE FROM ai_chat_sessions WHERE id = ?').run(sessionId);
      
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
      
      const session = db.prepare(`
        SELECT * FROM ai_chat_sessions 
        WHERE id = ? AND user_id = ?
      `).get(sessionId, userId);

      if (!session) {
        return res.status(404).json({ message: 'Session not found' });
      }

      // Save user message
      const userMessageId = uuidv4();
      db.prepare(`
        INSERT INTO ai_chat_messages (id, session_id, role, content)
        VALUES (?, ?, 'user', ?)
      `).run(userMessageId, sessionId, content);

      // Generate AI response (placeholder)
      const aiMessageId = uuidv4();
      const aiResponse = "This is a placeholder AI response. Connect to an AI service for real responses.";
      
      db.prepare(`
        INSERT INTO ai_chat_messages (id, session_id, role, content)
        VALUES (?, ?, 'assistant', ?)
      `).run(aiMessageId, sessionId, aiResponse);

      // Update session timestamp
      db.prepare('UPDATE ai_chat_sessions SET updated_at = datetime("now") WHERE id = ?').run(sessionId);

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