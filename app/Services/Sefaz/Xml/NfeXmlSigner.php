<?php

namespace App\Services\Sefaz\Xml;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

class NfeXmlSigner
{
    private const XMLDSIG_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';

    private const C14N_ALGORITHM = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

    private const RSA_SHA1_ALGORITHM = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';

    private const SHA1_ALGORITHM = 'http://www.w3.org/2000/09/xmldsig#sha1';

    private const ENVELOPED_SIGNATURE_ALGORITHM = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    public function sign(string $xmlContent, string $certificatePem, string $privateKeyPem, string $referenceIdAttribute = 'Id'): string
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = false;
        $xml->preserveWhiteSpace = false;

        if (! $xml->loadXML($xmlContent)) {
            throw new RuntimeException('XML de manifestação NF-e inválido.');
        }

        $elementToSign = $this->findElementToSign($xml, $referenceIdAttribute);
        $referenceId = $elementToSign->getAttribute($referenceIdAttribute);
        if ($referenceId === '') {
            throw new RuntimeException('Identificador do XML de manifestação NF-e não encontrado.');
        }

        $digestValue = base64_encode(sha1($this->canonicalize($elementToSign), true));
        $signature = $this->buildSignatureElement($xml, $referenceId, $digestValue, $certificatePem);

        $parent = $elementToSign->parentNode;
        if (! $parent instanceof DOMNode) {
            throw new RuntimeException('Elemento de manifestação NF-e sem nó pai para assinatura.');
        }

        $parent->appendChild($signature);

        $signedInfo = $signature->getElementsByTagNameNS(self::XMLDSIG_NAMESPACE, 'SignedInfo')->item(0);
        if (! $signedInfo instanceof DOMElement) {
            throw new RuntimeException('SignedInfo não foi criado para assinatura NF-e.');
        }

        $canonicalSignedInfo = $this->canonicalize($signedInfo);
        $signatureBytes = '';

        if (! openssl_sign($canonicalSignedInfo, $signatureBytes, $privateKeyPem, OPENSSL_ALGO_SHA1)) {
            throw new RuntimeException('Não foi possível assinar XML de manifestação NF-e com certificado A1.');
        }

        $signatureValue = $signature->getElementsByTagNameNS(self::XMLDSIG_NAMESPACE, 'SignatureValue')->item(0);
        if (! $signatureValue instanceof DOMElement) {
            throw new RuntimeException('SignatureValue não foi criado para assinatura NF-e.');
        }

        $signatureValue->nodeValue = base64_encode($signatureBytes);

        return $xml->saveXML($xml->documentElement) ?: '';
    }

    private function findElementToSign(DOMDocument $xml, string $referenceIdAttribute): DOMElement
    {
        foreach ($xml->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement && $element->hasAttribute($referenceIdAttribute)) {
                return $element;
            }
        }

        throw new RuntimeException("Elemento com atributo {$referenceIdAttribute} não encontrado para assinatura NF-e.");
    }

    private function buildSignatureElement(DOMDocument $xml, string $referenceId, string $digestValue, string $certificatePem): DOMElement
    {
        $signature = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'Signature');
        $signedInfo = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'SignedInfo');
        $signature->appendChild($signedInfo);

        $canonicalizationMethod = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', self::C14N_ALGORITHM);
        $signedInfo->appendChild($canonicalizationMethod);

        $signatureMethod = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', self::RSA_SHA1_ALGORITHM);
        $signedInfo->appendChild($signatureMethod);

        $reference = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'Reference');
        $reference->setAttribute('URI', '#'.$referenceId);
        $signedInfo->appendChild($reference);

        $transforms = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'Transforms');
        $reference->appendChild($transforms);

        $enveloped = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'Transform');
        $enveloped->setAttribute('Algorithm', self::ENVELOPED_SIGNATURE_ALGORITHM);
        $transforms->appendChild($enveloped);

        $canonical = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'Transform');
        $canonical->setAttribute('Algorithm', self::C14N_ALGORITHM);
        $transforms->appendChild($canonical);

        $digestMethod = $xml->createElementNS(self::XMLDSIG_NAMESPACE, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::SHA1_ALGORITHM);
        $reference->appendChild($digestMethod);
        $reference->appendChild($xml->createElementNS(self::XMLDSIG_NAMESPACE, 'DigestValue', $digestValue));

        $signature->appendChild($xml->createElementNS(self::XMLDSIG_NAMESPACE, 'SignatureValue'));

        $keyInfo = $signature->appendChild($xml->createElementNS(self::XMLDSIG_NAMESPACE, 'KeyInfo'));
        $x509Data = $keyInfo->appendChild($xml->createElementNS(self::XMLDSIG_NAMESPACE, 'X509Data'));
        $x509Data->appendChild($xml->createElementNS(self::XMLDSIG_NAMESPACE, 'X509Certificate', $this->certificateBody($certificatePem)));

        return $signature;
    }

    private function canonicalize(DOMNode $node): string
    {
        $canonical = $node->C14N(false, false);

        if (! is_string($canonical)) {
            throw new RuntimeException('Não foi possível canonicalizar XML NF-e.');
        }

        return $canonical;
    }

    private function certificateBody(string $certificatePem): string
    {
        return trim(str_replace([
            '-----BEGIN CERTIFICATE-----',
            '-----END CERTIFICATE-----',
            "\r",
            "\n",
            ' ',
        ], '', $certificatePem));
    }
}
