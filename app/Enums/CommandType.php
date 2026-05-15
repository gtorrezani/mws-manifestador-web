<?php

namespace App\Enums;

enum CommandType: string
{
    case SyncFiscalDocuments = 'sync_fiscal_documents';
    case ManifestAcknowledgement = 'manifest_acknowledgement';
    case ManifestConfirmation = 'manifest_confirmation';
    case ManifestUnknown = 'manifest_unknown';
    case ManifestNotPerformed = 'manifest_not_performed';
    case DownloadXmlByAccessKey = 'download_xml_by_access_key';
    case DownloadXmlByPeriod = 'download_xml_by_period';
    case ExportXmlZip = 'export_xml_zip';
    case TestCertificate = 'test_certificate';
    case ListCertificates = 'list_certificates';
    case TestSefazConnectivity = 'test_sefaz_connectivity';
    case AgentDiagnosticsRequested = 'agent_diagnostics_requested';
}
