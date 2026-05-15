<?php

namespace Tests\Unit\Support;

use App\Rules\ValidCpf;
use App\Support\Cpf;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CpfTest extends TestCase
{
    public function test_valid_cpf_without_mask_passes(): void
    {
        $validator = Validator::make(
            ['cpf' => '52998224725'],
            ['cpf' => ['required', new ValidCpf]],
        );

        $this->assertTrue($validator->passes());
        $this->assertTrue(Cpf::isValid('52998224725'));
    }

    public function test_valid_cpf_with_mask_passes(): void
    {
        $validator = Validator::make(
            ['cpf' => '529.982.247-25'],
            ['cpf' => ['required', new ValidCpf]],
        );

        $this->assertTrue($validator->passes());
        $this->assertTrue(Cpf::isValid('529.982.247-25'));
    }

    public function test_invalid_cpf_fails(): void
    {
        $validator = Validator::make(
            ['cpf' => '12345678900'],
            ['cpf' => ['required', new ValidCpf]],
        );

        $this->assertFalse($validator->passes());
        $this->assertFalse(Cpf::isValid('12345678900'));
    }

    public function test_repeated_sequence_fails(): void
    {
        $validator = Validator::make(
            ['cpf' => '11111111111'],
            ['cpf' => ['required', new ValidCpf]],
        );

        $this->assertFalse($validator->passes());
        $this->assertFalse(Cpf::isValid('11111111111'));
    }

    public function test_normalize_removes_mask(): void
    {
        $this->assertSame('52998224725', Cpf::normalize('529.982.247-25'));
    }

    public function test_format_applies_mask(): void
    {
        $this->assertSame('529.982.247-25', Cpf::format('52998224725'));
    }
}
