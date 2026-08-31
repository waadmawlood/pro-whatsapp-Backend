# Pro WhatsApp API

Laravel REST API لنظام إدارة عملاء ومحادثات WhatsApp. الواجهة الأمامية منفصلة؛ هذا المستودع هو الـ Backend فقط.

يدعم نوعين من الربط:

| النوع | الوصف |
|-------|--------|
| **WhatsApp Web** (افتراضي) | عبر جسر Node.js + Baileys — QR من التطبيق، بدون Meta Cloud API |
| **WhatsApp Cloud API** | عبر Meta Business API والويب هوك الرسمي |

---

## المميزات (MVP)

- تسجيل دخول متعدد الأجهزة (Sanctum tokens)
- شركات متعددة (Multi-tenant) مع عزل كامل للبيانات
- أدوار وصلاحيات: Admin / Employee
- إدارة الموظفين والعملاء والوسوم
- Inbox: محادثات، رسائل، تعيين، إغلاق، `link_id` مخصص
- دعم المحادثات الشخصية والمجموعات (كروبات)
- ربط WhatsApp Web (Baileys bridge) + WhatsApp Cloud API
- رسائل فورية عبر WebSocket (Soketi / Pusher protocol)
- بحث، لوحة تحكم، إشعارات، Audit log
- Rate limiting، تشفير التوكنات، تحقق من توقيع الويب هوك

---

## البنية

```
┌─────────────────┐     REST + WebSocket      ┌──────────────────┐
│  Web / Mobile   │ ─────────────────────────▶│   Laravel API    │
│     clients     │                           │   (port 8000)    │
└─────────────────┘                           └────────┬─────────┘
                                                       │
                       ┌───────────────────────────────┼───────────────────────────────┐
                       │                               │                               │
                       ▼                               ▼                               ▼
              ┌────────────────┐            ┌─────────────────┐            ┌──────────────────┐
              │  PostgreSQL /  │            │  Queue Worker   │            │  whatsapp-bridge │
              │    SQLite      │            │ queue:work      │            │  Node + Baileys  │
              │  + Redis       │            │ (إرسال/ويب هوك) │            │  (port 3001)     │
              └────────────────┘            └─────────────────┘            └────────┬─────────┘
                                                                                    │
                                                                                    ▼
                                                                          ┌──────────────────┐
                                                                          │  WhatsApp Web    │
                                                                          │  (QR / جلسة)     │
                                                                          └──────────────────┘
```

---

## المتطلبات

| الأداة | الإصدار | ملاحظة |
|--------|---------|--------|
| PHP | 8.3+ | مع امتدادات Laravel القياسية |
| Composer | 2.x | |
| Node.js | 16+ | لتشغيل `whatsapp-bridge` |
| npm | 8+ | |
| PostgreSQL | 16 | أو SQLite للتطوير المحلي |
| Redis | 7 | اختياري (مُوصى به للإنتاج) |
| Docker | — | اختياري: Postgres + Redis + Soketi + Bridge |

---

## التشغيل السريع

### 1) إعداد Laravel

```bash
# استنساخ المشروع ثم من جذر المستودع:
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

**متغيرات مهمة في `.env` (جذر المشروع):**

```env
APP_URL=http://localhost:8000
QUEUE_CONNECTION=database

# جسر WhatsApp Web — يجب أن يطابق whatsapp-bridge/.env
WHATSAPP_BRIDGE_URL=http://127.0.0.1:3001
WHATSAPP_BRIDGE_SECRET=change-me-bridge-secret
```

> `storage:link` ضروري لعرض الوسائط (صور/فيديو) المحفوظة.

### 2) إعداد whatsapp-bridge

```bash
cd whatsapp-bridge
cp .env.example .env   # أو أنشئ الملف يدوياً (انظر الأسفل)
npm install
```

**ملف `whatsapp-bridge/.env`:**

```env
PORT=3001
BRIDGE_SECRET=change-me-bridge-secret
LARAVEL_URL=http://127.0.0.1:8000
SESSIONS_DIR=./sessions
AUTO_RESUME_SESSIONS=true
```

| متغير | الوصف |
|-------|--------|
| `BRIDGE_SECRET` | نفس قيمة `WHATSAPP_BRIDGE_SECRET` في Laravel |
| `LARAVEL_URL` | عنوان API لإرسال webhooks |
| `AUTO_RESUME_SESSIONS` | استئناف آخر جلسة محفوظة تلقائياً عند التشغيل |
| `AUTO_RESUME_ACCOUNT_ID` | (اختياري) فرض رقم حساب معيّن |
| `AUTO_RESUME_ALL` | `true` لاستئناف كل الجلسات المحفوظة |

### 3) تشغيل الخدمات

افتح **3 نوافذ طرفية** من جذر المشروع:

```bash
# الطرفية 1 — API
php artisan serve
```

```bash
# الطرفية 2 — Queue (إلزامي للإرسال واستقبال الرسائل)
php artisan queue:work
```

```bash
# الطرفية 3 — جسر WhatsApp
cd whatsapp-bridge && npm start
```

بعد تشغيل الـ bridge سترى:

```
WhatsApp bridge listening on port 3001
Auto-resuming WhatsApp session(s): 9
```

> إعادة تشغيل الـ bridge **لا تتطلب** مسح QR من جديد — الجلسة محفوظة في `whatsapp-bridge/sessions/`.

### 4) (اختياري) Docker للبنية التحتية

```bash
docker compose up -d postgres redis soketi
# أو لتشغيل الـ bridge داخل Docker أيضاً:
docker compose up -d
```

عند تشغيل الـ bridge عبر Docker، اضبط `LARAVEL_URL=http://host.docker.internal:8000` داخل `docker-compose.yml`.

