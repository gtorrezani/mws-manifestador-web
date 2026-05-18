<?php

namespace App\Services\Sefaz\Xml;

use App\Services\Sefaz\Dto\SefazEventResponse;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class NfeEventResponseParser
{
    private const NFE_NAMESPACE = 'http://www.portalfiscal.inf.br/nfe';

    public function parse(string $responseXml): SefazEventResponse
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->preserveWhiteSpace = false;

        if (! $xml->loadXML($responseXml)) {
            throw new RuntimeException('Resposta SOAP da SEFAZ não contém XML válido.');
        }

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('nfe', self::NFE_NAMESPACE);

        $retEnvEventoNodes = $xpath->query('//nfe:retEnvEvento');
        $retEnvEvento = $retEnvEventoNodes === false ? null : $retEnvEventoNodes->item(0);
        if (! $retEnvEvento instanceof DOMElement) {
            throw new RuntimeException('Resposta SEFAZ não contém retEnvEvento.');
        }

        $infEventoNodes = $xpath->query('//nfe:retEvento/nfe:infEvento');
        $infEvento = $infEventoNodes === false ? null : $infEventoNodes->item(0);

        return new SefazEventResponse(
            batchStatusCode: $this->value($xpath, $retEnvEvento, 'cStat'),
            batchReason: $this->value($xpath, $retEnvEvento, 'xMotivo'),
            eventStatusCode: $infEvento instanceof DOMElement ? $this->value($xpath, $infEvento, 'cStat') : null,
            eventReason: $infEvento instanceof DOMElement ? $this->value($xpath, $infEvento, 'xMotivo') : null,
            eventProtocolNumber: $infEvento instanceof DOMElement ? $this->value($xpath, $infEvento, 'nProt') : null,
        );
    }

    private function value(DOMXPath $xpath, DOMElement $context, string $name): ?string
    {
        $nodes = $xpath->query('nfe:'.$name, $context);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node?->nodeValue !== '' ? $node?->nodeValue : null;
    }
}
