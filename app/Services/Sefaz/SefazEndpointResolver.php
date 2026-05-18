<?php

namespace App\Services\Sefaz;

use App\Enums\FiscalEnvironment;
use App\Services\Sefaz\Dto\SefazEndpoint;
use InvalidArgumentException;
use RuntimeException;

class SefazEndpointResolver
{
    private const NFE_WSDL_NAMESPACE = 'http://www.portalfiscal.inf.br/nfe/wsdl';

    /** @var array<string, int> */
    private const UF_CODES = [
        'RO' => 11,
        'AC' => 12,
        'AM' => 13,
        'RR' => 14,
        'PA' => 15,
        'AP' => 16,
        'TO' => 17,
        'MA' => 21,
        'PI' => 22,
        'CE' => 23,
        'RN' => 24,
        'PB' => 25,
        'PE' => 26,
        'AL' => 27,
        'SE' => 28,
        'BA' => 29,
        'MG' => 31,
        'ES' => 32,
        'RJ' => 33,
        'SP' => 35,
        'PR' => 41,
        'SC' => 42,
        'RS' => 43,
        'MS' => 50,
        'MT' => 51,
        'GO' => 52,
        'DF' => 53,
    ];

    /** @var array<string, string> */
    private const PRODUCTION_EVENT_ENDPOINTS = [
        'AN' => 'https://www.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx',
        'SP' => 'https://nfe.fazenda.sp.gov.br/ws/nferecepcaoevento4.asmx',
        'PR' => 'https://nfe.sefa.pr.gov.br/nfe/NFeRecepcaoEvento4',
        'RS' => 'https://nfe.sefazrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx',
        'MG' => 'https://nfe.fazenda.mg.gov.br/nfe2/services/NFeRecepcaoEvento4',
        'BA' => 'https://nfe.sefaz.ba.gov.br/webservices/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx',
    ];

    /** @var array<string, string> */
    private const HOMOLOGATION_EVENT_ENDPOINTS = [
        'AN' => 'https://hom1.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx',
        'SP' => 'https://homologacao.nfe.fazenda.sp.gov.br/ws/nferecepcaoevento4.asmx',
        'PR' => 'https://homologacao.nfe.sefa.pr.gov.br/nfe/NFeRecepcaoEvento4',
        'RS' => 'https://nfe-homologacao.sefazrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx',
        'MG' => 'https://hnfe.fazenda.mg.gov.br/nfe2/services/NFeRecepcaoEvento4',
    ];

    public function resolveNfeRecepcaoEvento(FiscalEnvironment $environment, string $uf): SefazEndpoint
    {
        $normalizedUf = strtoupper($uf);
        $endpoints = $environment === FiscalEnvironment::Production
            ? self::PRODUCTION_EVENT_ENDPOINTS
            : self::HOMOLOGATION_EVENT_ENDPOINTS;

        $endpointUf = array_key_exists($normalizedUf, $endpoints) ? $normalizedUf : 'AN';
        $url = $endpoints[$endpointUf] ?? null;

        if (! is_string($url)) {
            throw new RuntimeException("Endpoint SEFAZ não configurado para manifestação NF-e em {$environment->value}/{$uf}.");
        }

        return new SefazEndpoint(
            environment: $environment,
            uf: $endpointUf,
            url: $url,
            soapAction: self::NFE_WSDL_NAMESPACE.'/NFeRecepcaoEvento4/nfeRecepcaoEvento',
            operationName: 'nfeRecepcaoEvento',
            operationNamespace: self::NFE_WSDL_NAMESPACE.'/NFeRecepcaoEvento4',
        );
    }

    public function ibgeCodeForUf(string $uf): int
    {
        $normalizedUf = strtoupper($uf);
        $code = self::UF_CODES[$normalizedUf] ?? null;

        if (! is_int($code)) {
            throw new InvalidArgumentException("UF inválida para manifestação NF-e: {$uf}.");
        }

        return $code;
    }
}
