<?php

namespace App\Enums;

enum FiscalDocumentBulkAction: string
{
    case DownloadXml = 'download_xml';
    case ExportZip = 'export_zip';
    case Acknowledge = 'acknowledge';
    case ManifestConfirmation = 'manifest_confirmation';
    case ManifestUnknown = 'manifest_unknown';
    case ManifestNotPerformed = 'manifest_not_performed';

    public function commandType(): ?CommandType
    {
        return match ($this) {
            self::DownloadXml => CommandType::DownloadXmlByAccessKey,
            self::ExportZip => CommandType::ExportXmlZip,
            default => null,
        };
    }

    public function manifestationEventType(): ?ManifestationEventType
    {
        return match ($this) {
            self::Acknowledge => ManifestationEventType::OperationAcknowledgement,
            self::ManifestConfirmation => ManifestationEventType::OperationConfirmation,
            self::ManifestUnknown => ManifestationEventType::OperationUnknown,
            self::ManifestNotPerformed => ManifestationEventType::OperationNotPerformed,
            default => null,
        };
    }

    public function requiresExplicitConfirmation(): bool
    {
        return in_array($this, [
            self::ManifestConfirmation,
            self::ManifestUnknown,
            self::ManifestNotPerformed,
        ], true);
    }

    public function requiresJustification(): bool
    {
        return $this === self::ManifestNotPerformed;
    }
}
