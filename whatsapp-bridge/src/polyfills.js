import { webcrypto } from 'node:crypto';

// Node 16 exposes the legacy crypto module as globalThis.crypto without subtle.
// Baileys needs the Web Crypto API (crypto.subtle).
if (! globalThis.crypto?.subtle) {
  globalThis.crypto = webcrypto;
}
