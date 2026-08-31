import './polyfills.js';
import express from 'express';
import { config } from './config.js';
import {
  getSessionQr,
  getSessionStatus,
  logoutSession,
  resumeSavedSessions,
  sendMedia,
  sendText,
  startSession,
} from './session-manager.js';

const app = express();
app.use(express.json({ limit: '25mb' }));

function requireSecret(req, res, next) {
  const provided = req.header('X-Bridge-Secret');

  if (! provided || provided !== config.secret) {
    return res.status(403).json({ message: 'Invalid bridge secret.' });
  }

  return next();
}

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'whatsapp-bridge' });
});

app.use(requireSecret);

app.get('/sessions/:accountId/status', (req, res) => {
  res.json(getSessionStatus(req.params.accountId));
});

app.get('/sessions/:accountId/qr', (req, res) => {
  res.json(getSessionQr(req.params.accountId));
});

app.post('/sessions/:accountId/start', (req, res) => {
  try {
    const status = startSession(req.params.accountId);
    res.json({ success: true, ...status });
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

app.post('/sessions/:accountId/logout', async (req, res) => {
  try {
    const status = await logoutSession(req.params.accountId);
    res.json({ success: true, ...status });
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

app.post('/sessions/:accountId/send-text', async (req, res) => {
  const { to, body, jid } = req.body || {};

  if ((! to && ! jid) || ! body) {
    return res.status(422).json({ message: 'Fields "body" and either "to" or "jid" are required.' });
  }

  try {
    const result = await sendText(req.params.accountId, to, body, jid);
    res.json({ success: true, ...result });
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

app.post('/sessions/:accountId/send-media', async (req, res) => {
  const { to, type, file_base64: fileBase64, caption, mimetype, filename, jid } = req.body || {};

  if ((! to && ! jid) || ! type || ! fileBase64) {
    return res.status(422).json({ message: 'Fields "type", "file_base64", and either "to" or "jid" are required.' });
  }

  try {
    const result = await sendMedia(req.params.accountId, to, type, fileBase64, {
      caption,
      mimetype,
      filename,
      jid,
    });
    res.json({ success: true, ...result });
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

app.listen(config.port, () => {
  console.log(`WhatsApp bridge listening on port ${config.port}`);

  if (config.autoResumeSessions) {
    setTimeout(() => {
      const resumed = resumeSavedSessions();

      if (resumed.length > 0) {
        console.log(`Auto-resuming WhatsApp session(s): ${resumed.join(', ')}`);
      }
    }, config.autoResumeDelayMs);
  }
});
