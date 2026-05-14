<?php

namespace App\Enums;

enum CommandStatus: string
{
    case Pending = 'pending';
    case Locked = 'locked';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
