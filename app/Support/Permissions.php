<?php

namespace App\Support;

final class Permissions
{
    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_CREATE = 'customers.create';

    public const CUSTOMERS_UPDATE = 'customers.update';

    public const CUSTOMERS_DELETE = 'customers.delete';

    public const CONVERSATIONS_VIEW = 'conversations.view';

    public const CONVERSATIONS_VIEW_ALL = 'conversations.view_all';

    public const CONVERSATIONS_ASSIGN = 'conversations.assign';

    public const CONVERSATIONS_CLOSE = 'conversations.close';

    public const CONVERSATIONS_DELETE = 'conversations.delete';

    public const MESSAGES_VIEW = 'messages.view';

    public const MESSAGES_SEND = 'messages.send';

    public const NOTES_CREATE = 'notes.create';

    public const TAGS_VIEW = 'tags.view';

    public const TAGS_MANAGE = 'tags.manage';

    public const USERS_VIEW = 'users.view';

    public const USERS_MANAGE = 'users.manage';

    public const WHATSAPP_MANAGE = 'whatsapp.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const AUDIT_VIEW = 'audit.view';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CUSTOMERS_VIEW,
            self::CUSTOMERS_CREATE,
            self::CUSTOMERS_UPDATE,
            self::CUSTOMERS_DELETE,
            self::CONVERSATIONS_VIEW,
            self::CONVERSATIONS_VIEW_ALL,
            self::CONVERSATIONS_ASSIGN,
            self::CONVERSATIONS_CLOSE,
            self::CONVERSATIONS_DELETE,
            self::MESSAGES_VIEW,
            self::MESSAGES_SEND,
            self::NOTES_CREATE,
            self::TAGS_VIEW,
            self::TAGS_MANAGE,
            self::USERS_VIEW,
            self::USERS_MANAGE,
            self::WHATSAPP_MANAGE,
            self::REPORTS_VIEW,
            self::AUDIT_VIEW,
        ];
    }

    /**
     * @return list<string>
     */
    public static function employeeDefaults(): array
    {
        return [
            self::CUSTOMERS_VIEW,
            self::CUSTOMERS_CREATE,
            self::CUSTOMERS_UPDATE,
            self::CONVERSATIONS_VIEW,
            self::CONVERSATIONS_CLOSE,
            self::MESSAGES_VIEW,
            self::MESSAGES_SEND,
            self::NOTES_CREATE,
            self::TAGS_VIEW,
        ];
    }
}
