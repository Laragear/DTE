---
name: laragear-dte-testing
description: "Use this skill to test when the application requires Laragear DTE services, classes and code. Do not use this skill when the library is not required, when outside testing scenarios, or outside testing files."
license: MIT
metadata:
  author: laragear
---

# Testing

Before testing, configure a fake issuer and CAF using the `InteractsWithSiiDte` trait, which sets up everything automatically.

## Level 1: Quick Setup with `InteractsWithSiiDte`

The `InteractsWithSiiDte` trait auto-invokes `setUpInteractsWithSiiDte()` which configures a fake issuer, CAFs, certificates, and fakes all builder facades. Plain PHPUnit users must call it manually inside `setUp()`.

```php
use Tests\TestCase;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Testing\InteractsWithSiiDte;

class MyTest extends TestCase
{
    use InteractsWithSiiDte;

    public function test_creates_invoice(): void
    {
        $dte = $this->newInvoice(
            receiver: '12.345.678-9',
            items: [['Consulting', 50000, 2]],
        )->create();

        $this->assertDteCreated(DteType::Invoice);
        $this->assertSame(100000, $dte->amount_net);
    }
}
```

Override `setUpCafs()` to configure additional document types:

```php
protected function setUpCafs(): array
{
    return [
        SiiRut::DEFAULT->value => [DteType::Invoice, DteType::Receipt],
    ];
}
```

## Level 2: Facade Fakes

Use `SiiInvoice::fake()` (and other `Sii*::fake()`) to intercept document creation without the trait. The fakes return in-memory `SiiDte` models without hitting the database, compilation pipeline, or SII servers.

```php
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Models\SiiDte;

public function test_it_creates_receipt_on_cart_payment()
{
    SiiInvoice::fake();

    $this->post('pay')->assertOk();

    SiiInvoice::assertCreated(1);

    $dte = SiiInvoice::lastCreated();
    
    $this->assertSame(DteType::Receipt, $dte->document_type);
}
```

Available facades: `SiiInvoice`, `SiiReceipt`, `SiiCreditNote`, `SiiDebitNote`, `SiiDispatchGuide`, `SiiPurchaseInvoice`, `SiiInvoiceLiquidation`.

Each provides `assertCreated(?int $times)`, `assertNotCreated()`, `created(): array`, and `lastCreated(): SiiDte`.

## Level 3: Cross-Facade Assertions

Use `DteFake` to aggregate creation records across all faked facades:

```php
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Facades\SiiCreditNote;
use Laragear\Dte\Testing\DteFake;

$fake = DteFake::fake();

SiiInvoice::fake()->issuedBy(...)->addItem('A', 1000)->create();
SiiCreditNote::fake()->issuedBy(...)->addItem('B', 500)->create();

$fake->assertCreated(times: 2);
$fake->assertCreatedFor(DteType::Invoice, times: 1);
$fake->created();    // All documents across all types
$fake->lastCreated(); // Most recent document
```

## Level 4: Silent Operation (No Faking)

The library, when it detects the application on `testing` environment, will process DTE normally but not hit SII servers. Use this by setting the CAF to create with `setUpCafs`, indicating the target RUT of the company and the DTE Type.

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laragear\Dte\Testing\InteractsWithSiiDte;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Testing\InteractsWithDigitalCertificates;

class MyTest extends TestCase
{
    use RefreshDatabase, InteractsWithSiiDte;
    
    protected function setUpCafs(): array
    {
        return [
            '76.132.456-0' => [DteType::Receipt]
        ];
    }
    
    public function test_it_creates_receipt_on_cart_payment()
    {
        $this->post('pay')->assertOk();
    }

}
```

## Level 5: Database Check

Use `$this->assertDatabase...` methods to assert DTE are stored into the database. Requires a CAF and Digital Certificate loaded (use `InteractsWithSiiDte` with `create(sync: true)`).

```php
use Laragear\Dte\Facades\SiiInvoice;

public function test_it_creates_receipt_on_cart_payment()
{
    SiiInvoice::fake()->receivedBy('12.345.678-9')
        ->addItem('Service', 5000)
        ->create(sync: true);

    $this->assertDatabaseHas('sii_dtes', [
        'amount_net' => 5000,
    ]);
}
```

## PDF Testing

Use `withPdfStubs($dte)` to mock the barcode generator and inject a minimal XML stub, then render the Blade view directly:

```php
$this->withPdfStubs($dte);

$html = $dte->pdf()->view()->render();
$this->assertStringContainsString('Timbre Electrónico', $html);
```

## Certificate Fakes

The `InteractsWithDigitalCertificates` trait (used by `InteractsWithSiiDte`) provides:

- `withFakeCertificate()` — returns a valid fake `.p12` certificate for any RUT.
- `withFakeCertificate($rut)` — returns a certificate only for the specified RUT.
- `expectsNoCertificate()` — returns null for all RUTs (no certificate available).
