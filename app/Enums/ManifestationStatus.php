<?php

namespace App\Enums;

enum ManifestationStatus: string
{
    case NoManifestation = 'no_manifestation';
    case AcknowledgementRequested = 'acknowledgement_requested';
    case Acknowledged = 'acknowledged';
    case PendingFinalManifestation = 'pending_final_manifestation';
    case ConfirmationRequested = 'confirmation_requested';
    case Confirmed = 'confirmed';
    case UnknownRequested = 'unknown_requested';
    case Unknown = 'unknown';
    case NotPerformedRequested = 'not_performed_requested';
    case NotPerformed = 'not_performed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function isConclusive(): bool
    {
        return in_array($this, [
            self::Confirmed,
            self::Unknown,
            self::NotPerformed,
        ], true);
    }

    public function isRequested(): bool
    {
        return in_array($this, [
            self::AcknowledgementRequested,
            self::ConfirmationRequested,
            self::UnknownRequested,
            self::NotPerformedRequested,
        ], true);
    }
}
