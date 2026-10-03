<?php

namespace Tests\Unit\Xml;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Laragear\Dte\DteServiceProvider;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XsdValidator;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class XsdValidatorTest extends TestCase
{
    public function test_throws_on_validation_failure(): void
    {
        $validator = $this->app->make(XsdValidator::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs("XSD validation failed: Element 'Invalid': No matching global declaration available for the validation root.");

        $validator->validate('<Invalid/>', 'DTE_v10.xsd');
    }

    public function test_throws_when_schema_not_found(): void
    {
        $validator = $this->app->make(XsdValidator::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('XSD schema not found: NonExistent.xsd');

        $validator->validate('<Any/>', 'NonExistent.xsd');
    }

    public function test_uses_app_resource_path_when_xsd_exists_there(): void
    {
        // Use a real XSD file path that exists in the package assets
        $realXsdPath = DteServiceProvider::XSD_ASSETS.'/DTE_v10.xsd';

        $fileSystem = Mockery::mock(Filesystem::class);
        $fileSystem->expects('exists')->once()->andReturn(true);

        $app = Mockery::mock(Application::class);
        $app->expects('resourcePath')->with('xsd/DTE_v10.xsd')->andReturn($realXsdPath);

        $validator = new XsdValidator($app, $fileSystem, $this->app->make(XmlDomFactory::class),
            $this->app->make(LibxmlProxy::class));

        $this->expectException(RuntimeException::class);

        $validator->validate('<Invalid/>', 'DTE_v10.xsd');
    }
}
