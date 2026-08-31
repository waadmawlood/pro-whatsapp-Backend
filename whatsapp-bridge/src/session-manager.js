import './polyfills.js';
import fs from 'node:fs';
import path from 'node:path';
import {
  Browsers,
  DisconnectReason,
  downloadMediaMessage,
  fetchLatestBaileysVersion,
  makeCacheableSignalKeyStore,
  makeWASocket,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import { Boom } from '@hapi/boom';
import pino from 'pino';
import QRCode from 'qrcode';
import { config } from './config.js';
import { notifyLaravel } from './laravel.js';

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });

/** @type {Map<string, import('@whiskeysockets/baileys').WASocket>} */
const sockets = new Map();

/** @type {Map<string, { status: string, qr?: string, phone_number?: string, message?: string }>} */
const sessionState = new Map();

/** @type {Map<string, string>} */
const lastQrRawByAccount = new Map();

function ensureSessionsDir() {
  if (! fs.existsSync(config.sessionsDir)) {
    fs.mkdirSync(config.sessionsDir, { recursive: true });
  }
}

function jidFromPhone(phone) {
  const digits = String(phone).replace(/\D/g, '');
  return `${digits}@s.whatsapp.net`;
}

function phoneFromJid(jid = '') {
  return jid.split('@')[0]?.split(':')[0] || '';
}

function isDirectChatJid(jid = '') {
  return jid.endsWith('@s.whatsapp.net') || jid.endsWith('@lid');
}

function isGroupChatJid(jid = '') {
  return jid.endsWith('@g.us');
}

function isSupportedChatJid(jid = '') {
  return isDirectChatJid(jid) || isGroupChatJid(jid);
}

async function getGroupSubject(sock, groupJid) {
  try {
    const metadata = await sock.groupMetadata(groupJid);

    return metadata.subject || null;
  } catch (error) {
    logger.debug({ groupJid, error: error.message }, 'Failed to fetch group metadata');

    return null;
  }
}

function resolveDestination(to, jid = null) {
  if (jid && String(jid).includes('@')) {
    return jid;
  }

  const digits = String(to).replace(/\D/g, '');

  if (! digits) {
    throw new Error('Missing recipient phone or jid');
  }

  if (digits.length > 15) {
    return `${digits}@g.us`;
  }

  return `${digits}@s.whatsapp.net`;
}

function setState(accountId, patch) {
  const current = sessionState.get(accountId) || { status: 'disconnected' };
  const next = { ...current, ...patch };
  sessionState.set(accountId, next);
  return next;
}

function unwrapMessageContent(message) {
  let content = message?.message || {};

  while (content) {
    if (content.ephemeralMessage?.message) {
      content = content.ephemeralMessage.message;
      continue;
    }

    if (content.viewOnceMessage?.message) {
      content = content.viewOnceMessage.message;
      continue;
    }

    if (content.viewOnceMessageV2?.message) {
      content = content.viewOnceMessageV2.message;
      continue;
    }

    if (content.documentWithCaptionMessage?.message) {
      content = content.documentWithCaptionMessage.message;
      continue;
    }

    break;
  }

  return content;
}

function shouldSkipMessage(message) {
  const content = unwrapMessageContent(message);

  return Boolean(
    content.reactionMessage
    || content.protocolMessage
    || content.senderKeyDistributionMessage
    || content.pollUpdateMessage,
  );
}

function mapMessageType(message) {
  const content = unwrapMessageContent(message);

  if (content.conversation || content.extendedTextMessage) {
    return 'text';
  }

  if (content.imageMessage) {
    return 'image';
  }

  if (content.videoMessage) {
    return 'video';
  }

  if (content.documentMessage) {
    return 'document';
  }

  if (content.audioMessage || content.pttMessage) {
    return 'audio';
  }

  if (content.stickerMessage) {
    return 'sticker';
  }

  return 'unknown';
}

function extractBody(message) {
  const content = unwrapMessageContent(message);

  return (
    content.conversation
    || content.extendedTextMessage?.text
    || content.imageMessage?.caption
    || content.videoMessage?.caption
    || content.documentMessage?.caption
    || null
  );
}

