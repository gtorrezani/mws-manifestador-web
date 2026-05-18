<?php

namespace App\Services\Sefaz\Xml;

use App\Enums\FiscalEnvironment;
use App\Enums\ManifestationEventType;
use App\Enums\SefazManifestationEventCode;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\RecipientManifestation;
use App\Services\Sefaz\SefazEndpointResolver;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

class NfeManifestationXmlBuilder
{
    private const NFE_NAMESPACE = 'http://www.portalfiscal.inf.br/nfe';

    public function __construct(
        private readonly SefazEndpointResolver $endpointResolver,
    ) {}

    public function build(
        Company $company,
        FiscalDocument $document,
        RecipientManifestation $manifestation,
        ManifestationEventType $eventType,
        ?string $justification,
        string $lotId,
    ): string {
        $eventCode = SefazManifestationEventCode::fromManifestationEventType($eventType);
        $sequence = 1;
        $eventId = sprintf('ID%d%s%02d', $eventCode->value, $document->access_key, $sequence);

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = false;
        $xml->preserveWhiteSpace = false;

        $envEvento = $this->element($xml, 'envEvento');
        $envEvento->setAttribute('versao', '1.00');
        $xml->appendChild($envEvento);

        $envEvento->appendChild($this->element($xml, 'idLote', $lotId));

        $evento = $this->element($xml, 'evento');
        $evento->setAttribute('versao', '1.00');
        $envEvento->appendChild($evento);

        $infEvento = $this->element($xml, 'infEvento');
        $infEvento->setAttribute('Id', $eventId);
        $evento->appendChild($infEvento);

        $infEvento->appendChild($this->element($xml, 'cOrgao', (string) $this->endpointResolver->ibgeCodeForUf($company->uf)));
        $infEvento->appendChild($this->element($xml, 'tpAmb', $this->environmentCode($company->fiscal_environment)));
        $infEvento->appendChild($this->element($xml, 'CNPJ', $company->cnpj));
        $infEvento->appendChild($this->element($xml, 'chNFe', $document->access_key));
        $infEvento->appendChild($this->element($xml, 'dhEvento', now()->format('Y-m-d\TH:i:sP')));
        $infEvento->appendChild($this->element($xml, 'tpEvento', (string) $eventCode->value));
        $infEvento->appendChild($this->element($xml, 'nSeqEvento', (string) $sequence));
        $infEvento->appendChild($this->element($xml, 'verEvento', '1.00'));

        $detEvento = $this->element($xml, 'detEvento');
        $detEvento->setAttribute('versao', '1.00');
        $detEvento->appendChild($this->element($xml, 'descEvento', $eventCode->description()));

        if ($eventType === ManifestationEventType::OperationNotPerformed) {
            $justification = trim((string) $justification);
            if ($justification === '') {
                throw new InvalidArgumentException('Operação não realizada exige justificativa.');
            }

            $detEvento->appendChild($this->element($xml, 'xJust', $justification));
        }

        $infEvento->appendChild($detEvento);

        return $xml->saveXML($xml->documentElement) ?: '';
    }

    private function environmentCode(FiscalEnvironment $environment): string
    {
        return $environment === FiscalEnvironment::Production ? '1' : '2';
    }

    private function element(DOMDocument $xml, string $name, ?string $value = null): DOMElement
    {
        $element = $xml->createElementNS(self::NFE_NAMESPACE, $name);

        if ($value !== null) {
            $element->appendChild($xml->createTextNode($value));
        }

        return $element;
    }
}
