import http from 'node:http';
import https from 'node:https';
import { URL } from 'node:url';
import { config } from './config.js';

export function notifyLaravel(accountId, event, payload) {
  const target = new URL(
    `${config.laravelUrl}/api/v1/webhooks/whatsapp-bridge/${accountId}/${event}`,
  );
  const body = JSON.stringify(payload);
  const client = target.protocol === 'https:' ? https : http;

  return new Promise((resolve, reject) => {
    const request = client.request(
      target,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Bridge-Secret': config.secret,
          'Content-Length': Buffer.byteLength(body),
        },
      },
      (response) => {
        let responseBody = '';

        response.on('data', (chunk) => {
          responseBody += chunk;
        });

        response.on('end', () => {
          if (response.statusCode && response.statusCode >= 200 && response.statusCode < 300) {
            resolve();
            return;
          }

          reject(new Error(`Laravel webhook failed (${response.statusCode}): ${responseBody}`));
        });
      },
    );

    request.on('error', reject);
    request.write(body);
    request.end();
  });
}