function extensionFromMime(mimetype = '') {
  switch (mimetype) {
    case 'image/jpeg':
      return '.jpg';
    case 'image/png':
      return '.png';
    case 'image/webp':
      return '.webp';
    case 'image/gif':
      return '.gif';
    case 'video/mp4':
      return '.mp4';
    case 'audio/ogg':
      return '.ogg';
    case 'audio/mpeg':
      return '.mp3';
    default:
      return '';
  }
}

/** @type {Set<string>} */
const booting = new Set();

function postConnection(accountId, payload) {
  notifyLaravel(accountId, 'connection', payload).catch((error) => {
    logger.error({ accountId, error: error.message }, 'Failed to notify Laravel about connection');
  });
}

function postIncomingMessage(accountId, payload) {
  notifyLaravel(accountId, 'message', payload).catch((error) => {
    logger.error({ accountId, error: error.message }, 'Failed to notify Laravel about message');
  });
}

function postStatus(accountId, payload) {
  notifyLaravel(accountId, 'status', payload).catch((error) => {
    logger.error({ accountId, error: error.message }, 'Failed to notify Laravel about status');
  });
}

async function handleIncomingMessage(accountId, sock, message) {
  if (! message.message || message.key.fromMe) {
    return;
  }

  if (shouldSkipMessage(message)) {
    return;
  }

  const remoteJid = message.key.remoteJid || '';

  if (! isSupportedChatJid(remoteJid)) {
    logger.debug({ accountId, remoteJid }, 'Skipping unsupported inbound chat jid');
    return;
  }

  const isGroup = isGroupChatJid(remoteJid);
  const type = mapMessageType(message);
  const payload = {
    whatsapp_message_id: message.key.id,
    chat_type: isGroup ? 'group' : 'direct',
    remote_jid: remoteJid,
    from: phoneFromJid(remoteJid),
    push_name: message.pushName || null,
    type,
    body: extractBody(message),
    timestamp: Number(message.messageTimestamp || Math.floor(Date.now() / 1000)),
  };

  if (isGroup) {
    payload.participant_jid = message.key.participant || null;
    payload.participant_name = message.pushName || null;
    payload.group_subject = await getGroupSubject(sock, remoteJid);
  }

  if (['image', 'video', 'document', 'audio', 'sticker'].includes(type)) {
    try {
      const buffer = await downloadMediaMessage(message, 'buffer', {}, {
        logger,
        reuploadRequest: sock.updateMediaMessage,
      });

      const content = unwrapMessageContent(message);
      const mediaNode = content.imageMessage
        || content.videoMessage
        || content.documentMessage
        || content.audioMessage
        || content.stickerMessage;
      const mimetype = mediaNode?.mimetype || 'application/octet-stream';
      const baseName = mediaNode?.fileName || `${type}-${message.key.id}`;
      const filename = baseName.includes('.') ? baseName : `${baseName}${extensionFromMime(mimetype)}`;

      payload.media = {
        mimetype,
        filename,
        file_base64: buffer.toString('base64'),
      };
    } catch (error) {
      logger.warn({ accountId, error: error.message }, 'Failed to download inbound media');
    }
  }

  await postIncomingMessage(accountId, payload);
}

function mapAckStatus(status) {
  switch (status) {
    case 1:
      return 'sent';
    case 2:
      return 'delivered';
    case 3:
      return 'read';
    case 0:
      return 'failed';
    default:
      return null;
  }
}

export function getSessionStatus(accountId) {
  return sessionState.get(String(accountId)) || { status: 'disconnected' };
}

export function getSessionQr(accountId) {
  const state = getSessionStatus(accountId);

  return {
    status: state.status,
    qr: state.qr || null,
    phone_number: state.phone_number || null,
  };
}

function readSessionCreds(accountId) {
  const credsPath = path.join(config.sessionsDir, String(accountId), 'creds.json');

  if (! fs.existsSync(credsPath)) {
    return null;
  }

  try {
    return JSON.parse(fs.readFileSync(credsPath, 'utf8'));
  } catch (error) {
    logger.warn({ accountId, error: error.message }, 'Failed to read saved session creds');

    return null;
  }
}

function isRestorableSession(accountId) {
  const creds = readSessionCreds(accountId);

  if (! creds) {
    return false;
  }

  return Boolean(creds.registered || creds.me?.id || creds.me);
}

