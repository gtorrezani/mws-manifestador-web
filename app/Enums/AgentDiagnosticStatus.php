<?php

namespace App\Enums;

enum AgentDiagnosticStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';
}
