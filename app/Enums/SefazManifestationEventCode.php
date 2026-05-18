<?php

namespace App\Enums;

enum SefazManifestationEventCode: int
{
    case OperationAcknowledgement = 210210;
    case OperationConfirmation = 210200;
    case OperationUnknown = 210220;
    case OperationNotPerformed = 210240;

    public static function fromManifestationEventType(ManifestationEventType $eventType): self
    {
        return match ($eventType) {
            ManifestationEventType::OperationAcknowledgement => self::OperationAcknowledgement,
            ManifestationEventType::OperationConfirmation => self::OperationConfirmation,
            ManifestationEventType::OperationUnknown => self::OperationUnknown,
            ManifestationEventType::OperationNotPerformed => self::OperationNotPerformed,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OperationAcknowledgement => 'Ciencia da Operacao',
            self::OperationConfirmation => 'Confirmacao da Operacao',
            self::OperationUnknown => 'Desconhecimento da Operacao',
            self::OperationNotPerformed => 'Operacao nao Realizada',
        };
    }
}
