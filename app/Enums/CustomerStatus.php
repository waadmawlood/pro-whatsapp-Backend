<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Blocked = 'blocked';
}
