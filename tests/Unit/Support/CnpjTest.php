<?php

namespace Tests\Unit\Support;

use App\Support\Cnpj;
use PHPUnit\Framework\TestCase;

class CnpjTest extends TestCase
{
    public function test_normalize_removes_mask(): void
    {
        $this->assertSame('12345678000195', Cnpj::normalize('12.345.678/0001-95'));
    }

    public function test_format_applies_mask(): void
    {
        $this->assertSame('12.345.678/0001-95', Cnpj::format('12345678000195'));
    }
}
