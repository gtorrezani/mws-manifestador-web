<?php

namespace App\Enums;

enum AgentOperationalStatus: string
{
    case NotInstalled = 'not_installed';
    case PendingActivation = 'pending_activation';
    case Online = 'online';
    case Offline = 'offline';
    case Outdated = 'outdated';
    case Revoked = 'revoked';
    case Error = 'error';
    case ServiceStopped = 'service_stopped';
    case Unknown = 'unknown';
}
