<?php

namespace App\Enums;

enum ManifestationEventType: string
{
    case OperationAcknowledgement = 'operation_acknowledgement';
    case OperationConfirmation = 'operation_confirmation';
    case OperationUnknown = 'operation_unknown';
    case OperationNotPerformed = 'operation_not_performed';
}
