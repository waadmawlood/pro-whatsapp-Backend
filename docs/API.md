# توثيق واجهة البرمجة — Pro WhatsApp API

التوثيق التفاعلي (Swagger): بعد تشغيل السيرفر افتح [http://localhost:8000/docs](http://localhost:8000/docs).

مواصفة OpenAPI: [`openapi.yaml`](./openapi.yaml)

---

## الأساس

| العنصر | القيمة |
|--------|--------|
| Base URL | `/api/v1` |
| الصيغة | JSON |
| المصادقة | `Authorization: Bearer {token}` |
| اللغة | `Accept-Language: ar` أو `en` |
| تعدد الأجهزة | كل تسجيل دخول يُنشئ توكن مستقل |

### شكل النجاح

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 0
  }
}
```

`meta` يظهر فقط في القوائم المصفّحة.

### شكل الخطأ

```json
{
  "success": false,
  "message": "Forbidden."
}
```

| الرمز | المعنى |
|------|--------|
| 401 | غير مسجّل الدخول |
| 403 | لا صلاحية / حساب معطّل |
| 404 | غير موجود أو تابع لشركة أخرى |
| 422 | بيانات غير صالحة |
| 429 | تجاوز حد الطلبات |

---

## الصلاحيات

| الصلاحية | Admin | Employee الافتراضي |
|----------|:-----:|:------------------:|
| `customers.view` | نعم | نعم |
| `customers.create` | نعم | نعم |
| `customers.update` | نعم | نعم |
| `customers.delete` | نعم | لا |
| `conversations.view` | نعم | نعم (محادثاته فقط) |
| `conversations.view_all` | نعم | لا |
| `conversations.assign` | نعم | لا |
| `conversations.close` | نعم | نعم |
| `conversations.delete` | نعم | لا |
| `messages.view` | نعم | نعم |
| `messages.send` | نعم | نعم |
| `notes.create` | نعم | نعم |
| `tags.view` | نعم | نعم |
| `tags.manage` | نعم | لا |
| `users.view` | نعم | لا |
| `users.manage` | نعم | لا |
| `whatsapp.manage` | نعم | لا |
| `reports.view` | نعم | لا |
| `audit.view` | نعم | لا |

---

## المسارات

### Auth

| Method | Path | Auth | الوصف |
|--------|------|------|--------|
| POST | `/auth/login` | لا | توكن + المستخدم |
| POST | `/auth/logout` | نعم | إنهاء الجلسة الحالية |
| POST | `/auth/logout-all` | نعم | إنهاء كل الجلسات |
| GET | `/auth/me` | نعم | الملف الحالي |
| PUT | `/auth/profile` | نعم | تحديث الاسم/الهاتف/اللغة/كلمة المرور |
| GET | `/auth/sessions` | نعم | الأجهزة المتصلة |
| DELETE | `/auth/sessions/{token}` | نعم | إلغاء جهاز |

### Users

| Method | Path | صلاحية |
|--------|------|--------|
| GET | `/users` | `users.view` |
| POST | `/users` | `users.manage` |
| GET | `/users/{id}` | `users.view` |
| PUT | `/users/{id}` | `users.manage` |
| DELETE | `/users/{id}` | `users.manage` |

### Customers

| Method | Path | صلاحية |
|--------|------|--------|
| GET | `/customers` | `customers.view` |
| POST | `/customers` | `customers.create` |
| GET | `/customers/{id}` | `customers.view` |
| PUT | `/customers/{id}` | `customers.update` |
| DELETE | `/customers/{id}` | `customers.delete` |
| GET | `/customers/{id}/notes` | `customers.view` |
| POST | `/customers/{id}/notes` | `notes.create` |
| POST | `/customers/{id}/tags` | `customers.update` |

حالات العميل: `new` · `active` · `waiting` · `completed` · `blocked`

### Conversations & Messages

| Method | Path | صلاحية |
|--------|------|--------|
| GET | `/conversations` | `conversations.view` |
| GET | `/conversations/{id}` | عرض المحادثة |
| PATCH | `/conversations/{id}` | تحديث `link_id` |
| DELETE | `/conversations/{id}` | `conversations.delete` |
| GET | `/conversations/{id}/messages` | `messages.view` |
| POST | `/conversations/{id}/messages` | `messages.send` |
| POST | `/conversations/{id}/assign` | `conversations.assign` |
| POST | `/conversations/{id}/close` | `conversations.close` |
| POST | `/conversations/{id}/reopen` | `conversations.close` |

حالات الرسالة: `sending` · `sent` · `delivered` · `read` · `failed`

أنواع الرسالة: `text` · `image` · `video` · `document` · `audio` · `sticker` · `location` · `contacts` · `unknown`

فلاتر الصندوق: `q`, `status=open|closed`, `unassigned=true`, `assigned_user_id`, `customer_id`

### Tags / WhatsApp / باقي النظام

| Method | Path | صلاحية |
|--------|------|--------|
| GET/POST | `/tags` | عرض / `tags.manage` |
| PUT/DELETE | `/tags/{id}` | `tags.manage` |
| GET/POST | `/whatsapp-accounts` | `whatsapp.manage` |
| GET/PUT/DELETE | `/whatsapp-accounts/{id}` | `whatsapp.manage` |
| GET | `/whatsapp-accounts/{id}/bridge` | `whatsapp.manage` — **حالة + QR للفرونت** |
| GET | `/whatsapp-accounts/{id}/bridge/status` | `whatsapp.manage` |
| GET | `/whatsapp-accounts/{id}/bridge/qr` | `whatsapp.manage` |
| POST | `/whatsapp-accounts/{id}/bridge/connect` | `whatsapp.manage` |
| POST | `/whatsapp-accounts/{id}/bridge/disconnect` | `whatsapp.manage` |
| GET | `/dashboard` | `reports.view` |
| GET | `/search?q=` | حسب نوع النتيجة |
| GET | `/notifications` | المستخدم الحالي |
| POST | `/notifications/read-all` | المستخدم الحالي |
| POST | `/notifications/{id}/read` | المستخدم الحالي |
| GET | `/audit-logs` | `audit.view` |
| GET | `/permissions` | أي مستخدم مسجّل |
| GET/POST | `/webhooks/whatsapp/{id}` | عام (Meta Cloud API) |
| POST | `/webhooks/whatsapp-bridge/{id}/connection` | جسر Node (سر مشترك) |
| POST | `/webhooks/whatsapp-bridge/{id}/message` | جسر Node (سر مشترك) |
| POST | `/webhooks/whatsapp-bridge/{id}/status` | جسر Node (سر مشترك) |

---

## ربط WhatsApp

يدعم النظام طريقتين للربط عبر حقل `connection_type`:

| النوع | القيمة | الاستخدام |
|-------|--------|-----------|
| WhatsApp Web (افتراضي) | `web` | جلسة واحدة عبر جسر Baileys — مناسب عندما لا يتوفر Cloud API |
| Meta Cloud API | `cloud` | الربط الرسمي عبر Meta Developer |

### ربط WhatsApp Web (موصى به)

**المتطلبات:** تشغيل خدمة `whatsapp-bridge` على المنفذ `3001` وضبط المتغيرات في `.env`:

```env
WHATSAPP_BRIDGE_URL=http://127.0.0.1:3001
WHATSAPP_BRIDGE_SECRET=change-me-bridge-secret
```

**الخطوات:**

1. إنشاء حساب (لا يحتاج رقم واتساب مسبقاً):

```bash
curl -X POST http://localhost:8000/api/v1/whatsapp-accounts \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"الخط الرئيسي","connection_type":"web","is_default":true}'
```

2. بدء الجلسة:

```bash
curl -X POST http://localhost:8000/api/v1/whatsapp-accounts/1/bridge/connect \
  -H "Authorization: Bearer TOKEN"
