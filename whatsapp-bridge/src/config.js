import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export const config = {
  port: Number(process.env.PORT || 3001),
  secret: process.env.BRIDGE_SECRET || 'change-me-bridge-secret',
  laravelUrl: (process.env.LARAVEL_URL || 'http://127.0.0.1:8000').replace(/\/$/, ''),
  sessionsDir: process.env.SESSIONS_DIR || path.join(__dirname, '..', 'sessions'),
  autoResumeSessions: process.env.AUTO_RESUME_SESSIONS !== 'false',
  autoResumeAll: process.env.AUTO_RESUME_ALL === 'true',
  autoResumeAccountId: process.env.AUTO_RESUME_ACCOUNT_ID || null,
  autoResumeDelayMs: Number(process.env.AUTO_RESUME_DELAY_MS || 1500),
};
