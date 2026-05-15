<?php

namespace Tests\Feature\Certificate;

use App\Http\Requests\Certificate\StoreA1CertificateRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreA1CertificateRequestTest extends TestCase
{
    public function test_pfx_file_with_generic_mime_type_passes_upload_validation(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'certificado.pfx',
            'pfx-content',
        );

        $validator = Validator::make([
            'certificate_file' => $file,
            'password' => 'secret',
        ], (new StoreA1CertificateRequest)->rules());

        $this->assertTrue($validator->passes(), $validator->errors()->first('certificate_file'));
    }

    public function test_non_certificate_extension_fails_upload_validation(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'certificado.txt',
            'pfx-content',
        );

        $validator = Validator::make([
            'certificate_file' => $file,
            'password' => 'secret',
        ], (new StoreA1CertificateRequest)->rules());

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('certificate_file'));
    }
}
