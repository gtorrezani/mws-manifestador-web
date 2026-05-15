<?php

namespace App\Enums;

enum AgentStatus: string
{
    case Pending = 'pending';
    case Online = 'online';
    case Offline = 'offline';
    case Outdated = 'outdated';
    case Error = 'error';
    case Revoked = 'revoked';
    case ServiceStopped = 'service_stopped';
}