```

3. جلب QR ومسحه من واتساب → الأجهزة المرتبطة:

```bash
curl http://localhost:8000/api/v1/whatsapp-accounts/1/bridge/qr \
  -H "Authorization: Bearer TOKEN"
```

4. بعد المسح يُحدَّث `phone_number` و`status` تلقائياً إلى `connected`.

**للفرونت — endpoint موحّد:**

```bash
# بعد connect — استعلم دورياً كل 3 ثوانٍ
GET /api/v1/whatsapp-accounts/{id}/bridge
```

**شكل الاستجابة:**

```json
{
  "success": true,
  "data": {
    "status": "qr",
    "qr_available": true,
    "qr_data_url": "data:image/png;base64,...",
    "phone_number": null,
    "is_connected": false,
    "poll_after_ms": 3000,
    "message": "Scan the QR code with WhatsApp on your phone.",
    "account": { "id": 1, "status": "pending", ... }
  }
}
```

**مثال React:**

```jsx
<img src={data.qr_data_url} alt="WhatsApp QR" width={280} height={280} />
```

**حقول الاستجابة الإضافية لحساب Web:**

| الحقل | الوصف |
|-------|--------|
| `connection_type` | `web` أو `cloud` |
| `bridge_qr_available` | هل يوجد QR جاهز للعرض |
| `bridge_connected_at` | وقت آخر اتصال ناجح |

**ملاحظات:**

- رقم واتساب **واحد** — عدة موظفين يديرونه من النظام.
- عند الحذف يُرسل طلب `logout` للجسر تلقائياً.
- الإرسال والاستقبال يعملان عبر نفس صندوق الوارد والمحادثات.

### ربط Meta Cloud API

عند `connection_type: "cloud"` تُطلب الحقول: `phone_number`, `phone_number_id`, `access_token` (واختيارياً `waba_id`, `app_secret`).

بعد الإنشاء استخدم `webhook_url` و `webhook_verify_token` في Meta Developer Console.

---

## أمثلة

### تسجيل الدخول

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"password","device_name":"office-1"}'
```

### إرسال نص

```bash
curl -X POST http://localhost:8000/api/v1/conversations/1/messages \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type":"text","body":"مرحباً"}'
```

### إرسال صورة

```bash
curl -X POST http://localhost:8000/api/v1/conversations/1/messages \
  -H "Authorization: Bearer TOKEN" \
  -F "type=image" \
  -F "caption=الفاتورة" \
  -F "file=@/path/to/photo.jpg"
```

### تعيين محادثة

```bash
curl -X POST http://localhost:8000/api/v1/conversations/1/assign \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id":2}'
```

---

## البث الفوري (WebSocket)

مصادقة القناة: `POST /api/broadcasting/auth` مع Bearer token.

| القناة | من يستمع |
|--------|----------|
| `private-company.{companyId}` | كل مستخدمي الشركة |
| `private-user.{userId}` | المستخدم نفسه (إشعارات) |
| `private-conversation.{conversationId}` | المعيَّن أو من يملك `view_all` |

| الحدث | المعنى |
|--------|--------|
| `message.created` | رسالة جديدة |
| `message.status` | تحديث حالة الإرسال |
| `whatsapp.connection` | تغيّر حالة ربط واتساب (QR / اتصال / انقطاع) |
| `conversation.created` | محادثة جديدة |
| `conversation.assigned` | تم التعيين |
| `conversation.closed` / `reopened` / `updated` / `read` | تحديث المحادثة |

إشعارات قاعدة البيانات تُبث أيضاً على قناة المستخدم (`new_message`, `conversation_assigned`, `new_conversation`).
