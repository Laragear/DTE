# DTE
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/dte.svg)](https://packagist.org/packages/laragear/dte)
[![Latest stable test run](https://github.com/Laragear/Dte/actions/workflows/php.yml/badge.svg)](https://github.com/Laragear/Dte/actions)
[![Codecov Coverage](https://codecov.io/gh/Laragear/Dte/graph/badge.svg?token=BJMBVZNPM8)](https://codecov.io/gh/Laragear/Dte)
[![Maintainability](https://qlty.sh/badges/eb9ec1dc-4587-46cc-9261-6dea405e0b76/maintainability.svg)](https://qlty.sh/gh/Laragear/projects/Dte)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_Dte&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_Dte)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/13.x/octane#introduction)
[![Laravel Boost Compatibility](https://img.shields.io/badge/Laravel%20Boost-Compatible-purple?logo=data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCEtLSBMaWNlbnNlOiBNSVQuIE1hZGUgYnkgZnJhbWV3b3JrN2lvOiBodHRwczovL2dpdGh1Yi5jb20vZnJhbWV3b3JrN2lvL2ZyYW1ld29yazctaWNvbnMgLS0+CjxzdmcgZmlsbD0iI2ZmZmZmZiIgdmlld0JveD0iMCAwIDU2IDU2IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxwYXRoIGQ9Ik0gMjYuNjg3NSAxMi42NjAyIEMgMjYuOTY4NyAxMi42NjAyIDI3LjEwOTQgMTIuNDk2MSAyNy4xNzk3IDEyLjIzODMgQyAyNy45MDYyIDguMzI0MiAyNy44NTk0IDguMjMwNSAzMS45Mzc1IDcuNDU3MCBDIDMyLjIxODcgNy40MTAyIDMyLjM4MjggNy4yNDYxIDMyLjM4MjggNi45NjQ4IEMgMzIuMzgyOCA2LjY4MzYgMzIuMjE4NyA2LjUxOTUgMzEuOTM3NSA2LjQ3MjYgQyAyNy44ODI4IDUuNjUyNCAyOC4wMDAwIDUuNTU4NiAyNy4xNzk3IDEuNjkxNCBDIDI3LjEwOTQgMS40MzM2IDI2Ljk2ODcgMS4yNjk1IDI2LjY4NzUgMS4yNjk1IEMgMjYuNDA2MiAxLjI2OTUgMjYuMjY1NiAxLjQzMzYgMjYuMTk1MyAxLjY5MTQgQyAyNS4zNzUwIDUuNTU4NiAyNS41MTU2IDUuNjUyNCAyMS40Mzc1IDYuNDcyNiBDIDIxLjE3OTcgNi41MTk1IDIwLjk5MjIgNi42ODM2IDIwLjk5MjIgNi45NjQ4IEMgMjAuOTkyMiA3LjI0NjEgMjEuMTc5NyA3LjQxMDIgMjEuNDM3NSA3LjQ1NzAgQyAyNS41MTU2IDguMjc3NCAyNS40Njg3IDguMzI0MiAyNi4xOTUzIDEyLjIzODMgQyAyNi4yNjU2IDEyLjQ5NjEgMjYuNDA2MiAxMi42NjAyIDI2LjY4NzUgMTIuNjYwMiBaIE0gMTUuMzQzOCAyOC43ODUyIEMgMTUuNzg5MSAyOC43ODUyIDE2LjA5MzggMjguNTAzOSAxNi4xNDA2IDI4LjA4MjEgQyAxNi45ODQ0IDIxLjgyNDIgMTcuMTk1MyAyMS44MjQyIDIzLjY2NDEgMjAuNTgyMSBDIDI0LjA4NjAgMjAuNTExNyAyNC4zOTA2IDIwLjIzMDUgMjQuMzkwNiAxOS43ODUyIEMgMjQuMzkwNiAxOS4zNjMzIDI0LjA4NjAgMTkuMDU4NiAyMy42NjQxIDE4Ljk4ODMgQyAxNy4xOTUzIDE4LjA5NzcgMTYuOTYwOSAxNy44ODY3IDE2LjE0MDYgMTEuNTExNyBDIDE2LjA5MzggMTEuMDg5OSAxNS43ODkxIDEwLjc4NTIgMTUuMzQzOCAxMC43ODUyIEMgMTQuOTIxOSAxMC43ODUyIDE0LjYxNzIgMTEuMDg5OSAxNC41NzAzIDExLjUzNTIgQyAxMy43OTY5IDE3LjgxNjQgMTMuNDY4NyAxNy43OTMwIDcuMDQ2OSAxOC45ODgzIEMgNi42MjUwIDE5LjA4MjEgNi4zMjAzIDE5LjM2MzMgNi4zMjAzIDE5Ljc4NTIgQyA2LjMyMDMgMjAuMjUzOSA2LjYyNTAgMjAuNTExNyA3LjE0MDYgMjAuNTgyMSBDIDEzLjUxNTYgMjEuNjEzMyAxMy43OTY5IDIxLjc3NzQgMTQuNTcwMyAyOC4wMzUyIEMgMTQuNjE3MiAyOC41MDM5IDE0LjkyMTkgMjguNzg1MiAxNS4zNDM4IDI4Ljc4NTIgWiBNIDMxLjIzNDQgNTQuNzMwNSBDIDMxLjg0MzggNTQuNzMwNSAzMi4yODkxIDU0LjI4NTIgMzIuNDA2MiA1My42NTI0IEMgMzQuMDcwMyA0MC44MDg2IDM1Ljg3NTAgMzguODYzMyA0OC41NzgxIDM3LjQ1NzAgQyA0OS4yMzQ0IDM3LjM4NjcgNDkuNjc5NyAzNi44OTQ1IDQ5LjY3OTcgMzYuMjg1MiBDIDQ5LjY3OTcgMzUuNjc1OCA0OS4yMzQ0IDM1LjIwNzAgNDguNTc4MSAzNS4xMTMzIEMgMzUuODc1MCAzMy43MDcwIDM0LjA3MDMgMzEuNzYxNyAzMi40MDYyIDE4LjkxODAgQyAzMi4yODkxIDE4LjI4NTIgMzEuODQzOCAxNy44NjMzIDMxLjIzNDQgMTcuODYzMyBDIDMwLjYyNTAgMTcuODYzMyAzMC4xNzk3IDE4LjI4NTIgMzAuMDg2MCAxOC45MTgwIEMgMjguNDIxOSAzMS43NjE3IDI2LjU5MzggMzMuNzA3MCAxMy45MTQwIDM1LjExMzMgQyAxMy4yMzQ0IDM1LjIwNzAgMTIuNzg5MSAzNS42NzU4IDEyLjc4OTEgMzYuMjg1MiBDIDEyLjc4OTEgMzYuODk0NSAxMy4yMzQ0IDM3LjM4NjcgMTMuOTE0MCAzNy40NTcwIEMgMjYuNTcwMyAzOS4xMjExIDI4LjMyODEgNDAuODMyMSAzMC4wODYwIDUzLjY1MjQgQyAzMC4xNzk3IDU0LjI4NTIgMzAuNjI1MCA1NC43MzA1IDMxLjIzNDQgNTQuNzMwNSBaIi8+PC9zdmc+)](https://laravel.com/docs/13.x/boost)

Comply with SII within your Laravel application.

```php
use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy('76.543.210-K', 'Helados S.A.')
    ->addItem('Crema de Leche', 12_000)
    ->build();
```

> [!TIP]
>
> Building a Chilean application that manages RUT numbers? Check out [Laragear Rut](https://github.com/Laragear/Rut).

## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date, and maintainable. Alternatively, you can **spread the word on social media!**

## Requirements

* PHP 8.5 or later
* PHP Extensions: `openssl`, `dom`, `libxml`, `mbstring`, `simplexml`, `bcmath`, `gd`, `soap` and `xmlwriter`
* Laravel 13.x or later
* Laravel Scheduler and Queue enabled

> [!NOTE]
>
> The Scheduler is used to queue DTE Envelopes, check CAF depletion, and check DTE receives at `dte@my-app.cl`
> The Queue is used to build XML, PDF, and send DTE Envelopes to SII API.

## Why does this library exist?

In 2026, you (still) cannot just connect your application to the SII Servers to push invoices through OAuth 2.0 and JSON. **SII works using pre-iPhone technologies**: XML, SOAP, folio-authorization, digital-signing, and so forth.

To avoid your application being vendor-locked with external services, this library **handles everything from the document constructions onwards**, leaving you with only two tasks:

1. Uploading a Folio to authorize your documents
2. Create the documents you need (the fun part)

The rest is handled automatically by the library. You still require some **manual labor** due to SII self-imposed limits when you move to [production](#certification--production), like uploading a Digital Certificate and CAF, but that's it. 

## Installation

Fire up Composer and require this package in your project.

```shell
composer require laragear/dte
```

## Set up

First, publish the configuration file, database migrations and XSD schemas. The latter two are required for storing legal documents in your application and validating them against the SII.

```shell
php artisan vendor:publish --provider="Laragear\Dte\DteServiceProvider"
```

After that, you may migrate your database like always through the Artisan command.

```shell
php artisan migrate
```

## What does this library do to comply (legally) with SII?

If you are new to Chilean tax accounting (SII), the gist is straightforward: documents (DTE) are sent to SII servers in bulk to SOAP/REST endpoints, in XML, and signed with a Digital Certificate (`.p12|pfx`) [you can buy separately](https://www.sii.cl/servicios_online/1039-certificado_digital-1182.html).

The implementation is not _straightforward_. The SII requires any app to comply with **The Seven Points of Compliance**:

1. Receive a CAF XML that authorizes a DTE number range (folio).
2. DTE created must include the CAF they belong to and signed.
3. Pack DTE into a signed "envelope" and check its status.
4. Receive and answer DTEs received at your `dte@my-app.cl` mailbox.
5. Create a legally correct PDF for your DTEs.
6. Send DTE XML to the target business `dte@business.cl`.
7. Store all documents for 6 years.

To deal with this, this library implements the following:

1. Loads and persists CAF XML into the database.
2. Builds DTE XML, allocating a Folio, and signs it with your Digital Certificate.
3. Fills and sends envelopes, polling their status at SII for updates.
4. Reads your `dte@my-app.cl` (IMAP/driver) and sends acknowledgements.
5. Renders PDF for any DTE using a standard design.
6. Sends each DTE approved to the target business using Laravel's Mail driver.
7. All XML documents are stored separately from the original payload on the database.

Odds are you already know some document types before implementing this library, but if you feel lost, check the [glossary section](#glossary) and come back. 

## 3-minute quickstart

You can start to use this library in less than three minutes in your application, or _the pizza is free_.

### 1. Set your company

The library needs your company data (RUT, legal name, business activity, SII resolution) to build any DTE. There is no default: building a document without a registered company fails loudly, before any folio is burned.

Use the `ConfigurationManager::setCompany()` helper to register the company data. This is also the place to resolve it dynamically (e.g., from the database), like when the end-user is onboarded into the application.

```php
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Rut\Rut;

public function boot()
{
    // Resolve the Company dynamically for a single tenant
    ConfigurationManager::setCompany(function () {
        $settings = Settings::first();

        if (! $settings) {
            return;
        }
        
        return CompanyData::make(
            issuer: IssuerData::make(
                rut: $settings->rut,
                name: $settings->name,
                activity: $settings->business_activity,
                activityCode: $settings->economic_activity_code,
                address: $settings->address,
                commune: $settings->commune,
                city: $settings->city,
                resolutionDate: $settings->resolution_date,
                resolutionNumber: $settings->resolution_number,
            ),
        );
    });

    // Resolve the digital certificate
    CertificateResolver::resolveUsing(function () {
        $cert = Certificate::first();

        if (! $cert) {
            return;
        }
        
        return new DigitalCertificate($cert->p12, $cert->password);
    });
}
```

> [!WARNING]
>
> Returning nothing from the company resolver is only safe while no document is built. The next `build()` call throws `RuntimeException` when the resolver returns no company, so make sure the data exists before issuing documents.

> [!NOTE]
> 
> If you plan to use this library on multi-tenant environments (multiple business), the resolver callback is where each tenant's data is resolved. Proceed to the [Multi-Tenancy Configuration](#multi-tenancy-configuration) section once you understand how the library works.

### 2. Create a fake certificate and CAF

For local development, generate a fake certificate and a default CAF for invoices. These will allow you to create DTE without having to buy a real certificate for signing documents, or hit the SII servers.

```shell
php artisan dte:make-fake-cert

php artisan dte:make-fake-caf --db
```

> [!NOTE]
>
> Without `--db` (or `--file`), `dte:make-fake-caf` only prints the CAF XML to stdout without persisting it, and no folios become available.

If you plan to create more than simple Invoices from the start, run the `dte:make-fake-caf` again with the DTE Type code.

```shell
php artisan dte:make-fake-caf --db --type=39
```

Both `dte:make-fake-*` commands are only available outside production environments. In [certification/production](#certification--production), you will be required to download the real CAF from SII and [upload it](#uploading-caf) instead.

> [!NOTE]
>
> If you set a company previously, it will use the company data for these fake files. Alternatively, use `--rut` with the RUT of the company on both commands so files match.

### 3. Schedule background commands

[Schedule these Artisan commands](https://laravel.com/docs/13.x/scheduling) in your `routes/console.php`. You can read the [Artisan Commands Reference](#artisan-commands-reference) for details about what does what, but consider these commands the bare minimum for continuous operation.

```php
use Illuminate\Support\Facades\Schedule;

// Checks for CAFs depletion. Not mandatory but highly encouraged.
Schedule::command('dte:check-cafs')->everyTwoHours();

// Packs signed DTE into envelopes so these can be sent later.
Schedule::command('dte:pack-ready')->everyTenMinutes();

// Poll the email for unanswered DTE.
Schedule::command('dte:fetch-mailbox')->hourly();

// Poll the SII for the envelope status (accepted or rejected).
Schedule::command('dte:poll-track-status')->everyFiveMinutes();

// Reject phantom invoices sent to your app before the deadline (recommended).
Schedule::command('dte:reject-phantom-invoices')->twiceDaily();
```

> [!IMPORTANT]
> 
> Run every scheduled `dte:*` command with `withoutOverlapping()` (and `onOneServer()` on multi-server setups) so overlapping ticks never process the same envelope, email, or document twice.
>
> ```php
> Schedule::command('dte:pack-ready')->everyTenMinutes()->withoutOverlapping();
> ```

### You're all set!

You can now create your own documents using any document builder facade, like the `SiiInvoice`.

```php
use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy('76.543.210-K', 'Helados S.A.')
    ->addItem('Crema de Leche', 12_000)
    ->build();
```

## SII Documents (DTE)

The library supports most used SII documents types via dedicated builders, all sharing a fluent API:

| Builder              | Type Code | Description                                            |
|----------------------|-----------|--------------------------------------------------------|
| `Invoice`            | 33 / 34   | Electronic invoice / exempt                            |
| `Receipt`            | 39 / 41   | Electronic receipt (boleta)                            |
| `CreditNote`         | 61        | Credit note, references a prior invoice to reverse it |
| `DebitNote`          | 56        | Debit note, adjusts amounts on a prior document       |
| `DispatchGuide`      | 52        | Dispatch guide (guía de despacho)                     |
| `InvoiceLiquidation` | 43        | Invoice liquidation                                   |
| `PurchaseInvoice`    | 46        | Purchase invoice (factura de compra)                  |
| `AecCession`         | None      | AEC (Acuse Electrónico de Cargo), factoring/cession   |

> [!IMPORTANT]
>
> The library is missing some document types, and this is by scope. Additional support will be based on sponsorship.

### Creating documents

> [!WARNING]
>
> The builder computes the document totals, validates the input against SII rules before any folio is burned, and persists everything atomically. Creating documents manually with Data objects is fully supported (see [Manual creation with Data objects](#manual-creation-with-data-objects)), but then *you* own the math and the consistency between the ledger columns and the XML blocks.

Every builder is reached through its corresponding facade (or the underlying Builder instance you can inject as a dependency). The builder methods are fluent, each call returns the builder, so you can freely chain the properties of the target document. Since each builder creates one document, it's difficult to make the wrong type of document.

You're required to set who receives the DTE using `receivedBy()` with the RUT and legal name of the person. For businesses (like in invoices), you will require the `ReceiverData` object.

```php
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Facades\SiiInvoice;

$receiver = ReceiverData::make(
    rut: '76.123.456-0',
    name: 'Ferretería Pérez Ltda.',
    activity: 'Compra venta de artículos de construcción',
    email: 'compras@feperez.cl',
    address: 'Avenida Principal 48',
    commune: 'Osorno',
);

$invoice = SiiInvoice::receivedBy($receiver);
```

```php
use App\Models\Business;
use Illuminate\Http\Request;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Data\ReceiverData;

public function createInvoice(Request $request, InvoiceBuilder $builder)
{
    $request->validate([
        // ...
    ]);
    
    $receiver = ReceiverData::fromArray($request->array('receiver'));
    
    $builder->receivedBy($receiver)
        ->addItem($request->input('item_name'), $request->integer('item_price'))
        ->build();
}
```

Chances are that you will have an Eloquent Model that you will want to use as a receiver. In that case, use `Receivable` contract on the model you want, and it will be used as a receiver magically.

```php
use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Contracts\Receivable;
use Laragear\Dte\Data\ReceiverData;

class Business extends Model implements Receivable
{
    // ...

    public function toReceiver(): ReceiverData
    {
        return ReceiverData::make(
            // ...
        );
    }
}

use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy(Business::find(66));
```

Once your document is ready, use the `->build()` method to persist the DTE to the database. It returns a `SiiDte` model you can use to inspect its status later using the primary key.

```php
use App\Models\Business;
use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy(Business::find(66))
    ->addItem('Queso Ranco', 7_490)
    ->buildSync();
```

Meanwhile, a queued job will be dispatched to compile the legal XML document. If you need the XML ready immediately, like to [print a PDF](#pdf-generation), use `buildSync()` instead of `build()`.

```php
use App\Models\Business;
use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy(Business::find(66))
    ->addItem('Queso Ranco', 7_490)
    ->buildSync();

return $invoice->pdf()->generate();
```

> [!IMPORTANT]
>
> When building the XML in sync, your app will take time to properly sign the XML, which also involves folio reservation. This shouldn't take more than a second. The DTE will still be queued to be sent later through an envelope.

#### Adding Items

You can add an item using a name and a total through the `addItem()` method.

```php
use Laragear\Dte\Facades\SiiReceipt;

$receipt = SiiReceipt::receivedBy('76.543.210-K', 'Helados S.A.')
    ->addItem('Crema de Leche', 12_000)
    ->build();
```

Use `isExempt: true` for lines that carry no tax (IVA). Exempt items still count toward the document total but are excluded from the default 19% tax calculation.

```php
use Laragear\Dte\Facades\SiiReceipt;

$receipt = SiiReceipt::receivedBy('76.543.210-K', 'Helados S.A.')
    ->addItem('Crema de Leche', 12_000, isExempt: true)
    ->build();
```

If you require more control on the itemization, like quantity, description, special codes, or percentage discounts, use the `Item` object directly.

```php
use Laragear\Dte\Data\Item;
use Laragear\Dte\Facades\SiiReceipt;

$item = Item::make(
    name: 'Crema de Leche', 
    unitPrice: 1_200,
    quantity: 10,
    discountPercentage: 0.15,
    taxes: [15 => 1_900] // Automatically subtracts Retention Code 15
);

$receipt = SiiReceipt::receivedBy('76.543.210-K', 'Helados S.A.')
    ->addItem($item)
    ->build();
```

The `taxes` parameter accepts a dictionary of SII tax and retention codes assigned to their absolute amounts. Retentions (like '*IVA Retenido*', codes 14-19, 30+) are automatically subtracted from the document total, while Ad-Valorem additions natively evaluate positively.

When [creating the document manually](#manual-creation-with-data-objects), the same `Item` objects go straight into the `detail_items` block:

```php
$dte->payload()->create([
    // ... other blocks ...
    'detail_items' => ['items' => [Item::make('Crema de Leche', 1_200, quantity: 10)]],
]);
```

#### Global Modifiers

You can define commercial discounts and surcharges using the `globalDiscount()` or `globalSurcharge()` methods on the document builder before creation. 

To dictate which accounting block the modifier mathematically targets, pass a `ModifierTarget` enum (`Default` for taxable amounts, `Exempt`, or `NonBillable`).

```php
use Laragear\Dte\Enums\ModifierTarget;
use Laragear\Dte\Facades\SiiReceipt;

$receipt = SiiReceipt::receivedBy('76.543.210-K', 'Helados S.A.')
    ->addItem('Crema de Leche', 12_000)
    // Applies a 10% global discount mathematically targeting the taxable amount
    ->globalDiscount(10, isPercent: true, target: ModifierTarget::Default, description: 'Descuento Global Primavera')
    ->build();
```

> [!WARNING]
>
> The `description` parameter of `globalDiscount()` and `globalSurcharge()` is mapped to the SII `<GlosaDR>` tag, which has a **45-character limit**. Longer descriptions are silently truncated.

When [creating the document manually](#manual-creation-with-data-objects), `GlobalModifierData` objects go into the `global_modifiers` block:

```php
use Laragear\Dte\Data\GlobalModifierData;
use Laragear\Dte\Enums\ModifierTarget;

$dte->payload()->create([
    // ... other blocks ...
    'global_modifiers' => ['items' => [
        GlobalModifierData::make('D', '%', 10, ModifierTarget::Default->value, 'Descuento Global Primavera'),
    ]],
]);
```

#### Manual creation with Data objects

Every builder call is a thin wrapper over Data objects: the builder holds them, calculates the totals, and serializes them into the **payload blocks** of a `SiiDte` model. You can skip the builder and feed the same Data objects directly into the model, which is useful when your application already owns the document data structures and doesn't need the builder to validate or calculate anything.

Each Data object maps 1:1 to a payload block of the `SiiDtePayload` relation:

| Data object          | Payload block      | XML block of the SII `Documento` |
|----------------------|--------------------|----------------------------------|
| `IssuerData`         | `header_issuer`    | `<Emisor>`                       |
| `ReceiverData`       | `header_receiver`  | `<Receptor>`                     |
| `Item`               | `detail_items`     | `<Detalle>`                      |
| `ReferenceData`      | `references`       | `<Referencia>`                   |
| `GlobalModifierData` | `global_modifiers` | `<DscRcgGlobal>`                 |
| `TransportData`      | `header_transport` | `<Transporte>`                   |

Since all Data objects implement `JsonSerializable`, you can pass them directly as block values, as the cast serializes them for storage. Here is the same invoice of the [builder example](#creating-documents), created manually:

```php
use Illuminate\Support\Facades\Artisan;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;

$issuer = app(ConfigurationManager::class)->getIssuer();

$receiver = ReceiverData::make(
    rut: '76.123.456-0',
    name: 'Ferretería Pérez Ltda.',
    activity: 'Compra venta de artículos de construcción',
    email: 'compras@feperez.cl',
    address: 'Avenida Principal 48',
    commune: 'Osorno',
);

// When adding items, the amount calculation is done automatically.
$items = [new Item('Queso Ranco', 7_490, quantity: 2)];

$dte = SiiDte::create([
    'document_type' => DteType::Invoice,
    'issuer_rut' => $issuer->rut,
    'receiver_rut' => $receiver->rut,
    'issued_on' => now(),
    'status' => DteStatus::Pending,
]);

// The Data objects go directly into their payload blocks.
$dte->payload()->create([
    'header_id_doc' => [
        'document_type' => DteType::Invoice->value,
        'issued_on' => now()->format('Y-m-d'),
    ],
    'header_issuer' => $issuer,
    'header_receiver' => $receiver,
    'detail_items' => ['items' => $items],
]);

// Totals are recalculated from the items into the ledger and the
// `header_totals` block, exactly like the builder does. With
// `push()` we save the current model and the payload.
$dte->recalculateTotals()->push();

// Compile the signed XML, exactly like ->build() does.
Artisan::queue('dte:compile', ['dte_id' => $dte->getKey()]);
```

The `document_type` must be set on both the model and the `header_id_doc` block, but totals are calculated automatically on the ledger and the  `header_totals` block. The compiler reads the blocks to build the legal XML, while the ledger columns feed queries, books, and the RCV. If they disagree, the document gets accepted by the SII with wrong data in your books.

> [!IMPORTANT]
>
> The builder validates the payload against XSD-derived rules *before* persisting and acquiring a folio. When creating documents manually, validation runs at compilation time instead, so an invalid document is persisted first and fails loudly when the `dte:compile` command runs. See [Failures](#failures) for what happens to the folio then.

Once compiled, manually created documents are indistinguishable from builder-created ones: they are picked up by `dte:pack-ready` into an envelope, or you can send one immediately with `$dte->send()` / `$dte->sendSync()`.

When the document carries references, the block only holds them for the XML. The `SiiDteReference` rows must also be created manually so the document can be queried and corrected later:

```php
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\ReferenceType;

$reference = ReferenceData::make(
    ReferenceType::PurchaseOrder,
    '100000513',
    '2026-04-01',
    'Requiere artículo usado para mostrar helados',
);

$dte->payload()->create([
    // ... other blocks ...
    'references' => ['items' => [$reference]],
]);

$dte->references()->create([
    'document_type' => ReferenceType::PurchaseOrder->value,
    'folio' => $reference->folio,
    'date' => '2026-04-01',
    'reason' => $reference->reason,
]);
```

### Adding References

References link DTE with other documents, like your own purchase orders or other DTE invoices. You need to get this clear from the get-go:

* **Purchase Orders (`801`) & Contracts (`803`):** The `$folio` parameter is the **internal application ID** or commercial document number from your own or your customer's system (e.g. `'PO-2026-99'`, `'CONT-001'`). **Multiple** references are supported.
* **Previous Invoices / DTEs (`33`, `34`, `52`, etc.):** The `$folio` parameter **IS the official SII DTE Folio number** of the previously issued tax document being referenced, amended, discounted, or nullified. Only a **single** reference is supported.

> [!WARNING]
>
> Reason has a **90-character limit**, so don't put entire biographies there.

> [!TIP]
>
> When [creating the document manually](#manual-creation-with-data-objects), the `ReferenceData` objects go into the `references` block *and* their `SiiDteReference` rows are created separately, as the manual section shows both.

#### Purchase Orders (`801`) & Contracts (`803`)

Link documents to customer Purchase Orders or commercial contracts:

```php
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Facades\SiiReceipt;

$reference = ReferenceData::make(
    documentType: ReferenceType::PurchaseOrder, 
    folio: '100000513',
    date: '2026-04-01', // Our purchase order date
    reason: 'Requiere artículo usado para mostrar helados' 
);

$receipt = SiiReceipt::receivedBy('18685226-5', 'Adrián Roberto Pérez González')
    ->addItem('Vitrina', 140_000)
    ->addReference($reference)
    ->build();
```

> [!WARNING]
>
> Folio IDs have an **18-character limit**. If you're using UUID, ULID, or something larger, create a numeric/alphanumeric "Tracking ID" for your documents to link these instead. 

#### Referencing & Correcting Previous Invoices (Credit Notes / Debit Notes)

The `SiiDte` model contains helpers to annul, amend text, or change amounts of the target DTE that has been received by SII. These resolve the Credit Note or Debit Note required, with minimal data to add.

```php
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Models\SiiDte;

$dte = SiiDte::find(1);

// Annul a document
$dte->annul();

// Amend a document text
$dte->amend(function (InvoiceBuilder $builder) {
    $builder->addItem('Giro: Venta de suministros de cocina')
});

// Surcharge document items
$dte->surcharge([
    Item::make('Harina AAAA Selecta 10KG', 9_990)
]);
```

Alternatively, you're free to create Credit Notes (`61`) or Debit Notes (`56`) to modify previously issued invoices, by using the explicit correction methods for **nullifying** (canceling), **amending** (correcting text), or **charging/discounting** (modifying amounts).

> [!TIP]
>
> As a rule of thumb:
>
> * If you made a critical typo on the document, amend with a **Credit Note**.
> * If the customer needs to pay less, use a **Credit Note**.
> * If the customer needs to pay more, use a **Debit Note**.
> * If a document needs to **disappear completely**, use a **Credit Note**, unless the document you are making disappear is _already_ a Credit Note.

For convenience, issue the `SiiDte` instance you want to alter directly and fill the remaining data.

```php
use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Facades\SiiCreditNote;
use Laragear\Dte\Models\SiiDte;

// Cancel a previous invoice (Reason Code 1: Anula Documento de Referencia)
$creditNote = SiiCreditNote::receivedBy('76.482.465-2', 'Cliente SpA')
    ->annul(SiiDte::invoices()->find(56), reason: 'Mercancía perdida en el camino')
    ->build();

// Cancel a previous invoice (Reason Code 2: Corrige textos)
$creditNote = SiiCreditNote::receivedBy('76.482.465-2', 'Cliente SpA')
    // Add the correction as an Item (per SII instructions).
    ->addItem(new Item('Corrección', 0, description: 'Debería ser "Cliente SpA".'))
    ->amend(SiiDte::invoices()->find(56), reason: 'Corrección de Razón social')
    ->build();
```

> [!IMPORTANT]
>
> Only a single Credit Note for annulment can be created per targeted Invoice. The library will execute this validation when creating this type of Credit Note.

#### Cession (Factoring)

AEC cessions are meant for transferring a document's receivable to a third party. The most common use is for invoices to be paid later (30/60/90 days): transfer the invoice to a factoring business, receive part of the money now, and the other business receives the full amount later.

While you can use the `SiiAecCession::forDte()` facade method to create one manually, the best course of action is to find the invoice you want to cede and use the `cede()` method to fluently build and send the cession document.

```php
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;

// The invoice to cede. Must be an invoice.
$invoice = SiiDte::find(45);

$cession = $invoice->cede()
    ->to(rut: '76.543.210-K', name: 'Factoring Bank S.A.')
    ->address('Av. El Golf 123, Las Condes', 'cesiones@factoringbank.cl')
    ->authorizedBy(rut: '12.345.678-9', name: 'Juan Perez', email: 'contact@mycompany.cl')
    ->amount($invoice->amount_total) // Optional, defaults to total, fails if it exceeds total.
    ->dueDate('2026-12-31') // When the cession is due
    ->build();
```

As with SII DTE, a cession document gets queued for building and sending to the SII in bulk through a DTE Envelope.

### Consulting documents and status

All documents persisted are just `SiiDte` model instances waiting to be sent to SII in XML. The latter is handled async by your application queue.

Since `SiiDte` is an Eloquent Model, you can query freely as with any other model. As a single model manages multiple types, you can filter the types using their respective local scope:

| Method                  | Document Type                                         |
|-------------------------|-------------------------------------------------------|
| `invoices()`            | Electronic invoice                                    | 
| `exemptInvoices()`      | Electronic exempt invoice                             | 
| `receipts()`            | Electronic receipt (boleta)                           | 
| `creditNotes()`         | Credit note, references a prior invoice to reverse it | 
| `debitNotes()`          | Debit note, adjusts amounts on a prior document       | 
| `dispatchGuides()`      | Dispatch guide (guía de despacho)                     | 
| `invoiceLiquidations()` | Invoice liquidation                                   | 
| `purchaseInvoices()`    | Purchase invoice (factura de compra)                  | 


The only recommended action to do over models is to check their status through the `whereStatus()|accepted()|pending()` local scope, or the `$status` attribute. Statuses flow through `DteStatus` enum (pending → sent → accepted/rejected).

```php
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;

// Using a Local Scope
SiiDte::invoices()->accepted()->first();

// Using the `status` attribute.
if (SiiDte::find(1)->status === DteStatus::Accepted) {
    return 'The document was accepted, all good with the SII.';
}
```

In any case, terminal statuses cannot be transitioned from. Attempting to do so will throw a nasty exception.

### Handling Rejections

When the library polls the SII for the processing status of your DTE Envelopes, transparently using either the legacy SOAP API for Invoices or the modern REST API for Boletas, it correctly separates structural errors from business logic errors:

* **Envelope-level Rejections (Structural)**: If the envelope fails structurally (e.g., bad signature or schema), the DTEs inside were not processed. The library will automatically detach them and schedule them to be repacked into a new envelope (up to `dte.envelopes.max_retries` configured in `config/dte.php`), saving valid folios from being burned.
* **DTE-level Rejections (Business)**: If the envelope was processed properly but specific DTEs contain bad data (e.g., mismatched receiver RUT), the SII permanently rejects them. The library updates the model to `Rejected` and fires the `DteRejected` event.

Since a DTE with a business error will keep failing if automatically retried, you must resolve the data yourself. You can catch the event, correct the data on your end, and try again in any of two ways: **replicating** the document, or  **resend** the original modified.

#### Replicate to a new document

The `$dte->replicateForRetry()` method clones the payload into a new model readied to acquire a fresh folio. Use it when the retried document should become a brand-new document, especially when the payload requires micro-adjustments (which are often very rare, but should be possible).

```php
use Laragear\Dte\Events\DteRejected;

public function handle(DteRejected $event)
{
    // Fix the underlying problem...
    // $event->dte->payload...
    
    // Safely replicate the document to be processed and sent with a new folio
    $newDte = $event->dte->replicateForRetry();
}
```

#### Resend the same document

The `$dte->retry()` hydrates a document builder with the stored payload so you can fix it, while `$dte->retryUsing()` applies a callback to the hydrated builder and persists the changes once the callback ends.

The document keeps its folio number and CAF, so it is resent under the same number: its status is reset to `Pending`, and any previous repairs, acceptance timestamps, or SII response are cleared.

```php
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;

$dte = SiiDte::whereStatus(DteStatus::Rejected)->first();

$dte->retryUsing(function (InvoiceBuilder $builder) {
    // Correct the underlying problem...
    $builder->addItem(Item::make('Corrección de datos', 0, description: 'Datos corregidos'));
});
```

### Acceptance with Repairs

Similar to Envelopes, individual DTEs can also be accepted but contain repairs (e.g. `DOK` with `<GLOSA>` discrepancies). The `SiiDte` model shares the same helpers and scopes as Envelopes:

```php
use Laragear\Dte\Models\SiiDte;

if ($dte->isAcceptedWithRepairs()) {
    $repairs = $dte->repairs;
    $rawResponse = $dte->payload->sii_response;
}

$dte->isNotAcceptedWithRepairs();

SiiDte::whereHasRepairs()->get();
SiiDte::whereDoesntHaveRepairs()->get();
```

The friendly parsed comments are available in the `$dte->repairs` array, and the raw XML/JSON response is safely stored in `$dte->payload->sii_response`.

### Receipts

While Facturas are grouped into a signed [DTE Envelope](#sii-envelopes) and sent to SII in bulk via the standard SOAP API, Boletas (receipts), however, are grouped into a specialized `<EnvioBOLETA>` envelope and transmitted to the modern [SII REST API](https://www4c.sii.cl/bolcoreinternetui/api/) (`api.sii.cl`). The library handles both types of endpoints transparently via the same `dte:pack-ready` command. 

Receipts do not require receiver data, these use an "anonymous consumer", but it is recommended when amounts are large in case of SII audits (e.g., CLP$ 200.000 or more).

```php
use Laragear\Dte\Facades\SiiReceipt;

// The library will automatically group and pack receipts into an <EnvioBOLETA> envelope
$receipt = SiiReceipt::addItem('Bebida energética', 3_490)
    ->build();
```

Receipts are created as models, but their XML payload is queued to be sent later to SII servers.

### Document lifecycle

Every document moves through three developer-facing steps:

```
draft()                  build() / buildSync()              send() / sendSync()
mutable + inert          immutable + compiled XML           immutable + exclusive envelope
Draft                    Pending → ... → Outbox             Packed → Sent → Accepted
   │                          │                                       │
   │                          │                                       │
   └─ edit freely,            ├─ build(): queue dte:compile           ├─ send(): build + queue dte:process-envelope
      never compiled          └─ buildSync(): compile now,            └─ sendSync(): build + process now
         │                       ready for pdf()                         │
         │                                                               │
         └─ hydrate() + build() to compile a draft                       └─ $dte->send() resends an already-built document
```

### Drafts

When `->build()` is called, the DTE is queued for compilation and sent later inside an envelope. At this point, the document is considered **immutable**.

If you want to persist a document but still leave it open for further modifications, use the `draft()` method. Once you're ready to compile it, use `build()`. You may also hydrate it, adjust it, and call `build()` again.

```php
use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy('76.543.210-K', 'Helados S.A.')
    ->addItem('Crema de Leche', 12_000)
    ->draft();

// After further modifications, compile it explicitly.
$invoice = SiiInvoice::hydrate($invoice)->build();
```

### Sending immediately

> [!WARNING]
>
> Sending DTE one-by-one is not recommended to avoid endpoint scrutiny by the SII API (rate limits or blacklisting). Use immediate sending sparingly.

When `->build()` is called, the DTE is not sent but later picked by a queued job, which later takes care assigning an envelope and then sending the document along many others.

If you want to compile and send the document as soon as possible, use the `send()` method, which builds its XML and pushes it into an exclusive envelope ready to be sent.

```php
use Laragear\Dte\Facades\SiiInvoice;

// The library will build the XML and pack the invoice into an exclusive envelope.
$envelope = SiiInvoice::receivedBy($receiver)
    ->addItem($item)
    ->send();
```

### Failures

When the local processing of a document fails (the XML compilation, the digital signature, or the envelope upload), the document is not stuck: it goes back to `Draft` so you can edit it and try again from scratch. The reason is stored in the `failure` column of the `sii_dtes` table, with the stage that failed and the error message.

```php
$dte = SiiDte::find(1);

// Did the document fail at some point?
$dte->hasFailure();

// What happened and where?
dump($dte->failure); // ['stage' => 'compile', 'exception' => '...', 'error' => '...']
```

The folio is only released when it _may_ have been consumed by the SII (the document was acknowledged, or a previous envelope already released it). In that case, the next compilation allocates a fresh folio automatically, otherwise the same folio is reused, since the SII never saw it.

Failing DTEs trigger the `Laragear\Dte\Events\DteFailed` event, which you can use to alert someone or feed a monitoring dashboard. A `DteStatus` of `Rejected` (the SII refused the document) is different: the folio was consumed, so the document can only be cloned with `replicateForRetry()` into a new draft with a new folio.

Documents stuck in `Building` or `Signing` because of a crashed worker are also recovered: add the `dte:process-envelope` command with the `--reclaim-stale` option to your scheduler, and any document older than 30 minutes mid-compilation is reset to `Draft` with the failure recorded.

## SII Certificates

Your application will require Digital Certificates to operate completely with the SII. On multi-tenant applications, you will be required to resolve these by the RUT of the _issuer_.

### Resolving Certificates

The `DigitalCertificate` class loads a PKCS#12 (`.p12|pfx`) file into memory and exposes its public key, private key, and validity dates.

The most direct approach to resolve certificates is just using `CertificateResolver::resolveUsing()` with a callback that receives the target RUT, and should return a `DigitalCertificate` instance or `null` if not found.

For example, you may save certificates in your database, local filesystem, or even cloud storage. You can conveniently instantiate certificates from strings or files using the `Certificate` facade, and register a custom resolver callback to locate them:

```php
use Illuminate\Support\Facades\Storage;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Rut\Rut;

CertificateResolver::resolveUsing(function (Rut $rut) {
    $company = Company::where('rut', $rut->formatRaw())->first();
    
    return $company ? new DigitalCertificate(
        Storage::disk($company->cert_disk)->get($company->cert_path),
        $company->cert_password,
    ) : null;
});
```

For more advanced scenarios, you can create your own `CertificateResolverInterface` that resolves by RUT, and instances certificates from a file location or a string. Once done, bind it to the Service Container.

```php
$this->app->bind(
    CertificateResolverInterface::class,
    MyCertificateResolver::class
);
```

## SII CAF

SII CAF documents authorize your DTE to be issued to SII with a controlled Folio numbering. SII forces you to download this document manually and load it into this library through your app.

> [!NOTE]
>
> You may see third party web applications automating this. Don't be fooled, there is no SII API to request CAF (in 2026!), these apps use headless browser automation to navigate to the SII and download it (and charge for the privilege).

### Uploading CAF

In production, you will be required to download the CAF XML from SII website, and load it into the library through the `Caf` facade.

The library automatically validates the CAF's folio range, expiration date, and issuer RUT before accepting it.

```php
use Laragear\Dte\Facades\Caf;
use Illuminate\Support\Facades\Storage;

// If you have the binary XML string:
$caf = Caf::store(Storage::get('76123456-7/33.xml'));

// If you are uploading a CAF file directly via an HTTP Request:
$caf = Caf::storeFile($request->file('caf'));

// Or using an absolute path:
$caf = Caf::storeFile(storage_path('app/cafs/76123456-7/33.xml'));
```

### Checking CAF status

The `dte:check-cafs` command scans all loaded CAFs and dispatches a `CafNearDepleted` event when remaining folios drop below the pre-configured threshold. Schedule this command daily so you can request new folio blocks before running out. Depending on your DTE output, you may want to tighten the interval to twice a day or even hours.

```php
// In routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('dte:check-cafs')->daily();
```

### Annulling and restoring Folios

If a folio was skipped, damaged, or cannot be used, you can annul it inside its CAF range so it is never handed out again. You can annul a single DTE document folio, or find the CAF and annul one or many folios, or ranges expressed as `[from, to]` pairs (a flipped `[to, from]` pair is reversed automatically).

```php
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Facades\Caf;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;

// Manually annul the folio of an existing DTE
SiiDte::find(1)->annulFolio('Folio anulado por pelotudeces');

// Manually annul one or many folios, or ranges, on the CAF itself
SiiCaf::find(1)->annulFolios([10, 12, [16, 18]], 'Folio dañado');

// Or let the manager find the CAF covering every folio
Caf::annulFolios('12.345.678-9', DteType::Invoice, 'Folio dañado', [10, 12, [16, 18]]);

// Check if a document folio was annulled
SiiDte::find(1)->isFolioAnnuled();
```

Annulment is **strict**: the whole batch is validated before anything is persisted, and the first offending folio throws a `Laragear\Dte\Caf\Exceptions\FolioAnnulmentException` (i.e. `FolioOutOfRangeException`, `FolioAlreadyAllocatedException`, `FolioAlreadyAnnuledException`, or `CafNotFoundException`).

> [!WARNING]
>
> The SII only accepts folio annulment through its portal, executed by an administrator, and only for documents that were never sent to the SII. This library only annuls the folios locally.

Annulled folios can be restored **locally** (the SII has no restore operation):

```php
// Restore the folio for reuse
SiiCaf::find(1)->restoreFolios([10, [16, 18]]);
```

Both operations run inside a locked transaction and dispatch `CafFoliosAnnuled` / `CafFoliosRestored` after commit.

## SII Envelopes

Envelopes are the core of the library's operations with the SII. The lifecycle of an envelope operates entirely in the background through a series of scheduled commands and queued jobs.

Envelopes are "open", and DTE are assigned to envelopes by schedule. Once a time limit or number of documents is reached, it is sent. Another scheduled job is in charge of efficiently polling an envelope until its status resolves, propagating the status to each DTE.

This architecture ensures that the application remains fast and responsive, and prevents hammering the SII servers by smoothly throttling requests through your queues.

### Acceptance with Repairs

When the SII processes an envelope, it may return a status of `EPR` (Envío Procesado), which marks the envelope as `Accepted`. However, the envelope might be partially accepted, meaning some internal DTEs had discrepancies or "repairs" (Reparos or Rechazos) while consuming the folio.

To check if an envelope was accepted with repairs, you can use the model helpers:

```php
if ($envelope->isAcceptedWithRepairs()) {
    $repairs = $envelope->repairs; // Returns a JSON array of the parsed SII statistics/repairs
    $rawResponse = $envelope->payload->sii_response; // Returns the raw XML or JSON from the SII
}

if ($envelope->isNotAcceptedWithRepairs()) {
    // 100% clean acceptance
}
```

You can also filter envelopes using the provided local scopes:

```php
use Laragear\Dte\Models\SiiDteEnvelope;

SiiDteEnvelope::whereHasRepairs()->get();
SiiDteEnvelope::whereDoesntHaveRepairs()->get();
```

## SII IECV

While technically abolished, on certification you will be required to upload a Sales/Purchase Book ("Libro de Compras" / "Libro de Ventas"). This can be done using the `SiiIecv` facade.

For purchases, you will be required to issue the existing DTE models that were completely accepted, along with their associated Credit Notes and Debit Notes.

```php
use Laragear\Dte\Builders\Iecv\IecvPurchaseData;
use Laragear\Dte\Facades\SiiIecv;
use Laragear\Dte\Models\SiiDte;

$entries = [
    IecvPurchaseData::make(/* ... */),
    IecvPurchaseData::make(/* ... */),
];

$book = SiiIecv::sendPurchases(
    issuer: $rut,
    entries: $entries,
    period: '2024-03',
    resolutionDate: '2024-01-01',
    resolutionNumber: 123,
    senderRut: $rut,
);

$book = SiiIecv::sendSales(
    issuer: $rut,
    dtes: SiiDte::findMany([1, 2, 3, 4, 5, 6, 7, 8]),
    period: '2024-03',
    resolutionDate: '2024-01-01',
    resolutionNumber: 123,
    senderRut: $rut,
);
```

The same flow applies to both operations. Books are persisted before upload, so a crash mid-send leaves a recoverable row. The period may be filed again when the previous book is still local (`Pending`, `Building`, `Signing`, `Sending`) or `Failed`. Once the SII ruled on it, as `Accepted` or `Rejected`, the period is closed.

Both entry points are also available as Artisan commands for periods that are assembled outside the request lifecycle:

```bash
php artisan dte:send-iecv --period=2024-03 --type=sales --issuer=76.543.210-K
php artisan dte:poll-iecv-status
```

## SII RCV (Registro de Compras y Ventas)

This library provides a robust **Cuadratura Engine** that parses official SII *Registro de Compras y Ventas* CSV exports to securely synchronize and reconcile your database automatically. 

You can execute this engine programmatically using the `Laragear\Dte\Actions\SyncRcv` action, which accepts a file path, string payload, or uploaded file:

```php
use Laragear\Dte\Actions\SyncRcv;
use Laragear\Dte\Enums\RcvType;

public function sync(Request $request, SyncRcv $syncRcv)
{
    $stats = $syncRcv->handle(
        source: $request->file('rcv_document'),
        type: RcvType::Purchases,
        issuer: '76.123.456-0',
        period: '2026-03', // the RCV export's monthly period; optional
    );
    
    return response()->json($stats);
}
```

> [!IMPORTANT]
>
> The SII portal exports the RCV per monthly period, and a document may take
> [until the 10th day of the following tax period](https://www.sii.cl/preguntas_frecuentes/catastro/001_012_6977.htm)
> to be incorporated into it. Always sync the export together with its period.

The _Cuadratura_ engine is **read-only over your local statuses**: absence from an export is never treated as proof of a rejection or forgery. It:

* marks purchases found in the RCV but missing locally as `PhantomPending` inbounds (only for claimable types 33, 34 and 43, since those are the documents the SII Reclamo Webservice operates on),
* promotes locally `Sent` sales documents whose totals match the RCV to `Accepted`, and
* reports unmatched documents inside the synced period through the `DteOrphaned` event, without writing any status.

When numeric discrepancies or folio collisions exist, it alerts you via [Events](#events) and counts them under the `discrepancies` metric, maintaining business integrity natively.

Depending on what happened, the following are the recommended courses of action:

### 1. If an Electronic Document (DTE) is missing in the RCV

If a sale exists in your system but not in the SII's RCV, it means the SII either never received the envelope or rejected it.

* **Solution:** You must check the Track ID (`track_id`) status of that envelope. If it was rejected by the SII, you must fix the errors and re-emit/re-send the DTE. If it was never sent, you must send it.

### 2. If a Document has incorrect amounts or data

If the sale appears in both your system and the RCV but the amounts, dates, or client data are wrong, you cannot just "edit" the RCV record.

* **Solution:** You must emit a Nota de Crédito (Credit Note, Code 61) to annul or discount the erroneous invoice, or a Nota de Débito (Debit Note, Code 56) to increase the value. Note that the RCV will automatically balance out the totals for that month.

### 3. If missing non-electronic sales (Boletas, Transbank, etc.)

If the discrepancy comes from non-electronic documents (like physical paper boletas or summary voucher sales that weren't emitted as electronic boletas), the SII allows you to Complement the RCV.

* **Solution:** Go to the SII portal and manually enter these non-electronic sales into the RCV, or use the portal's "Carga Masiva" feature to upload a CSV file containing those missing paper records. This must be done _before_ declaring the monthly Formulario 29.

### 4. If the month has already passed (Formulario 29)

If the month has already been closed, the taxes were paid, and you subsequently notice a discrepancy between the local ERP and what was declared based on the RCV (often resulting in an "LM" observation from the SII):

* **Solution:** Go to the SII portal and _Rectify_ the Formulario 29 (F29). The RCV of that past month remains as-is, but the F29 is updated to pay the correct tax difference (along with applicable fines/interest).

### 5. Advanced Purchase Books (IECV Proportional IVA)

When satisfying complex *Libro de Compras* setups with "Proportional IVA" requirements (like during certification steps for *IVA Uso Común*), you can flag individual purchases when building them, then supply the period factor when assembling the book. 

> [!TIP]
>
> The *IVA Uso Común* is a special tax applied to the purchase of goods and services that _cannot be determined if these will be directly to generate sales_, exempt or not. For example, paying electricity bills, office rent, cleaning and office supplies, etc.
> 
> It's calculated this way:
> 
>     IVA × (Taxable Sales ÷ Total Sales) = IVA Uso Común
>
> For example, imagine you make $500.000 in total sales, of these $400.000 is taxable. This means an 80% of total sales made IVA. If you pay the electricity bill for $23.800, only 80% of IVA becomes fiscal credit.
> 
> From $3.800 of IVA from the bill, $3.040 becomes Fiscal Credit, and $760 is cost. The latter goes into _Impuesto de Primera Categoría_ since it's assumed cost.

Flag the purchase when you build it, and supply the period factor when assembling the book. The builder needs both: the flag marks *which* purchases are subject to Uso Común, while the factor tells it *how much* of that IVA is actually creditable.

```php
use Laragear\Dte\Enums\IecvProperty;
use Laragear\Dte\Facades\SiiIecv;
use Laragear\Dte\Facades\SiiPurchaseInvoice;

$invoice = SiiPurchaseInvoice::issuedBy($electricityCompany)
    ->receivedBy($receiver)
    ->addItem($item)
    ->withCommonUseIva() // destined in part to exempt sales
    ->buildSync();

$book = SiiIecv::sendPurchases(
    issuer: $receiver->rut,
    entries: $entries,
    period: '2024-03',
    resolutionDate: '2024-01-01',
    resolutionNumber: 123,
    senderRut: $rut,
    properties: [
        IecvProperty::CommonIvaFactor->of(0.60), // Proportional scale Factor
    ],
);
```

The flag is persisted on the document, so it survives refetching and can be applied at any time before the period is filed. The builder then emits `<IVAUsoComun>` on the flagged document's `<Detalle>` entry (instead of `<MntIVA>`), plus `<TotOpIVAUsoComun>`, `<TotIVAUsoComun>`, `<FctProp>` and `<TotCredIVAUsoComun>` in the period totals.

> [!IMPORTANT]
>
> Proportional IVA is a **purchases-only** mechanism. `<FctProp>` and `<TotCredIVAUsoComun>` exist solely in the *Libro de Compras* structure of the SII schema, as the *Libro de Ventas* structure has no such nodes.

The call persists the book, uploads it, and returns the `SiiIecv` model in [`IecvStatus::Uploaded`](src/Enums/IecvStatus.php) once the SII assigned a Track ID. The verdict is then polled asynchronously by the `dte:poll-iecv-status` command, which moves the book to `Accepted` or `Rejected` and dispatches `IecvAccepted` / `IecvRejected`.

Because a period is a one-shot attestation, re-filing throws a `LogicException`: a DTE already sitting in a terminal book, or a period already in flight, will be rejected rather than silently uploaded again.

## DTE Interchange Mailbox (DIM)

SII forces business to use a specific email address to send/receive DTE, called the _DTE Interchange Mailbox_ (Correo Electrónico de Intercambio de DTE), DIM for short. 

Incoming SII responses (ACK, respuesta) and B2B DTEs from other companies arrive via email to the DIM, while outbound interchange envelopes and commercial receipts must be emailed back to them.

The library separates between **reading** (Mailbox Drivers) and **sending** (Laravel Mailers) these emails. You could, for example, use AWS S3 to process incoming emails but use Mailchimp to send outbound responses.

### Reading (Inbound)

The library ships four mailbox drivers to fetch and parse unread emails:

* `imap`: standard IMAP (default, slow)
* `microsoft`: Microsoft 365 / Exchange
* `google`: Google Workspace API
* `aws_ses`: AWS SES incoming mail via S3, requires `zbateson/mail-mime-parser` or similar by contract.

> [!IMPORTANT]
>
> This library does not support legacy `pop3` inboxes for reading.

Configure your inbound driver under the `dte.mailbox` configuration key. The `MailboxManager` automatically maps these emails and extracts the XML documents.

#### Custom driver

If you have a service not covered by the drivers, you may create your own by extending the `MailboxManager` like any other Laravel Manager and register it after your application boots. Use `afterResolving` if you want the class to be resolved only when the manager is required in your logic.

```php
use App\Sii\Mailbox\Tuta;
use Laragear\Dte\Mailbox\MailboxManager;

public function register(): void
{
    $this->app->afterResolving(MailboxManager::class, function (MailboxManager $mailbox) {
        $mailbox->extend('rut', function () {
            return new Tuta(config('services.tuta.key'));
        });
    });
}
```

#### AWS SES XML Parser

When using AWS SES as driver, you will be required to install `zbateson/mail-mime-parser` to parse the email contents.

```shell
composer require zbateson/mail-mime-parser
```

It's recommended, but not mandatory, to use that package. You can bring your own MIME parser or package for AWS SES emails as long as it implements the `MimeMessageParser` contract in your `AppServiceProvider` or `bootstrap/app.php`.

```php
use App\Mail\FastMimeParser;
use Laragear\Dte\Contracts\MimeMessageParser;

$this->app->bind(MimeMessageParser::class, function () {
    return new FastMimeParser();
});
```

### Sending (Outbound)

Outbound interchange emails use your standard Laravel Mail configuration (`config/mail.php`). These are used for both sending responses and also sending XML to target businesses.

It's recommended to use a different transactional email service instead of your application default (probably used for password resets or else), but not mandatory. Define a dedicated Laravel mailer connection exclusively for DTE within the `dte.dim.mailer` configuration key.

```php
    'dim' => [
        // The Laravel Mailer connection (from config/mail.php) used to SEND interchange emails.
        // Set to null to use your application's default mailer.
        'mailer' => env('DTE_DIM_MAILER'),
    ],
```

### Automatic acknowledge and processing

The `dte:fetch-mailbox` command reads new messages, auto-acknowledges them, and routes SII responses and vendor DTEs into the document status flow using the `ProcessInboundDte` action.

You should schedule this command to run frequently to keep your records updated:

```php
// In routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('dte:fetch-mailbox')->hourly();
```

The interval shouldn't matter for single businesses, as responses are expected to be received _during the day_ or may be never (at the eight day these are considered accepted). For multi-tenancy, you should make the interval shorter to avoid bottlenecking (receiving more documents than the app can process).

### Answering a document

For inbound documents from other businesses (purchase-side), the `ProcessInboundDte` action parses the received DTE and creates a `SiiInboundDocument` in your database. You can query these inbound documents to display them in your application and decide if you want to Accept or Reject them.

> [!TIP]
>
> Under Ley 19.983, after **8 consecutive days** (calendar days) of its reception by the SII, the document is legally considered **tacitly accepted** ("Aceptación Tácita") and acquires executive merit ("Mérito Ejecutivo"). In other words, you don't need to accept each inbound document, these are accepted at the eight day.

Use the `Claim` facade or the convenience methods directly on the `SiiInboundDocument` model to reject or accept it. Note that commercially accepting a document requires your digital certificate to sign the generated Receipt XML.

```php
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Facades\Claim;
use Laragear\Dte\Models\SiiInboundDocument;

$document = SiiInboundDocument::find(1);

// The certificate is resolved for the receiver RUT by your registered resolver.
$certificate = app(CertificateResolverInterface::class)->resolve($document->receiver_rut);

// You can use the Claim facade directly:
$receiptXml = Claim::accept($document, $document->receiver_rut, 'Santiago', $certificate);

// Or conveniently through the document model:
$receiptXml = $document->accept($document->receiver_rut, 'Santiago', $certificate);

// Alternatively, reject it due to missing goods (RFT)
// Rejections only contact the SII Webservice, and do not generate an XML receipt.
$document->rejectGoods('Faltan 2 cajas de leche');
```

By default, the library automatically emails the generated `RespuestaDTE` or commercial receipt XML ("Acuse de Recibo") back to the vendor when you accept the invoice. You can opt out of this automatic emailing behavior via the `dte.dim.auto_email_receipts` configuration option.

> [!TIP]
>
> Acknowledgement happens when the DTE is fetched from the Mailbox and properly validated, not before.

### Handling Fake & Forged Documents

Since the interchange system relies on email, malicious actors or systems with faulty integrations might send you documents that are fake (forged signatures) or sent to you but never actually uploaded to the SII (and thus invalid for tax credit).

To protect your business, follow these practices:

1. **Cryptographic Validation**: The library automatically validates the XML signature of all inbound DTEs. If the signature is broken or manipulated, the document status is automatically set to `Forged` (or you will receive an exception). You should safely ignore these documents.
2. **SII Consistency Check**: Some vendors email their DTE *before* the SII actually accepts it. If the SII subsequently rejects their envelope, you have an invoice they consider "sent" but the SII considers non-existent. **Never pay invoices immediately**. Wait until the document appears in your SII *Registro de Compras* (RCV) — which may take until the 10th day of the following tax period — before issuing commercial acceptance or payment.
3. **Guardrails Enforced**: Once you accept or reject a document, you cannot do it again. The library enforces this to prevent duplicate claims and keep your system completely in sync with the SII.

## PDF Generation

> [!IMPORTANT]
> You must install the Dompdf package into your application to use the default configuration:
>
> ```bash
> composer require dompdf/dompdf
> ```
>
> If you need special DTE designs with advanced CSS (Flexbox/Grid) or JavaScript, use [another robust driver](https://spatie.be/docs/laravel-pdf/v2/drivers/), like Browsershot, Gotenberg, or Cloudflare Browser Run, among others.

Most of the time you will require offering a DTE as a PDF, and to move into certification, you will be required to do so. This library allows you to easily create a PDF, or [automate this behavior if you require](#automatic-generation).

To render the PDFs, this library uses [`spatie/laravel-pdf`](https://spatie.be/docs/laravel-pdf/v2/introduction) under the hood. By default, it is configured to use the `dompdf` driver for simple, table-based layouts without requiring Node.js or headless browsers installed separately, in exchange of using the application memory to build it.

To build a PDF, use the `pdf()` method of the `SiiDte` instance. From there, the `generate()` method will create the PDF if it does not exist yet. You will receive a `Laragear\Dte\Data\PdfData` instance with the information of the PDF location (disk and path). By default, it will use your default filesystem disk and save these under the `dte/pdf/` prefix.

```php
use Illuminate\Support\Facades\Storage;
use Laragear\Dte\Models\SiiDte;

$pdf = SiiDte::find(1)->pdf()->generate();

$disk = $pdf->disk;
$path = $pdf->path;

return Storage::disk($disk)->download($path);
```

With the disk and path, you can retrieve the PDF data using the `Storage` facade of Laravel, for example, [to attach to an email](https://laravel.com/docs/13.x/mail#attachments).

```php
use App\Mail\InvoiceCreated;
use Illuminate\Support\Facades\Mail;
use Laragear\Dte\Models\SiiDte;

$document = SiiDte::find(1);

Mail::to('ventas@empresa.cl')
    ->send(new InvoiceCreated($document, $document->pdf()->generate()));
```

### Automatic compilation

PDF methods render from the compiled and signed XML of the document. If the document is still pending compilation, the library compiles it automatically and synchronously before rendering, which acquires a folio from the available CAFs and digitally signs the XML. This also applies when returning the PDF builder from a controller, downloading it, or getting its URL.

Documents that cannot be compiled will throw an exception before any PDF is created. For example, a DTE without available CAF folios, or one that is being processed by another process.

> [!NOTE]
>
> **Draft documents are never compiled implicitly.** To generate a PDF of a draft, build it first with `build()`. This keeps drafts editable until you deliberately turn them into legal documents.

### Naming

PDFs are generated using the `{issuer-rut}_{type}_{folio}_{dte_created_at}.pdf` pattern, making it easy to search and sort. For example, `76543210-K_33_1045_2026-05-01_193254.pdf`.

The generation uses the default disk set in the configuration file of the library. You may also control where the PDF is saved programmatically by issuing the disk and the path as the `disk` and `as` methods.

```php
use Laragear\Dte\Models\SiiDte;

SiiDte::find(1)->pdf()->disk('public')->as('my_invoice_33.pdf')->generate();
```     

### Replace the file

When you call `generate()`, the PDF won't be overwritten if it already exists. You can overwrite the file using the `force()` method. It also accepts a condition if you want.

```php
use Laragear\Dte\Models\SiiDte;

SiiDte::find(1)->pdf()->force(fn () => true)->generate();
```

### PDF HTML View

If you want the HTML view used to generate the PDF, without generating the PDF file itself, use the `view()` method. This is great when you want only to show the PDF as HTML in a controller response, so the user prints it using their browser or through `window.print();`.

```php
use Laragear\Dte\Models\SiiDte;

public function view(SiiDte $document)
{
    return $document->pdf()->view(); 
}
```

The view also accepts a custom view and data to merge.

```php
use Laragear\Dte\Models\SiiDte;

public function view(SiiDte $document)
{
    return $document->pdf()->view('custom-view', ['with-background' => true]); 
}
```

> [!NOTE]
>
> **Thermal Printers and Tickets:** Generating a PDF using a specific ticket width (like 57mm or 80mm) with variable height is not supported by the PDF format. For robust Point of Sale (POS) environments, do not rely on printing PDFs. Instead, print the data natively using raw thermal commands through libraries like [mike42/escpos-php](https://github.com/mike42/escpos-php) which communicate directly with the ESC/POS printer protocol and offer **PDF417 2D barcode compliance**.

### Returning a download

The PDF builder instance is `Responsable`. If you return it in a controller, it will automatically initialize a download instead of showing an HTML view.

```php
use Laragear\Dte\Models\SiiDte;

public function view(SiiDte $document)
{
    return $document->pdf();
}
```

Alternatively, you can use the `download()` method to alter the download, like suggesting a filename or changing the headers.

```php
return $document->pdf()->download(name: 'my_invoice.pdf', headers: ['X-SII' => 'INVOICE']);
```

You may also use the `url()` to send the URL of the resource to download, or [`temporaryUrl()`](https://laravel.com/docs/13.x/filesystem#temporary-urls) to return an URL that only lasts for a minute (by default) on supported filesystems. This is great if you want to pass the URL to an email instead of attaching the PDF, send it through instant messaging, or just push the download to the web server instead.

```php
use Laragear\Dte\Models\SiiDte;

$document = SiiDte::find(1);

return $document->pdf()->url();

// Or return a link that only lasts for a while, like 5 minutes.
return $document->pdf()->temporaryUrl(now()->addMinutes(5));
```

### Rendering control

If you want total control on how the PDF is rendered, use the `customize` method with a callback to alter the underlying `Spatie\LaravelPdf\PdfBuilder` instance.

```php
use Spatie\LaravelPdf\PdfBuilder;
use Laragear\Dte\Models\SiiDte;

$document = SiiDte::find(1);

$document->pdf()->customize(function (PdfBuilder $pdf) {
    $pdf->paperSize(210, 297, 'mm')
        ->margins(10, 10, 10, 10);
})->generate();
```

### Direct Binary Access

> [!WARNING]
>
> PDF Generation uses storage directly because the DTE raw payload and XML already take memory, and holding the entire PDF file in memory adds more on top. Be careful, large documents (or handling multiple) may trigger Out Of Memory (OOM) fatal errors.

If you need the raw PDF content (for example, to stream it to another service without writing to the disk), use the `binary()` method.

```php
use Laragear\Dte\Models\SiiDte;

$content = SiiDte::find(1)->pdf()->binary();
```

### Deleting a PDF

If you want to delete a PDF because you want to reclaim space or just only keep a fresh set of PDF, use the `delete()` method of the PDF without generating it. If the PDF never existed, it won't raise any exceptions.

```php
use Laragear\Dte\Models\SiiDte;

return SiiDte::find(1)->pdf()->delete();
```

If the PDF was generated using another disk or path, use `disk()` and `as()` respectively.

```php
return $document->pdf()->disk('s3')->as('your-invoice.pdf')->delete();
```

### Automatic generation

You can [listen to events](#events) like `DteCompiled` or `EnvelopeAccepted` to automatically generate the PDF of the documents.

```php
use App\Models\Business;
use App\Jobs\SendDtePdfToBusiness;
use Illuminate\Support\Facades\Event;
use Laragear\Dte\Events\DteCompiled;
use Laragear\Dte\Models\SiiDte;

Event::listen(DteCompiled::class, function (DteCompiled $event) {
    $business = Business::findByRut($event->dte->receiver_rut);
    
    $pdfLocation = $event->dte->pdf()->generate(); 
    
    $business->notify(new InvoiceReady($pdfLocation));
});
```

## Validation

This library brings some validation rules to use when uploading digital certificates and CAF, which are required to work with SII endpoints and require constant renewal.

### Data validation

When using the data objects at `src/Data`, you may use the `validate()` method to check if the data will be ingested without errors by SII servers.

```php
use Laragear\Dte\Data\ReceiverData;

$receiver = ReceiverData::make(
    // ...
);

$receiver->validate();
```

Alternatively, you can get the rules for the data and directly use them on your input, as long as the keys are equal (otherwise, you will need to extract the rules of the key you need).

```php
use Illuminate\Http\Request;
use Laragear\Dte\Validation\DteRules;

public function addReceiver(Request $request)
{
    $request->validate(DteRules::RECEIVER);
    
    // ...
}
```

### Certificate Validation

Use the `sii_certificate` rule to validate the uploaded certificate. It will check the mime type (`com.rsa.pkcs-12`), use the `password` input to decrypt it, and check the expiration date.

```php
public function upload(Request $request)
{
    $request->validate([
        'certificate' => 'required|sii_certificate',
        'password' => 'required|string',
    ]);

    // ...
}
```

> [!IMPORTANT]
>
> If the password of the certificate is empty, the validation will fail.

When the password is somewhere else in the input, you can use an argument to tell the validation rule where to find it.

```php
$request->validate([
    'cert' => 'required|sii_certificate:cert_pass',
    'cert_pass' => 'required|string',
]);
```

### CAF Validation

Use the `sii_caf` rule to validate the CAF. It will check in the database if the CAF already exists for the target business, document, and folio. If it already exists, it will be invalid.

```php
public function upload(Request $request)
{
    $request->validate([
        'caf' => 'required|sii_caf',
    ]);
}
```

To avoid forging the CAF issuer RUT, you can use a parameter to check if the CAF is assigned to the given RUT. If the CAF Issuer RUT is different, the CAF will be invalid.

```php
$request->validate([
    'caf' => 'required|sii_caf:76.123.456-0',
]);
```

## Multi-Tenancy Configuration

By default, this package works in a single-business mode using static configuration. To support multi-tenancy seamlessly, you should use the [Dynamic Configuration](#dynamic-configuration-saas--gui-setups) pattern. This ensures all automated background tasks (like packing envelopes, sending DTEs, generating PDFs, and interacting with the DIM) automatically resolve the correct business details and certificates for the document being processed.

### 1. Manual Multi-tenant DTE overriding

If you are not using the dynamic configuration closures or need to override the globally resolved issuer for a specific document, you can use the `issuedBy()` method from any [document builder](#sii-documents-dte) to explicitly set _which_ business is issuing the document. 

```php
use Laragear\Dte\Facades\SiiReceipt;
use Laragear\Dte\Data\IssuerData;

$business = IssuerData::make(
    '76.123.456-0',
    'Panadería LTDA',
    'Amasandería',
    'Venta de productos comestibles',
    // ...
);

$invoice = SiiReceipt::issuedBy($business)
    ->receivedBy('18685226-5', 'Adrián Roberto Pérez González')
    ->addItem('Vitrina', 140_000)
    ->build();
```

### 2. Multi-tenant DIM fetching

To support incoming multi-tenant documents, implement and bind the `Laragear\Dte\Contracts\TenantResolver` contract. The library uses this interface to securely route and map incoming XML envelopes to the correct tenant model in your application by its receiver RUT, while discarding those without a tenant.

```php
use App\Models\Business;
use Laragear\Dte\Contracts\TenantResolver;
use Laragear\Rut\Rut;

class DatabaseResolver implements TenantResolver
{
    public function resolve(Rut $rut): ?object
    {
        return Business::whereRut($rut)->first();
    }
}

$this->app->bind(TenantResolver::class, function () {
    return new DatabaseResolver;
});
```

> [!WARNING]
>
> This library does not support fetching **mails from multiple sources**, like for each tenant. For multi-tenant applications, require your tenants to redirect/register their DIM to a universal app mailbox, e.g. `dte@my-app.com`.
>
> This is a limitation on email technologies due to SII shortsightedness: polling multiple mailboxes via IMAP does not scale, and using WebHooks is sketchy in terms of security (who sends it) and requires multiple parsing drivers (each email service sends data with different schemas).

## Artisan Commands Reference

This library brings a lot of commands, but these are required since working with SII requires a lot of automation.

| Command                                | Description                                                                                                        |
|----------------------------------------|--------------------------------------------------------------------------------------------------------------------|
| `dte:make-fake-cert`                   | Generate a self-signed dummy PKCS#12 (.p12) digital certificate for local/testing environments.                    |
| `dte:make-fake-caf`                    | Generate a dummy SII CAF XML file containing valid structural tags and a newly minted RSA keypair.                 |
| `dte:check-cafs`                       | Check for active CAFs nearing folio depletion and dispatch events                                                  |
| `dte:poll-track-status`                | Queries SII for TrackID status, updating envelope to Accepted or Rejected                                          |
| `dte:fetch-mailbox`                    | Poll configured DTE mailbox for UNREAD messages and process them                                                   |
| `dte:reject-phantom-invoices`          | Reject PhantomPending invoices nearing the automatic acceptance deadline                                           |
| `dte:compile {dte_id}`                 | Compile a DTE XML from its model                                                                                   |
| `dte:pack-ready`                       | Packs signed DTEs into envelopes and dispatches processing.                                                        |
| `dte:pack-manual {ids}`                | Packs the given DTEs into envelopes, bypassing batch thresholds.                                                   |
| `dte:process-envelope {envelope_id}`   | Process an envelope, signs it, and send it to the SII                                                              |
| `dte:purge`                            | Truncate all DTE-related database records (documents, envelopes, interchanges, CAFs)                               |

## Events

You can listen to these events in your `EventServiceProvider` to trigger notifications or custom logic:

| Event                      | Dispatched when...                                                                                               |
|----------------------------|------------------------------------------------------------------------------------------------------------------|
| `AecCessionCreating`       | Before an AEC Cession is built and signed                                                                        |
| `AecCessionCreated`        | After an AEC Cession is generated                                                                                |
| `CafDepleted`              | When a CAF has no available folios                                                                               |
| `CafExpiring`              | When a CAF has 7 or fewer days before expiration                                                                 |
| `CafLoaded`                | When a CAF is successfully loaded into the database                                                              |
| `CafNearDepleted`          | When `check-cafs` finds folios below threshold                                                                   |
| `CafFoliosAnnuled`         | When CAF folios are annuled                                                                                      |
| `CafFoliosRestored`        | When CAF folios previously annuled were restored                                                                 |
| `DteAccepted`              | When a DTE is successfully processed and accepted by the SII                                                     |
| `DteAltered`               | When a synced Cuadratura record has distinct totals (`amountTotal`), or a never-sent document appears in the RCV |
| `DteBuilt`                 | After the DTE XML compilation pipeline ends                                                                      |
| `DteBuilding`              | Before the DTE XML compilation pipeline starts                                                                   |
| `DteCompiled`              | Dispatched containing the generated raw XML right before transmission (Ideal for Storage Backups)                |
| `DteCompiling`             | Before a DTE XML is compiled                                                                                     |
| `DteCreated`               | After a document is persisted (success or failure)                                                               |
| `DteCreating`              | Before a document is built and signed                                                                            |
| `DteFailed`                | When local processing fails and the document returns to `Draft`                                                  |
| `DteOrphaned`              | When a locally tracked document is absent from its synced RCV period export (read-only detection)                |
| `DteRejected`              | When a DTE inside a rejected envelope fails to be accepted                                                       |
| `DteUnregistered`          | When the Cuadratura matches a sale found in the SII RCV but completely missing in the DB                         |
| `EnvelopeAccepted`         | After the SII processes and accepts an Envelope                                                                  |
| `EnvelopeRejected`         | After the SII processes and rejects an Envelope                                                                  |
| `EnvelopeSending`          | Before sending an envelope to the SII                                                                            |
| `EnvelopeSent`             | After successfully sending an envelope to the SII                                                                |
| `IecvAccepted`             | When the SII accepts an uploaded IECV book                                                                       |
| `IecvRejected`             | When the SII rejects an uploaded IECV book                                                                       |
| `IecvSent`                 | After an IECV book is uploaded and the SII assigns a Track ID                                                    |
| `InboundDteAcknowledged`   | When an inbound DTE is commercially acknowledged                                                                 |
| `InboundDteAnswered`       | When an inbound DTE is accepted or rejected                                                                      |
| `InboundDteReceived`       | When an inbound DTE envelope is received                                                                         |
| `InboundForgedDteReceived` | When an inbound DTE is found to be tampered or forged                                                            |

> [!NOTE]
> **Accepted Events and Repairs**: The `EnvelopeAccepted` and `DteAccepted` events are dispatched unconditionally when the SII confirms the document was processed (e.g., `EPR` or `DOK`), **even if there are repairs or rejections inside it**. If you need to log or react to discrepancies, check for repairs directly inside your event listener:

```php
use Laragear\Dte\Events\EnvelopeAccepted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(function (EnvelopeAccepted $event) {
    if ($event->envelope->isAcceptedWithRepairs()) {
        Log::warning('Envelope accepted with repairs!', [
            'repairs' => $event->envelope->repairs,
            'raw_xml' => $event->envelope->payload->sii_response,
        ]);
    }
});
```

```php
use App\Notifications\CafDepletedWarning;
use Laragear\Dte\Events\CafNearDepleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

Event::listen(function (CafNearDepleted $event) {
    Mail::to('admin@company.com')->send(new CafDepletedWarning($event->caf));
});
```

## Authorization

You may find yourself trying to apply authorization checks over the models. You should use the `Gate` facade in your `AuthServiceProvider` or application service provider and manually register your policy to the model.

```php
use App\Policies\SiiDtePolicy;
use Illuminate\Support\Facades\Gate;
use Laragear\Dte\Models\SiiDte;

public function boot()
{
    Gate::policy(SiiDte::class, SiiDtePolicy::class);
}
```

## Testing

During testing, the library detects your environment as `testing` automatically. Alternatively, you can force the environment in your `phpunit.xml`:

```xml
<phpunit>
  <php>
    <env name="APP_ENV" value="testing"/>
    <env name="DTE_ENV" value="testing"/>
  </php>
</phpunit>
```

In `testing` and `local` environments, the library incorporates safety mechanisms to guarantee tests run instantly and never hit the real SII servers:

* **Envelope Uploads** instantly return a generated fake string (e.g., `fake-track-id-123`).
* **SOAP Gateways** intentionally throw a `RuntimeException` to prevent hanging scripts and external requests.

### Quick Setup with `InteractsWithSiiDte`

The fastest way to start testing is using the `InteractsWithSiiDte` trait. It automatically configures a fake issuer, CAFs, certificates, and fakes all builder facades:

```php
use Illuminate\Foundation\Testing\TestCase;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Testing\InteractsWithSiiDte;

class InvoiceServiceTest extends TestCase
{
    use InteractsWithSiiDte;

    public function test_creates_invoice(): void
    {
        $dte = $this->newInvoice(
            receiver: '12.345.678-9',
            items: [['Consulting', 50000, 2]],
        )->build();

        $this->assertDteCreated(DteType::Invoice);
        $this->assertSame(100000, $dte->amount_net);
    }
}
```

Orchestra Testbench auto-invokes `setUpInteractsWithSiiDte()` and `tearDownInteractsWithSiiDte()`. Plain PHPUnit users must call them manually in `setUp()` / `tearDown()`.

### Faking Document Creation

Use `SiiInvoice::fake()` (and other `Sii*::fake()`) to intercept document creation. The fake builders return in-memory `SiiDte` models without hitting the database or compilation pipeline.

#### Scenario 1: Asserting a DTE was created

```php
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Models\SiiDte;

public function test_document_is_queued_for_upload()
{
    SiiInvoice::fake();

    $this->post('checkout/return')->assertOk();

    SiiInvoice::assertCreated(1);

    $dte = SiiInvoice::lastCreated();
    $this->assertSame(50000, $dte->amount_net);
}
```

#### Scenario 2: Asserting Application Behavior upon SII Rejection

If you want to test how your application reacts when the SII rejects a document, fake the event system directly.

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laragear\Dte\Events\DteRejected;
use Laragear\Dte\Models\SiiDte;
use App\Notifications\DocumentRejectedNotification;

public function test_user_is_notified_when_document_is_rejected()
{
    Notification::fake();
    
    Event::fake([DteRejected::class]);

    $dte = SiiDte::factory()->invoice()->create();

    DteRejected::dispatch($dte);

    Notification::assertSentTo($user, DocumentRejectedNotification::class);
}
```

#### Scenario 3: Deep Mocking the SOAP Gateway

If you need to test the exact internal polling logic, you can mock the Gateway classes directly in the service container.

```php
use Laragear\Dte\Gateways\SoapGateway;
use Laragear\Dte\Jobs\PollEnvelopeTrackIdJob;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Enums\EnvelopeStatus;

public function test_polling_updates_envelope_status()
{
    $this->mock(SoapGateway::class, function ($mock) {
        $mock->shouldReceive('query')
             ->withArgs(fn($token, $service, $action, $args) => $args['TrackId'] === '12345')
             ->andReturn('<ESTADO>EPR</ESTADO>');
    });

    $envelope = SiiDteEnvelope::factory()->create(['track_id' => '12345']);

    // The job resolves its dependencies (including the mocked gateway)
    // from the container when it runs through the default queue.
    dispatch(new PollEnvelopeTrackIdJob($envelope));

    $this->assertSame(EnvelopeStatus::Accepted, $envelope->fresh()->status);
}
```

### Available Fakes

For testing, you can fake builders.

| Facade                          | Fake Method                             |
|---------------------------------|-----------------------------------------|
| `SiiInvoice::fake()`            | Returns `FakeInvoiceBuilder`            |
| `SiiReceipt::fake()`            | Returns `FakeReceiptBuilder`            |
| `SiiCreditNote::fake()`         | Returns `FakeCreditNoteBuilder`         |
| `SiiDebitNote::fake()`          | Returns `FakeDebitNoteBuilder`          |
| `SiiDispatchGuide::fake()`      | Returns `FakeDispatchGuideBuilder`      |
| `SiiPurchaseInvoice::fake()`    | Returns `FakePurchaseInvoiceBuilder`    |
| `SiiInvoiceLiquidation::fake()` | Returns `FakeInvoiceLiquidationBuilder` |

Each facade also provides `assertCreated()`, `assertNotCreated()`, `created()`, and `lastCreated()` methods.

### Cross-Facade Assertions with `DteFake`

Use `DteFake` to aggregate creation records across all faked facades:

```php
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Facades\SiiCreditNote;
use Laragear\Dte\Testing\DteFake;

$fake = new DteFake;

SiiInvoice::fake()->issuedBy(...)->addItem('A', 1000)->build();
SiiCreditNote::fake()->issuedBy(...)->addItem('B', 500)->build();

$fake->assertCreated(times: 2);
$fake->assertCreatedFor(DteType::Invoice, times: 1);
$fake->lastCreated(); // Returns the credit note
```

## [Certification & Production](CERTIFICATION.md)

## Configuration

This package can be configured either statically (via a configuration file) or dynamically (via database or runtime settings).

### Static Configuration

For simple setups, you can publish the static configuration using Artisan:

```shell
php artisan vendor:publish --provider="Laragear\Dte\DteServiceProvider" --tag="config"
```

You will receive the [`config/dte.php`](config/dte.php) config file. Due to the length of the file, everything is explained there instead of re-explained here.

### Dynamic Configuration (SaaS / GUI setups)

In most real-world scenarios, applications expect the end-user to fill in their company details (RUT, resolution number, certificate, etc.) from a GUI. Relying on static configuration files makes it hard to update or initialize this data dynamically.

You can configure the library dynamically using closures in your `AppServiceProvider` via the static helpers in `ConfigurationManager` and `CertificateResolver`. The library will evaluate these resolvers at runtime before falling back to the `config/dte.php` defaults.

#### Multi-Company (Multi-Tenant) Dynamic Setup

If your application handles **multiple businesses** (Multi-Tenancy), you should use the granular resolvers. This guarantees that automated background tasks (like queuing DTE envelopes) can pass the contextual RUT to resolve the exact tenant dynamically.

```php
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Rut\Rut;

public function boot()
{
    // Resolve the Issuer (business data) dynamically
    ConfigurationManager::resolveIssuerUsing(function (?Rut $rut) {
        $settings = $rut ? Settings::where('rut', $rut->formatRaw())->first() : Settings::first();
        
        return $settings ? IssuerData::make(
            rut: $settings->rut,
            name: $settings->name,
            activity: $settings->business_activity,
            activityCode: $settings->economic_activity,
            address: $settings->address,
            commune: $settings->commune,
            city: $settings->city,
            resolutionDate: $settings->resolution_date,
            resolutionNumber: $settings->resolution_number,
        ) : null;
    });

    // Resolve the Sender RUT (the authorized signer)
    ConfigurationManager::resolveSenderUsing(function (Rut $issuerRut) {
        return Settings::where('rut', $issuerRut->formatRaw())->value('sender_rut');
    });

    // Resolve the digital certificate
    CertificateResolver::resolveUsing(function (Rut $signerRut) {
        $cert = Certificate::where('rut', $signerRut->formatRaw())->first();
        
        return $cert ? new DigitalCertificate($cert->p12, $cert->password) : null;
    });
}
```

## Laravel Octane compatibility

* There are no singletons using a stale application instance.
* There are no singletons using a stale config instance.
* There are no singletons using a stale request instance.
* All external I/O goes through proxy classes (`OpenSslProxy`, `SoapProxy`, `ImapProxy`, etc.) that are resolved fresh per request, so no stale handles or leaked connections survive across requests.

This library is **100% compatible with Laravel Octane** (Swoole, Roadrunner & FrankenPHP).

## Laravel Boost compatibility

This package includes Laravel Boost AI Guidelines for your agents. Also included are the following AI Skills:

* Set up
* Document building
* PDF generation
* Testing
* Certification

After installing this package, ensure your Laravel Boost files are updated using the `boost:update` command:

```shell
php artisan boost:update
```

## Security

The following security consideration has been made while creating this Laravel package.

* DTE uses a numbered folio that starts from zero. Using UUID to obfuscate the number is a moot point, **business activity can always be inferred legally**.
* Every XML document is validated against SII's XSD schemas before signing, **preventing malformed output from reaching SII**.
* Status transitions are guarded: once a document reaches a terminal status (accepted/rejected), **it cannot be modified**.
* RCV Sync **sends events** when discrepancies are found.

If you discover any security-related issues, Report a Vulnerability in the repository instead of using the issue tracker.

### Remove Certificates from version control

Digital certificates (`.p12|pfx`) and CAF private keys must never be committed to version control. Add them to `.gitignore` individually, or ignore the entire target folder. Location will depend on your configuration.

```gitignore
/storage/app/private/dte/*
```

## Development

Clone this repository, make your changes, and send a PR. Few rules, though:

* Don't make huge rewrites. If I can't understand it on a Friday afternoon, I'll close it.
* Don't push AI slop. Follow styles and conventions as I would write it, not you or your agent.
* Don't extend it for only you. Features should benefit everyone, not only your use case.

This library is made available for free, don't act like anyone owes you anything.

## F.A.Q.

* **I messed up the price on an invoice I just created. Can I just find the `SiiDte` model and update the database or delete the row?**

Absolutely **not**. You must emit a _Nota de Crédito_ (Credit Note, Code 61) to annul or discount the erroneous invoice, or a _Nota de Débito_ (Debit Note, Code 56) to increase the value.

By the time you notice, the XML will have already reserved its folio. It's just better to accept the error, or provide drafting in your app before committing data to the library.

* **Why is this library forcing me to use Queues and background jobs just to create an XML? Can't I just build and send it synchronously in my controller?**

Because XML compilation is computationally costly.

You can use `->buildSync()` to get the XML immediately (useful for printing receipts), but you shouldn't bypass the envelope and polling architecture. Use it only when required (like for immediately printing PDF) and sparingly.

* **How do I automate the download of the CAF (Folios) so my users don't have to upload XML files manually? Is there a REST endpoint for that?**

The SII does not have REST/SOAP endpoints for CAF handling. You must download the CAF XML manually from the SII portal and load it into the library.

Some web applications do this by using headless browser automation ([Playwright](https://playwright.dev/), [Puppeteer](https://pptr.dev/), [Selenium](https://www.selenium.dev/), etc), and charging for the privilege.

This library _may_ include this in the future based on support.

* **I need to print a Boleta (Receipt) on an 80mm thermal POS printer. How do I make the library's PDF generator format the paper size correctly?**

Don't use PDFs for thermal receipts, these have a static height. Use a raw thermal command library (like [mike42/escpos-php](https://github.com/mike42/escpos-php)) instead.

* **Can I just save up all my Boletas (Receipts) for the day and send a single summary to the SII at midnight to save server resources?**

No. Electronic receipts (Codes 39 and 41) must be transmitted immediately. You can only do that with the remaining DTE types.

* **I use UUIDs for all my database primary keys. Can I just drop the customer's cart UUID into the reference Folio field?**

No, references are character-limited. Use an alternative reference instead (random string, integer, etc.)

* **I'm building a multi-tenant SaaS. Can I configure the mailbox listener to poll `dte@client-a.cl`, `dte@client-b.cl`, and `dte@client-c.cl`?**

No, this library does not support fetching emails from multiple sources. Instead, point your customers to a unified `dte@your-app.cl` and fetch from there. This is a performance tradeoff. 

* **I wrote an event listener to automatically accept incoming vendor invoices the second they hit the mailbox. Good idea?**

Terrible idea! **Never** pay invoices immediately upon email receipt. If you didn't put money on it, the invoice may be a scam.

* **I tried streaming 100+ invoice PDFs at once using the `binary()` method to send to a third-party API, and my server crashed with an Out Of Memory (OOM) error. Is the package leaking memory?**

It's not a leak. You're just holding massive amounts of data in RAM. Stop using `binary()` for large operations. Use a single queued-job for each PDF.

* **If I install this on my application, I can instantly issue DTE legally?**

No, you need to be [certified by SII](#certification--production). This library helps your application to do that.

* **If I build this automated integration, can my client still log into the free SII web portal to manually issue a quick invoice if my app goes down?**

No. Once they sign to production with your software, they are locked out of the free SII tool. Ensure your app has high availability.

* **I want to charge clients for my web app. Do I have to open-source my entire codebase if I use this? I heard Chilean DTE libraries enforce this.**

No, you can keep your application closed-source and commercial, or even totally private.

[LibreDTE](https://github.com/LibreDTE/libredte-lib-core) uses the [AGPL License](https://choosealicense.com/es/licenses/agpl-3.0/), which imposes restrictions on usage and distribution. This library does not.

* **Do I need to back-up XML for 6 years?**

You're not legally required to have a backup, but you're legally required to do whatever you need to keep **ALL** your XML documents for 6 years, from `DTE` and `EnvioDTE` to documents received and sent to businesses.

Back-up your data periodically (database, storage). I recommend the [3-2-1 backup strategy](https://en.wikipedia.org/wiki/Glossary_of_backup_terms). You can always dump the database.

## Glossary

This is a small glossary for some concepts this library works with.

* **CAF (Código de Autorización de Folios):** An official XML file granted by SII containing an authorized range of serial numbers (folios) and an RSA private key for a specific document type. A valid CAF block must be loaded in the database before issuing documents.
* **Digital Certificate:** A PKCS#12 (`.p12|pfx`) digital signature file that authorizes your business to sign tax documents and communicate with SII endpoints.
* **DTE (Documento Tributario Electrónico):** A digitally signed XML document representing a legally binding tax event (Invoice, Receipt, Credit Note, etc.).
* **DTE Envelope:** A signed XML that contains all your signed DTE to send to SII in bulk per-business. Boletas (Receipts) use a specialized `<EnvioBOLETA>` envelope that groups up to 500 documents.
* **PDF:** Visual (and legal) representation of the DTE XML that includes a [PDF417](https://en.wikipedia.org/wiki/PDF417) 2D barcode. Not legally required to be sent to the business on _production_, but mandatory for _certification_. Respectable business always sends these after documents are approved.

> [!NOTE]
>
> Daily receipt (boletas) summary reports [were abolished on August 1, 2022](https://www.sii.cl/noticias/2022/040822noti01rp.htm) (SII Res. Ex. N° 53 de 2022). Receipts are transmitted to SII via envelopes and populate the taxpayer's sales registry (Registro de Compras y Ventas, RCV) automatically. The IECV process is honored as it's needed for certification.

## License

This library is not affiliated with SII, nor the Chilean Government, or any of the businesses that implement SII connectors, ERP, or both.

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

[Laravel](https://laravel.com/) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2026 Laravel LLC.
