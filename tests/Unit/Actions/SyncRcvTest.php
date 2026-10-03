<?php

namespace Tests\Unit\Actions;

use Laragear\Dte\Actions\Cuadratura\Sync;
use Laragear\Dte\Actions\RcvParsing\Parse;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Actions\SyncRcv;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\RcvType;
use Laragear\Rut\Rut;
use Mockery;
use Mockery\MockInterface;
use Override;
use RuntimeException;
use Tests\DatabaseTestCase;
use function file_put_contents;
use function sys_get_temp_dir;

class SyncRcvTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            IssuerData::make(
                '76.111.222-3',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            ),
            '76.111.222-3',
        ));
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink(sys_get_temp_dir().'/dummy-compras.csv');

        parent::tearDown();
    }

    public function test_throws_when_no_issuer(): void
    {
        $csvPath = sys_get_temp_dir().'/dummy-compras.csv';
        file_put_contents($csvPath, ''); // Create dummy file

        $this->mock(ConfigurationManager::class)
            ->expects('getIssuer')
            ->andThrow(new RuntimeException('No Issuer resolver has been registered.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('There is no issuer to be resolved and sync RCV.');

        $this->app->make(SyncRcv::class)->handle($csvPath, 'compras');
    }

    public function test_executes_successfully_and_returns_sync_results(): void
    {
        $csvPath = sys_get_temp_dir().'/dummy-compras.csv';
        file_put_contents($csvPath, ''); // Create dummy file

        $parsingContext = new ParsingContext('fake', RcvType::Purchases, Rut::parse('76111222-3'));

        $this->mock(Parse::class, function (MockInterface $mock) use ($csvPath, $parsingContext) {
            $mock
                ->expects('forBatch')
                ->with($csvPath, RcvType::Purchases, Mockery::on(fn(Rut $rut) => $rut->format() === '76.111.222-3'))
                ->andReturn($parsingContext);
        });

        $this->mock(Sync::class, function (MockInterface $mock) use ($parsingContext) {
            $mock
                ->expects('forParsing')
                ->with($parsingContext)
                ->andReturn([
                    'matched' => 5,
                    'phantoms' => 2,
                    'discrepancies' => 1,
                    'orphans' => 3,
                ]);
        });

        $action = $this->app->make(SyncRcv::class);
        $metrics = $action->handle($csvPath, 'compras');

        static::assertEquals(
            [
                'matched' => 5,
                'phantoms' => 2,
                'discrepancies' => 1,
                'orphans' => 3,
            ],
            $metrics,
        );

        unlink($csvPath);
    }
}