function listRestorableSessionIds() {
  ensureSessionsDir();

  if (! fs.existsSync(config.sessionsDir)) {
    return [];
  }

  return fs.readdirSync(config.sessionsDir, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .filter((accountId) => isRestorableSession(accountId));
}

function getLatestSessionId(sessionIds) {
  if (sessionIds.length === 0) {
    return null;
  }

  if (sessionIds.length === 1) {
    return sessionIds[0];
  }

  let latestId = sessionIds[0];
  let latestMtime = 0;

  for (const accountId of sessionIds) {
    const credsPath = path.join(config.sessionsDir, accountId, 'creds.json');
    const mtime = fs.statSync(credsPath).mtimeMs;

    if (mtime > latestMtime) {
      latestMtime = mtime;
      latestId = accountId;
    }
  }

  return latestId;
}

export function resumeSavedSessions() {
  if (! config.autoResumeSessions) {
    return [];
  }

  const restorable = listRestorableSessionIds();

  if (restorable.length === 0) {
    logger.info('No saved WhatsApp sessions found to resume');

    return [];
  }

  let accountIds = [];

  if (config.autoResumeAccountId) {
    const forcedId = String(config.autoResumeAccountId);

    if (! isRestorableSession(forcedId)) {
      logger.warn({ accountId: forcedId }, 'Configured auto-resume account has no saved session');

      return [];
    }

    accountIds = [forcedId];
  } else if (config.autoResumeAll) {
    accountIds = restorable;
  } else {
    const latestId = getLatestSessionId(restorable);

    accountIds = latestId ? [latestId] : [];
  }

  for (const accountId of accountIds) {
    logger.info({ accountId }, 'Auto-resuming saved WhatsApp session');
    startSession(accountId);
  }

  return accountIds;
}

export function startSession(accountId) {
  const id = String(accountId);
  ensureSessionsDir();

  if (sockets.has(id)) {
    return getSessionStatus(id);
  }

  if (booting.has(id)) {
    return getSessionStatus(id);
  }

  setState(id, { status: 'connecting', qr: undefined, message: 'Starting session' });
  postConnection(id, { status: 'connecting', message: 'Starting session' });

  booting.add(id);
  bootSession(id)
    .catch((error) => {
      logger.error({ accountId: id, error: error.message }, 'Failed to boot session');
      setState(id, { status: 'error', message: error.message });
      postConnection(id, { status: 'error', message: error.message });
    })
    .finally(() => booting.delete(id));

  return getSessionStatus(id);
}

function clearSessionFiles(accountId) {
  const authDir = path.join(config.sessionsDir, String(accountId));

  if (fs.existsSync(authDir)) {
    fs.rmSync(authDir, { recursive: true, force: true });
  }
}

async function bootSession(id) {
  const authDir = path.join(config.sessionsDir, id);
  const { state, saveCreds } = await useMultiFileAuthState(authDir);
  const { version } = await fetchLatestBaileysVersion();

  const sock = makeWASocket({
    version,
    auth: {
      creds: state.creds,
      keys: makeCacheableSignalKeyStore(state.keys, logger),
    },
    browser: Browsers.macOS('Chrome'),
    logger,
    printQRInTerminal: false,
    syncFullHistory: false,
    markOnlineOnConnect: false,
    generateHighQualityLinkPreview: false,
    getMessage: async () => undefined,
  });

  let registered = Boolean(state.creds?.registered);

  sockets.set(id, sock);

  sock.ev.on('creds.update', async () => {
    await saveCreds();
    registered = Boolean(state.creds?.registered);
  });

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      const previousRaw = lastQrRawByAccount.get(id);

      if (previousRaw === qr) {
        return;
      }

      lastQrRawByAccount.set(id, qr);
      const dataUrl = await QRCode.toDataURL(qr);
      setState(id, { status: 'qr', qr: dataUrl });
      postConnection(id, { status: 'qr', qr: dataUrl });
    }

    if (connection === 'open') {
      lastQrRawByAccount.delete(id);
      const phone = phoneFromJid(sock.user?.id);
      setState(id, { status: 'connected', qr: undefined, phone_number: phone, message: 'Connected' });
      postConnection(id, { status: 'connected', phone_number: phone });
    }

    if (connection === 'close') {
      const statusCode = new Boom(lastDisconnect?.error)?.output?.statusCode;
      const errorMessage = lastDisconnect?.error?.message || 'Connection closed';
      sockets.delete(id);

      if (statusCode === DisconnectReason.loggedOut) {
        clearSessionFiles(id);
        lastQrRawByAccount.delete(id);
        setState(id, { status: 'logged_out', qr: undefined, phone_number: undefined });
        postConnection(id, { status: 'logged_out', message: 'Logged out' });
        return;
      }

      // Normal after scanning QR — restart with saved credentials, never wipe session.
      if (statusCode === DisconnectReason.restartRequired) {
        lastQrRawByAccount.delete(id);
        setState(id, {
          status: 'connecting',
          qr: undefined,
          message: 'Pairing successful, finishing connection...',
        });
        postConnection(id, {
          status: 'connecting',
          message: 'Pairing successful, finishing connection...',
        });

        setTimeout(() => {
          if (! sockets.has(id) && ! booting.has(id)) {
            startSession(id);
          }
        }, 1500);

        return;
      }

      const wasPairing = ! registered;
      const shouldResetSession = statusCode === 405 || statusCode === 401;

      if (shouldResetSession) {
        clearSessionFiles(id);
        lastQrRawByAccount.delete(id);
      }

      const previous = getSessionStatus(id);

      if (wasPairing && previous.qr) {
        setState(id, {
          status: 'qr',
          qr: previous.qr,
          message: 'Scan the QR code with WhatsApp on your phone.',
        });
      } else {
        setState(id, {
          status: 'disconnected',
          message: `${errorMessage}${statusCode ? ` (${statusCode})` : ''}`,
        });
        postConnection(id, {
          status: 'disconnected',
          message: errorMessage,
        });
      }

      setTimeout(() => {
        if (! sockets.has(id) && ! booting.has(id)) {
          startSession(id);
        }
      }, wasPairing ? 3000 : 5000);
    }
  });

  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') {
      return;
    }

    for (const message of messages) {
      await handleIncomingMessage(id, sock, message);
    }
  });

  sock.ev.on('messages.update', async (updates) => {
    for (const update of updates) {
      const mapped = mapAckStatus(update.update?.status);

      if (! mapped || ! update.key?.id) {
        continue;
      }

      postStatus(id, {
        whatsapp_message_id: update.key.id,
        status: mapped,
      });
    }
  });
}

