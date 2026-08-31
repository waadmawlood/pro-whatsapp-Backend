<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';
    case Audio = 'audio';
    case Sticker = 'sticker';
    case Location = 'location';
    case Contacts = 'contacts';
    case Unknown = 'unknown';

    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Video, self::Document, self::Audio, self::Sticker], true);
    }
}
