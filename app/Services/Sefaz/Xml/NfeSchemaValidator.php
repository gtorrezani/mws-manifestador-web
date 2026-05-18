<?php

namespace App\Services\Sefaz\Xml;

use DOMDocument;
use LibXMLError;
use RuntimeException;

class NfeSchemaValidator
{
    /**
     * @return list<string>
     */
    public function validateEnvEvento(string $xmlContent): array
    {
        $schemaPath = $this->schemaPath('envEvento_v1.00.xsd');
        if (! is_file($schemaPath)) {
            throw new RuntimeException('Schema envEvento_v1.00.xsd não encontrado para validação NF-e.');
        }

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->preserveWhiteSpace = false;

        if (! $xml->loadXML($xmlContent)) {
            return ['XML de manifestação NF-e inválido.'];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $valid = $xml->schemaValidate($schemaPath);
        $errors = array_map(
            static fn (LibXMLError $error): string => trim($error->message),
            libxml_get_errors(),
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $valid ? [] : array_values(array_filter($errors));
    }

    private function schemaPath(string $file): string
    {
        $basePath = config('sefaz.nfe_schema_path', resource_path('schemas/nfe'));

        if (! is_string($basePath) || $basePath === '') {
            return resource_path('schemas/nfe/'.$file);
        }

        return rtrim($basePath, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.$file;
    }
}
