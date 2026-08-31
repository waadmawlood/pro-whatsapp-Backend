# Pro WhatsApp API

Laravel REST API لنظام إدارة عملاء ومحادثات WhatsApp. الواجهة الأمامية منفصلة؛ هذا المستودع هو الـ Backend فقط.

## المميزات (MVP)

- تسجيل دخول متعدد الأجهزة (Sanctum tokens)
- شركات متعددة (Multi-tenant) مع عزل كامل للبيانات
- أدوار وصلاحيات: Admin / Employee
- إدارة الموظفين والعملاء والوسوم
- Inbox: محادثات، رسائل، تعيين، إغلاق
- ربط WhatsApp Cloud API + Webhooks
- رسائل فورية عبر WebSocket (Soketi / Pusher protocol)
- بحث، لوحة تحكم، إشعارات، Audit log
- Rate limiting، تشفير التوكنات، تحقق من توقيع الويب هوك

## المتطلبات

- PHP 8.3+
- Composer
- PostgreSQL 16
- Redis
- (اختياري) Docker لتشغيل Postgres + Redis + Soketi

## التشغيل السريع

```bash
cp .env.example .env
docker compose up -d
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan queue:work
```

حسابات التجربة بعد `migrate --seed`:

| الدور    | البريد                 | كلمة المرور |
|----------|------------------------|-------------|
| Admin    | admin@example.com      | password    |
| Employee | ahmed@example.com      | password    |

## توثيق الـ API

- تفاعلي (Swagger UI): [http://localhost:8000/docs](http://localhost:8000/docs)
- OpenAPI: [`docs/openapi.yaml`](docs/openapi.yaml)
- مرجع نصي: [`docs/API.md`](docs/API.md)

بعد `php artisan serve` افتح `/docs`، اضغط **Authorize**، ثم الصق توكن تسجيل الدخول لتجربة المسارات مباشرة.

## ربط WhatsApp Cloud API

1. أنشئ تطبيق Meta واتساب بيزنس واحصل على `phone_number_id` و `access_token` و `app_secret`.
2. `POST /api/v1/whatsapp-accounts` بالبيانات.
3. من الاستجابة خذ `webhook_url` و `webhook_verify_token`.
4. في Meta Developer أضف Callback URL نفسه وVerify Token.
5. اشترك في أحداث `messages`.

الويب هوك:

- `GET  /api/v1/webhooks/whatsapp/{id}` تحقق الاشتراك
- `POST /api/v1/webhooks/whatsapp/{id}` استقبال الرسائل وحالات التسليم

التوقيع `X-Hub-Signature-256` يُتحقق منه إذا تم حفظ `app_secret`.

## المصادقة

```http
POST /api/v1/auth/login
{
  "email": "admin@example.com",
  "password": "password",
  "device_name": "office-1"
}
```

ثم:

```http
Authorization: Bearer {token}
Accept-Language: ar
```

كل جهاز يحصل على توكن مستقل (`GET /api/v1/auth/sessions`).

## أهم المسارات

| Method | Path | الوصف |
|--------|------|--------|
| POST | `/api/v1/auth/login` | تسجيل الدخول |
| GET | `/api/v1/auth/me` | المستخدم الحالي |
| GET | `/api/v1/dashboard` | إحصائيات الأدمن |
| GET/POST | `/api/v1/users` | الموظفون |
| GET/POST | `/api/v1/customers` | العملاء |
| GET | `/api/v1/conversations` | صندوق الوارد |
| POST | `/api/v1/conversations/{id}/messages` | إرسال رسالة / ملف |
| POST | `/api/v1/conversations/{id}/assign` | تعيين لموظف |
| POST | `/api/v1/conversations/{id}/close` | إغلاق المحادثة |
| GET | `/api/v1/search?q=` | بحث شامل |
| GET/POST | `/api/v1/tags` | الوسوم |
| GET/POST | `/api/v1/whatsapp-accounts` | أرقام واتساب |
| GET | `/api/v1/audit-logs` | سجل العمليات |
| GET | `/api/v1/notifications` | الإشعارات |

الاستجابة موحدة:

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 0 }
}
```

## Real-Time

قنوات خاصة (Laravel Echo + Soketi):

- `private-company.{companyId}`
- `private-user.{userId}`
- `private-conversation.{conversationId}`

أحداث:

- `message.created`
- `message.status`
- `conversation.assigned` / `created` / `closed` / `updated`
- إشعارات البث على قناة المستخدم

Auth endpoint: `POST /api/broadcasting/auth` مع Bearer token.

## الصلاحيات

Admin يملك الكل. Employee افتراضياً:

`customers.view/create/update`, `conversations.view/close`, `messages.view/send`, `notes.create`, `tags.view`

الأدمن يخصص صلاحيات إضافية عند إنشاء الموظف.

## الاختبارات

```bash
php artisan test
```

## البنية

```
Web / Mobile clients
        ↓ REST + WebSocket
     Laravel API
        ↓
 PostgreSQL + Redis + Queue
        ↓
 WhatsApp Cloud API
```
