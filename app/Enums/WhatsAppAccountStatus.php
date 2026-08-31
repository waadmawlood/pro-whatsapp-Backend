<?php

namespace App\Enums;

enum WhatsAppAccountStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';
}
