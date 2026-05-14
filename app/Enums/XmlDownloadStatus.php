<?php

namespace App\Enums;

enum XmlDownloadStatus: string
{
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Processing = 'processing';
    case Available = 'available';
    case Failed = 'failed';
    case Unavailable = 'unavailable';
}
