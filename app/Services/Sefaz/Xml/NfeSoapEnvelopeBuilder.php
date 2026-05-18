<?php

namespace App\Services\Sefaz\Xml;

use App\Services\Sefaz\Dto\SefazEndpoint;
use RuntimeException;

class NfeSoapEnvelopeBuilder
{
    private const SOAP12_NAMESPACE = 'http://www.w3.org/2003/05/soap-envelope';

    public function build(SefazEndpoint $endpoint, string $nfeXml): string
    {
        $payload = new \DOMDocument('1.0', 'UTF-8');
        $payload->preserveWhiteSpace = false;

        if (! $payload->loadXML($nfeXml)) {
            throw new RuntimeException('XML NF-e inválido para envelope SOAP.');
        }

        return sprintf(
            '<soap12:Envelope xmlns:soap12="%s"><soap12:Body><%s xmlns="%s"><nfeDadosMsg>%s</nfeDadosMsg></%s></soap12:Body></soap12:Envelope>',
            self::SOAP12_NAMESPACE,
            $endpoint->operationName,
            $endpoint->operationNamespace,
            $nfeXml,
            $endpoint->operationName,
        );
    }
}
