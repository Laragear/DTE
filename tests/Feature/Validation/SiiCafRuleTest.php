<?php

namespace Tests\Feature\Validation;

use Illuminate\Http\UploadedFile;
use Laragear\MetaTesting\Validation\InteractsWithValidator;
use Tests\DatabaseTestCase;
use Tests\Unit\Caf\Fixtures\CafFixture;

class SiiCafRuleTest extends DatabaseTestCase
{
    use InteractsWithValidator;

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_validates_a_valid_caf()
    {
        $this->validation([
            'caf' => UploadedFile::fake()->createWithContent('caf.xml', CafFixture::create()->xml()),
        ], [
            'caf' => 'sii_caf',
        ])->assertPasses();
    }

    public function test_validates_a_valid_caf_string()
    {
        $this->validation([
            'caf' => CafFixture::create()->xml(),
        ], [
            'caf' => 'sii_caf',
        ])->assertPasses();
    }

    public function test_validates_with_literal_rut_parameter()
    {
        $fixture = CafFixture::create();

        $this->validation([
            'caf' => $fixture->xml(),
        ], [
            'caf' => 'sii_caf:'.$fixture->issuer,
        ])->assertPasses();
    }

    public function test_validates_with_rut_parameter_field()
    {
        $fixture = CafFixture::create();

        $this->validation([
            'company_rut' => $fixture->issuer,
            'caf' => $fixture->xml(),
        ], [
            'company_rut' => 'required',
            'caf' => 'sii_caf:company_rut',
        ])->assertPasses();
    }

    public function test_validates_when_empty_but_not_when_required(): void
    {
        $this->validation([
            'company_rut' => '76.123.456-0',
            'caf' => '',
        ], [
            'company_rut' => 'required',
            'caf' => 'sii_caf:company_rut',
        ])->assertPasses();

        $this->validation([
            'company_rut' => '76.123.456-0',
            'caf' => '',
        ], [
            'company_rut' => 'required',
            'caf' => 'required|sii_caf:company_rut',
        ])->assertFails();
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_fails_validation_with_invalid_mime_type()
    {
        $fixture = UploadedFile::fake()->createWithContent('caf.pdf', CafFixture::create()->xml());

        $this->validation([
            'caf' => $fixture->mimeType('application/pdf'),
        ], [
            'caf' => 'sii_caf',
        ])->assertFails();
    }

    public function test_fails_validation_with_invalid_caf_xml()
    {
        $this->validation([
            'caf' => '<AUTORIZACION></AUTORIZACION>',
        ], [
            'caf' => 'sii_caf',
        ])->assertFails();
    }

    public function test_fails_validation_with_wrong_rut()
    {
        $this->validation([
            'company_rut' => '11.111.111-1',
            'caf' => CafFixture::create()->xml(),
        ], [
            'company_rut' => 'required',
            'caf' => 'sii_caf:company_rut',
        ])->assertFails();
    }

    public function test_fails_with_uploaded_text_file(): void
    {
        $this->validation([
            'company_rut' => '76.123.456-0',
            'caf' => UploadedFile::fake()->create('test.txt', 10, 'text/plain'),
        ], [
            'company_rut' => 'required',
            'caf' => 'sii_caf:company_rut',
        ])->assertFails();
    }
}
