# Certification & Production

> [!CAUTION]
>
> For Certification and Production, you **require a real certificate**, [which can be bought separately](https://www.sii.cl/servicios_online/1039-certificado_digital-1182.html). Do not proceed until it's made available to the library.

To operate with the SII, the _Certification Process_ is mandatory. SII will _test_ your application if it complies with the basic and legal procedures to manage DTE. Luckily for you, this library makes this process simple: there is no special certification API to learn. You build documents, envelopes, books, and PDFs exactly like you would in production, with the environment pointing at the SII test servers.

## How certification works

The process has four phases. Each one must pass on the SII website before you move to the next:

1. **Set de Pruebas (Test Set).** You create the documents the SII assigned to you, send them, and upload your sales and purchases books.
2. **Simulación (Simulation).** You send a batch of realistic documents, like a normal week of work at your company.
3. **Intercambio de Información (Interchange).** The SII sends you supplier invoices, and you must receive and accept them.
4. **Documentos Impresos (Print Samples).** You generate the PDF versions of your documents, with the stamp and barcode, for a human reviewer.

Once all four pass, you sign the compliance declaration on the SII portal and receive the resolution that authorizes you to operate in production.

## Before you start

**1. Digital certificate.** You must [acquire a P12/PFX certificate](https://www.sii.cl/servicios_online/1039-certificado_digital-1182.html) from the SII and make it available to the library, along with its password. Use any flow to store the certificate and password in your app, like storing it both in the database.

Use the `resolveUsing()` to manually return an instance of `DigitalCertificate` with the certificate string and the certificate password, if you haven't already.

```php
use App\Models\Company;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Certificate\DigitalCertificate;

CertificateResolver::resolveUsing(function (): ?DigitalCertificate {
    $company = Company::first();

    if ($company) {
        return null;
    }

    return new DigitalCertificate($company->cert, $company->cert_pass);
});
```

**2. Company configuration.** Register your company's issuer data:

```php
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\IssuerData;
use Laragear\Rut\Rut;

public function register()
{
    ConfigurationManager::resolveIssuerUsing(function (?Rut $rut): IssuerData {
        return new IssuerData(
            rut: Rut::parse('76.123.456-0'),
            name: 'Tu empresa SpA',
            businessActivity: '620100',
            economicActivity: ['620100'],
            address: 'Calle Falsa 123',
            commune: 'SANTIAGO',
            resolutionDate: '2026-01-01',
            resolutionNumber: 0,
        );
    });
}
```

> [!NOTE]
>
> Use your **certification** resolution date and number. These are published on the SII certification portal under your company's test data. You need this beforehand starting certification. The same values go on every envelope and book you send during certification.

Most of the time, sender of the documents (`RutEnvia`) differs from the issuer of the documents, especially for businesses. Register the sender separately:

```php
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Rut\Rut;

public function register()
{
    // ...

    ConfigurationManager::resolveSenderUsing(fn() => Rut::parse('76.123.456-0'));
}
```

**3. Switch to the certification environment:**

You should do this dynamically in your application via `EnvironmentResolver`, but as an alternative, you may change the environment variable.

```dotenv
DTE_ENV=certification
```

## Endpoints and settings

You don't need to point the library at any URL by hand. Setting `DTE_ENV=certification` makes every upload, query, and claim go to the SII test servers (Maullín), and `DTE_ENV=production` switches everything to the real ones (Palena). For reference, these are the servers involved:

| What                                                | Certification (`DTE_ENV=certification`)                    | Production (`DTE_ENV=production`)                     |
|:----------------------------------------------------|:-----------------------------------------------------------|:------------------------------------------------------|
| Sending documents and books (SOAP)                  | `https://maullin.sii.cl`                                   | `https://palena.sii.cl`                               |
| Receipts (boletas) API                              | `https://apicert.sii.cl` (upload: `https://pangal.sii.cl`) | `https://api.sii.cl` (upload: `https://rahue.sii.cl`) |
| Accepting or rejecting supplier invoices (reclamos) | `https://ws2.sii.cl/WSREGISTRORECLAMODTECERT/...`          | `https://ws1.sii.cl/WSREGISTRORECLAMODTE/...`         |

The only mailbox setting you may need is the driver the library uses to read your interchange inbox (`DTE_DIM_MAILER`), which is only used in Phase 3. This is not strictly needed because the SII will allow you to send/receive without direct DIM connection.

## Phase 1 — Set de Pruebas (Test Set)

The SII hands you a **test set**: a list of document specifications your application must create and submit. These are **not** documents you receive from SII; they are instructions to build DTEs from scratch using the exact data provided.

A typical test set looks like this:

```
FACTURA ELECTRONICA 			 32
FACTURA DEL GIRO CON DERECHO A CREDITO
  10221		  10335

NOTA DE CREDITO				451
NOTA DE CREDITO POR DESCUENTO A FACTURA 234
		   2880
```

Each entry specifies: document type, folio number, description, amounts, and sometimes references to other documents in the set.

### Create the DTEs from the test set instructions

For each entry in the test set, create a DTE in your database. The **receiver RUT** should be a valid customer RUT; use different RUTs for different invoices (do not repeat them), as the SII instructs, so you'll need to find real businesses with their apropiate data.

Use `forTestCase()` to mark a DTE as part of a test set case. This adds the required SET/CASO reference line automatically:

```php
use App\Models\Business;
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Facades\SiiCreditNote;

// Create an invoice for the test set
$dte = SiiInvoice::issuedBy('76.123.456-0')     // your company RUT
    ->receivedBy(Business::find(1))             // a customer RUT (distinct per invoice)
    ->addItem(item: 'Cajón AFECTO', unitPrice: 1599, quantity: 135)
    ->forTestCase('5034081-1')                  // marks as CASO 5034081-1
    ->build();

// Credit note referencing an original invoice
$dte = SiiCreditNote::issuedBy('76.123.456-0')
    ->receivedBy(Business::find(2))
    ->forTestCase('5034081-5')
    ->annul(SiiDte::find(1), reason: 'CORRIGE GIRO DEL RECEPTOR')
    ->build();
```

The `forTestCase()` prepends a reference line with `TpoDocRef="SET"` and `RazonRef="CASO xxxxx-x"` on every `references()` call — this survives correction methods like `annul()` that replace the reference list.

> [!IMPORTANT]
>
> When adding a SET reference manually, the **first** reference of every test set DTE must have:
> - `TpoDocRef` = `"SET"` (handled by `forTestCase()`)
> - `RazonRef` = `"CASO xxxxx-x"` (handled by `forTestCase()`)
>
> Credit notes referencing original invoices add the invoice reference on line 2 (via `annul()` or `addReference()`).

### Send the documents

After creating all DTEs, upload each test document individually, or pack them into a manually created envelope and send it through the regular production flow (compile each `SiiDte`, create an `SiiDteEnvelope` addressed to the SII, sign, and upload). The SII returns a **TrackID** per envelope.

Poll the result using the `dte:poll-track-status` Artisan Command, or programmatically:

```php
use Laragear\Dte\Jobs\PollEnvelopeTrackIdJob;

PollEnvelopeTrackIdJob::dispatchSync($envelope);
```

Otherwise, you may want to schedule it for each minute to check.

### Upload the sales book (Libro de Ventas)

The SII doesn't just want your documents: it wants proof that your monthly sales records match what you sent. That's what the **sales book** is for. It's a single summary file listing every sales document your company issued during the test period — invoices, exempt invoices, debit notes, and credit notes — along with their totals. If a document isn't in the book, for the SII it doesn't exist in your accounting.

To build it, gather the documents you already sent in the envelope step (only the ones with `sent` or `accepted` status count, since those are the ones the SII actually received), and make sure they all belong to the same tax month (`YYYY-MM`, like `2026-09`). Then generate the signed book and upload it. You need three things alongside the documents: the tax period, your certification resolution date and number (the same values from your company configuration), and the sender RUT (usually your own company RUT).

```php
use Laragear\Dte\Gateways\IecvUploadGateway;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Services\IecvGenerator;
use Laragear\Rut\Rut;

$issuer = Rut::parse('76.123.456-0');
$sender = Rut::parse('76.123.456-0');

// Only documents the SII already received, all from the same month.
$dtes = SiiDte::whereNotNull('sii_dte_envelope_id')
    ->whereIn('status', ['sent', 'accepted'])
    ->whereIn('document_type', [33, 34, 56, 61])
    ->get();

$signedXml = app(IecvGenerator::class)->generateSales(
    $issuer,
    $dtes,
    period: '2026-09',
    resolutionDate: '2026-01-01',
    resolutionNumber: 0,
    senderRut: $sender,
);

$trackId = app(IecvUploadGateway::class)->upload($issuer, $sender, $signedXml);
```

The upload returns a **TrackID**, just like the document envelopes. The SII reviews the book and marks it accepted or rejected on the certification portal.

### Upload the purchases book (Libro de Compras)

The **purchases book** is the mirror image: instead of what you sold, it lists what your company *bought* from suppliers during the test period. Since these are third-party documents (you didn't issue them, so there are no `SiiDte` records for them), you describe each one by hand as an entry with the data from the SII's purchases test set: document type, folio, date, supplier RUT, and amounts. The library computes the taxes and totals from the net amounts, then builds, signs, and uploads the book the same way.

```php
use Laragear\Dte\Builders\Iecv\IecvPurchaseData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\IecvProperty;
use Laragear\Dte\Gateways\IecvUploadGateway;
use Laragear\Dte\Services\IecvGenerator;
use Laragear\Rut\Rut;

$entries = [
    IecvPurchaseData::make(
        documentType: DteType::InvoicePhysical, // 30
        folio: 234,
        issuedOn: '2026-09-01',
        issuerRut: '99.888.777-1',              // supplier RUT from the set
        amountNet: 45899,
    ),
    IecvPurchaseData::make(
        documentType: DteType::Invoice,         // 33
        folio: 781,
        issuedOn: '2026-09-01',
        issuerRut: '99.888.777-1',
        amountNet: 30082,
        ivaCommonUse: true,                     // IVA uso común
    ),
    IecvPurchaseData::make(
        documentType: DteType::CreditNote,      // 61
        folio: 451,
        issuedOn: '2026-09-01',
        issuerRut: '99.888.777-1',
        amountNet: 2880,
        referenceType: DteType::InvoicePhysical, // references factura 234
        referenceFolio: 234,
    ),
];

// Pass the IVA proporcionalidad factor for uso común (set by SII)
$properties = [IecvProperty::CommonIvaFactor->of(0.60)];

$issuer = Rut::parse('76.123.456-0');
$sender = Rut::parse('76.123.456-0');

$signedXml = app(IecvGenerator::class)->generatePurchases(
    $issuer,
    $entries,
    period: '2026-09',
    resolutionDate: '2026-01-01',
    resolutionNumber: 0,
    senderRut: $sender,
    properties: $properties,
);

$trackId = app(IecvUploadGateway::class)->upload($issuer, $sender, $signedXml);
```

The `IecvPurchaseData` entry fields map to the book as follows:

| Entry field        | What it means                                      | Notes                                          |
|--------------------|----------------------------------------------------|------------------------------------------------|
| `documentType`     | The document type                                  | `DteType` enum or raw int (30, 33, 46, 61...)  |
| `folio`            | Folio from the test set                            | As printed in your SII test set                |
| `issuedOn`         | When the supplier issued it                        | `Y-m-d` format                                 |
| `issuerRut`        | The supplier's RUT                                 | Not your company RUT                           |
| `amountNet`        | "Monto Afecto" from the set                        | Taxes are computed from this                   |
| `amountExempt`     | "Monto Exento" from the set                        | Zero unless the set says otherwise             |
| `ivaCommonUse`     | IVA uso común                                      | Shows up in the resumen totals                 |
| `noCost`           | Entrega gratuita del proveedor                     | Writes `IndSinCosto=1`                         |
| `ivaRetainedTotal` | Compra con retención total del IVA                 | Writes `IVARetTotal`                           |
| `referenceType`    | For NC/ND referencing another document             | The referenced document type                   |
| `referenceFolio`   | Folio of the referenced document                   | The referenced folio                           |

> [!IMPORTANT]
>
> **IVA proporcionalidad.** For entries with `ivaCommonUse: true`, pass `IecvProperty::CommonIvaFactor->of(0.60)` (or the factor value SII specifies for your company). This populates `FctProp` and `TotCredIVAUsoComun` in the resumen.

---

### Phase 2 — Simulación (Simulation)

The simulation requires a production-like workrate: many documents with references, annulments, amendments, surcharges/discounts, and other advanced data. Because of this, the simulation is intentionally left to you: create the DTEs with the regular builders (`SiiInvoice`, `DocumentBuilder`, etc.), including any references and advanced items, then compile, envelope, send, and poll them like you would in production.

---

### Phase 3 — Intercambio de Información (Interchange)

The SII sends DTEs to your DIM (electronic inbox), simulating supplier invoices. Your app must process them and return the commercial receipt (`EnvioRecibos`, per Ley 19.983-96) plus the DTE-level acceptance via the SII SOAP claim web service. Use the regular production flow:

1. Fetch and process the SII email with the mailbox command, filtering by the SII interchange sender:

```bash
php artisan dte:fetch-mailbox --sender=sii_dte_intercambio@sii.cl
```

Alternatively, process a downloaded XML file or raw XML content directly:

```php
use Laragear\Dte\Actions\InboundDte\ProcessInboundDte;
use Laragear\Dte\Data\InboundEmailData;

$data = app(ProcessInboundDte::class)->forEmail(
    InboundEmailData::make('manual-file-'.now()->getTimestamp(), 'sii_dte_intercambio@sii.cl', 'Intercambio SII (Manual File)', $xmlString)
);
```

2. Commercially accept the resulting inbound document (this sends the acknowledgment receipt and the SII claim):

```php
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Rut\Rut;

$document = SiiInboundDocument::latest('id')->first();

$document->accept(
    Rut::parse('76.123.456-0'), // signer RUT (defaults to your company RUT)
    'Santiago',                 // signing location
    app(CertificateResolver::class)->resolve(Rut::parse('76.123.456-0')),
);
```

---

### Phase 4 — Documentos Impresos (Print Samples)

PDF representations of your DTEs, including the PDF417 barcode and _timbre electrónico_, are generated with the regular production procedure (`SiiDte::pdf()`). For example:

```php
use Laragear\Dte\Models\SiiDte;

foreach (SiiDte::where(['issuer_num' => 76123456, 'issuer_vd' => '0'])->lazyById(5) as $dte) {
    $dte->pdf()->generate();
}
```

The PDFs are stored on disk. If the SII requires a single concatenated PDF, you will need to merge them yourself using any free service, e.g.: [I Love PDF](https://www.ilovepdf.com/), [BentoPDF](https://www.bentopdf.com/), [EmbedPDF](https://www.embedpdf.com/tools/pdf-merge), [PrivatePDF Merge](https://privatepdfmerge.com/), [Pipefile](https://pipefile.com/tools/pdf-merger), [Toolflic](https://toolflic.com/tool/pdf-merge/), and many more.

See the PDF documentation in the README for the full `pdf()` API (`binary()`, `download()`, `url()`, etc.).

---

### Phase 5 — Declaración de Cumplimiento

You digitally sign (via the SII web portal) your compliance to operate on production servers.

> [!CAUTION]
>
> Once you sign, **you lose access to the free SII Web App to manage DTE**. Back up all your historical data before. If you consider this a drawback, desist and use this library as a way to manually mirror your data in SII.

---

### Phase 6 — Autorización

The SII validates your submission and issues an authorization resolution. You can now move to production.

```dotenv
DTE_ENV=production
```

If you have the prior DTE leftovers from certification, you should also truncate all tables to avoid mixing real data with dummies.

```bash
php artisan dte:purge
```