export async function logoutSession(accountId) {
  const id = String(accountId);
  const sock = sockets.get(id);

  if (sock) {
    try {
      await sock.logout();
    } catch (error) {
      logger.warn({ accountId: id, error: error.message }, 'Logout failed');
    }

    sock.end(undefined);
    sockets.delete(id);
  }

  const authDir = path.join(config.sessionsDir, id);

  if (fs.existsSync(authDir)) {
    fs.rmSync(authDir, { recursive: true, force: true });
  }

  setState(id, { status: 'logged_out', qr: undefined, phone_number: undefined });
  postConnection(id, { status: 'logged_out', message: 'Session cleared' });

  return getSessionStatus(id);
}

export async function sendText(accountId, to, body, jid = null) {
  const sock = sockets.get(String(accountId));

  if (! sock) {
    throw new Error('Session is not connected. Call /start first.');
  }

  const destination = resolveDestination(to, jid);
  const result = await sock.sendMessage(destination, { text: body });

  return {
    whatsapp_message_id: result?.key?.id || null,
    status: 'sent',
    destination,
  };
}

export async function sendMedia(accountId, to, type, fileBase64, options = {}) {
  const sock = sockets.get(String(accountId));

  if (! sock) {
    throw new Error('Session is not connected. Call /start first.');
  }

  const buffer = Buffer.from(fileBase64, 'base64');
  const destination = resolveDestination(to, options.jid);
  const caption = options.caption || undefined;
  const mimetype = options.mimetype || 'application/octet-stream';
  const filename = options.filename || 'file';

  let content;

  switch (type) {
    case 'image':
      content = { image: buffer, caption, mimetype };
      break;
    case 'video':
      content = { video: buffer, caption, mimetype };
      break;
    case 'audio':
      content = { audio: buffer, mimetype, ptt: false };
      break;
    case 'document':
      content = { document: buffer, mimetype, fileName: filename, caption };
      break;
    case 'sticker':
      content = { sticker: buffer };
      break;
    default:
      throw new Error(`Unsupported media type: ${type}`);
  }

  const result = await sock.sendMessage(destination, content);

  return {
    whatsapp_message_id: result?.key?.id || null,
    status: 'sent',
    destination,
  };
}
