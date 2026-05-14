<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case PendingValidation = 'pending_validation';
    case Active = 'active';
    case Expired = 'expired';
    case Invalid = 'invalid';
    case Revoked = 'revoked';
}
