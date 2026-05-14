<?php

namespace App\Enums;

enum SefazRequestStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Succeeded = 'succeeded';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Timeout = 'timeout';
}
