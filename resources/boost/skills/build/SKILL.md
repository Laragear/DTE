---
name: laragear-dte-build
description: "Use this skill to create SII documents (DTE) inside business logic (buying, selling, etc). Don't use this skill for purchase history, commercial notifications, commercial acknowledgement."
license: MIT
metadata:
  author: laragear
---

# Laragear Dte Build

Use the `Sii{DocumentType}` facade to create a DTE depending on the type required to legally issue. Each facade instances a custom builder for each document type:

| Builder                        | Description                          | Reason                              |
|--------------------------------|--------------------------------------|-------------------------------------|
| `SiiInvoice::class`            | Electronic invoice / exempt          | Selling B2B                         |
| `SiiReceipt::class`            | Electronic receipt (boleta)          | Selling B2C                         |
| `SiiCreditNote::class`         | Credit note                          | Anull/Amends/Discount Invoice       |
| `SiiDebitNote::class`          | Debit note                           | Anull/Amend/Charge Invoice          |
| `SiiDispatchGuide::class`      | Dispatch guide (guía de despacho)    | Moving unsold goods                 |
| `SiiInvoiceLiquidation::class` | Invoice liquidation                  | Consignment sales, Commissions      |
| `SiiPurchaseInvoice::class`    | Purchase invoice (factura de compra) | Buying from consumers/international |
| `SiiAecBuilder::class`         | AEC (Acuse Electrónico de Cargo),    | Factoring / Cession                 |

Depending on the builder, some methods will be available and others won't. You're required to check the methods available for each builder on the source code using codebase tools available, e.g. search codebase, find nodes, etc.

## Receiver

Call `receivedBy` to set in the receipt the optional person RUT and name.

```php
use Laragear\Dte\Facades\SiiReceipt;

$invoice = SiiReceipt::receivedBy('18.765.321-0', 'Jorge Pérez');
```

All other documents that are not receipts, the full Business data is required. This must be done using the `ReceiverData` object and filling all required properties.

```php
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Facades\SiiInvoice;

$receiver = ReceiverData::make(
    rut: '76.123.456-0',
    name: 'Ferretería Pérez Ltda.',
    activity: 'Compra venta de artículos de construcción',
    email: 'compras@feperez.cl', // Optional
    address: 'Avenida Principal 48', 
    commune: 'Osorno',
    city: 'Osorno',
);

$invoice = SiiInvoice::receivedBy($receiver);
``` 

### Eloquent Model as Receiver

Implement the `Laragear\Dte\Contracts\Receivable` interface on models that can be used as valid document receivers: Business, Company, Tenant, Customer, Client, Organization, etc. Pass the model instance directly to `receivedBy()` instead of transforming it manually into a `ReceiverData`.

```php
use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Contracts\Receivable;
use Laragear\Dte\Data\ReceiverData;

class Business extends Model implements Receivable
{
    // ...

    public function toReceivable(): ReceiverData
    {
        return ReceiverData::make(
            // ... 
        );
    }
}

use Laragear\Dte\Facades\SiiInvoice;

$business = Business::find(66);

$invoice = SiiInvoice::receivedBy($business);
```

## Adding Items

Use `addItem()` to add simple description-price items. If the item should be legally exempt from taxes, use `isExempt: true`.

```php
use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy($business)
    ->addItem('Crema de Leche', 12_000)
    ->addItem('Clases de ordeñamiento', 56_000, isExempt: true)
    ->create();
```

For more control on the item to be added (price per unit, quantity, description, taxes, etc.), use the `ItemData` object.

```php
use Laragear\Dte\Data\Item;
use Laragear\Dte\Facades\SiiInvoice;

$item = Item::make(
    name: 'Crema de Leche', 
    unitPrice: 1_200,
    quantity: 10,
    discountPercentage: 0.15
);

$invoice = SiiInvoice::receivedBy('76.543.210-K', 'Helados S.A.')
    ->addItem($item)
    ->create();
```

### Eloquent Model as Item

Implement the `Laragear\Dte\Contracts\Itemable` interface on models that can be used as valid document receivers: Item, Article, Product, Service, Good, or LineItem, etc. Pass the model instance directly to `addItem()` instead of transforming it manually into a `Item`.

```php
use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Contracts\Itemable;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Facades\SiiInvoice;

class Product extends Model implements Itemable
{
    // ...

    public function toItem(): Item
    {
        return Item::make(
            // ... 
        );
    }
}

use Laragear\Dte\Facades\SiiInvoice;

$item = Product::find(4658);

$invoice = SiiInvoice::addItem($item);
```

## Persistence

Use the `create()` to persist the DTE into the database. The method returns an `Laragear\Dte\Models\SiiDte` model instance. Consider this instance as **READ-ONLY**. Use the model instance for later reference: attaching it to a purchase order, cart, internal invoicing/receipt, ERP ingress/egress registration, accounting API, etc.

```php
use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy($business)
    ->addItem('Crema de Leche', 12_000)
    ->create();
```

If the user requires printing a PDF immediately, use the `sync: true`. The operation will take a few seconds. The PDF will be able to be generated using `pdf()->generate()`.

```php
use Laragear\Dte\Facades\SiiInvoice;

$invoice = SiiInvoice::receivedBy($business)
    ->addItem('Crema de Leche', 12_000)
    ->create(sync: true);

$pdf = $invoice->pdf()->generate();

$disk = $pdf->disk;
$path = $pdf->path;

return Storage::disk($disk, $path);
```
