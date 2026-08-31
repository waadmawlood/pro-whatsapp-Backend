<?php

namespace App\Enums;

enum CustomerChatType: string
{
    case Direct = 'direct';
    case Group = 'group';

    public function isGroup(): bool
    {
        return $this === self::Group;
    }
}
