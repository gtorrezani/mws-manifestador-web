<?php

namespace Tests\Unit\Certificates;

use App\Services\Certificates\A1CertificateInspector;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class A1CertificateInspectorTest extends TestCase
{
    public function test_extract_cnpj_accepts_masked_document(): void
    {
        $inspector = new A1CertificateInspector;
        $method = (new ReflectionClass($inspector))->getMethod('extractCnpj');

        $this->assertSame(
            '12345678000195',
            $method->invoke($inspector, ['subject' => ['CN' => 'Empresa 12.345.678/0001-95']], 'certificate'),
        );
    }
}