---

## ربط WhatsApp Web (QR)

### من الـ API

```bash
# 1) تسجيل الدخول
curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password","device_name":"cli"}'

# 2) إنشاء حساب واتساب (نوع web)
curl -s -X POST http://localhost:8000/api/v1/whatsapp-accounts \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"name":"Main","connection_type":"web","is_default":true}'

# 3) بدء الجلسة / عرض QR
curl -s -X POST http://localhost:8000/api/v1/whatsapp-accounts/{id}/bridge/connect \
  -H "Authorization: Bearer {TOKEN}"

# 4) متابعة الحالة
curl -s http://localhost:8000/api/v1/whatsapp-accounts/{id}/bridge \
  -H "Authorization: Bearer {TOKEN}"
```

### الخطوات

1. تأكد أن **الثلاث خدمات** تعمل (`serve` + `queue:work` + `npm start`).
2. أنشئ حساباً بنوع `web` من الـ API أو Swagger.
3. استدعِ `POST .../bridge/connect` وخذ `qr_data_url` من الاستجابة.
4. امسح QR من واتساب على الهاتف: **الإعدادات ← الأجهزة المرتبطة ← ربط جهاز**.
5. عند `status: connected` يصبح الحساب جاهزاً للإرسال والاستقبال.

### فحص صحة الـ bridge

```bash
curl http://127.0.0.1:3001/health
# {"ok":true,"service":"whatsapp-bridge"}
```

---

## حسابات التجربة

بعد `php artisan migrate --seed`:

| الدور | البريد | كلمة المرور |
|-------|--------|-------------|
| Admin | admin@example.com | password |
| Employee | ahmed@example.com | password |

---

## توثيق الـ API

- تفاعلي (Swagger UI): [http://localhost:8000/docs](http://localhost:8000/docs)
- OpenAPI: [`docs/openapi.yaml`](docs/openapi.yaml)
- مرجع نصي: [`docs/API.md`](docs/API.md)

بعد `php artisan serve` افتح `/docs`، اضغط **Authorize**، ثم الصق توكن تسجيل الدخول.

---

## ربط WhatsApp Cloud API (اختياري)

1. أنشئ تطبيق Meta واتساب بيزنس واحصل على `phone_number_id` و `access_token` و `app_secret`.
2. `POST /api/v1/whatsapp-accounts` مع `"connection_type": "cloud"`.
3. من الاستجابة خذ `webhook_url` و `webhook_verify_token`.
4. في Meta Developer أضف Callback URL وVerify Token.
5. اشترك في أحداث `messages`.

الويب هوك:

- `GET  /api/v1/webhooks/whatsapp/{id}` — تحقق الاشتراك
- `POST /api/v1/webhooks/whatsapp/{id}` — استقبال الرسائل وحالات التسليم

---

## المصادقة

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password",
  "device_name": "office-1"
}
```

ثم في كل طلب:

```http
Authorization: Bearer {token}
Accept-Language: ar
```

---

## أهم المسارات

| Method | Path | الوصف |
|--------|------|--------|
| POST | `/api/v1/auth/login` | تسجيل الدخول |
| GET | `/api/v1/auth/me` | المستخدم الحالي |
| GET | `/api/v1/dashboard` | إحصائيات الأدمن |
| GET/POST | `/api/v1/users` | الموظفون |
| GET/POST | `/api/v1/customers` | العملاء |
| GET | `/api/v1/conversations` | صندوق الوارد |
| PATCH | `/api/v1/conversations/{id}` | تحديث `link_id` |
| POST | `/api/v1/conversations/{id}/messages` | إرسال رسالة / ملف |
| POST | `/api/v1/conversations/{id}/assign` | تعيين لموظف |
| POST | `/api/v1/conversations/{id}/close` | إغلاق المحادثة |
| GET | `/api/v1/whatsapp-accounts/{id}/bridge` | حالة الربط + QR |
| POST | `/api/v1/whatsapp-accounts/{id}/bridge/connect` | بدء / استئناف الجلسة |
| GET | `/api/v1/search?q=` | بحث شامل |
| GET | `/api/v1/audit-logs` | سجل العمليات |

الاستجابة موحدة:

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 0 }
}
```

---

## Real-Time

قنوات خاصة (Laravel Echo + Soketi):

- `private-company.{companyId}`
- `private-user.{userId}`
- `private-conversation.{conversationId}`

أحداث: `message.created`, `message.status`, `conversation.*`

Auth endpoint: `POST /api/broadcasting/auth` مع Bearer token.

---

## استكشاف الأخطاء

| المشكلة | الحل |
|---------|------|
| الرسائل لا تُرسل | تأكد من `php artisan queue:work` |
| QR لا يظهر | شغّل الـ bridge ثم `bridge/connect` |
| الصور لا تظهر | نفّذ `php artisan storage:link` وأعد تحميل الرسائل |
| 403 على webhooks | طابق `BRIDGE_SECRET` و `WHATSAPP_BRIDGE_SECRET` |
| بعد إعادة تشغيل bridge | لا حاجة لـ QR — الاستئناف تلقائي إن `AUTO_RESUME_SESSIONS=true` |

---

## الاختبارات

```bash
php artisan test
```

---

## الصلاحيات

Admin يملك الكل. Employee افتراضياً:

`customers.view/create/update`, `conversations.view/close`, `messages.view/send`, `notes.create`, `tags.view`
