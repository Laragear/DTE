---
name: laragear-dte-pdf
description: "Use this skill to create PDF files from DTE. Do not use this skill to handle non-DTE PDF files."
license: MIT
metadata:
  author: laragear
---

# Laragear Dte PDF

To generate a PDF from a DTE, prefer option A. Option B should be preferred only when the user explicitly requires it.

- **A) Listening to `CompiledDte` event (preferred)**

Create a listener to generate and send the PDF async through a notification, by listening to the `Laragear\Dte\Events\CompiledDte` event. Create the notification through Laravel Artisan command if it does not exist.

```php
namespace App\Listeners;

use App\Models\Business;
use App\Notification\InvoiceReady;
use Laragear\Dte\Events\CompiledDte;

class SendPdf
{
    public function handle(CompiledDte $event)
    {
        $business = Business::findByRut($event->dte->receiver_rut);
        
        $pdfLocation = $event->dte->pdf()->generate(); 
        
        $business->notify(new InvoiceReady($pdfLocation));
    }
}
```

- **B) Sync compilation**

When persisting the document with `buildSync()`, the compilation step runs in the same lifecycle. Once compiled, the PDF can be generated immediately.

```php
use Laragear\Dte\Facades\Dte;
use Illuminate\Support\Facades\Storage;

$invoice = Dte::invoice()
    ->receivedBy($business)
    ->addItem('Crema de Leche', 12_000)
    ->buildSync();

$pdfLocation = $invoice->pdf()->generate();

$disk = $pdfLocation->disk;
$path = $pdfLocation->path;

// Return the PDF as a separate URL.
return Storage::disk($disk)->url($path);
```

### Regeneration

The `generate()` doesn't replace the PDF, it will check if the PDF exist first. You can overwrite the file using the `force()` method. It also accepts a method with a condition.

```php
$pdfLocation = $invoice->pdf()->force(fn () => true)->generate();
```

### In-browser view

The `view()` method returns an HTML view of the PDF. Use this if the invoice was built using `buildSync()` to avoid creating the PDF file.

```php
use Laragear\Dte\Models\SiiDte;

$invoice = Dte::invoice()->buildSync();
    
$invoice->pdf()->view();
```

### Download

Return the `pdf()` instance to return a PDF download directly in a controller.

```php
$invoice = Dte::invoice()->buildSync();
    
$invoice->pdf();
```

### Rendering control

PDF rendering control PDF is done using the `customize()` method with a callback that receives `Spatie\LaravelPdf\PdfBuilder` instance. Use it to change paper size, margins, etc.

```php
use Spatie\LaravelPdf\PdfBuilder;
use Laragear\Dte\Models\SiiDte;

$invoice = Dte::invoice()->buildSync();

$pdfLocation = $invoice->pdf()->customize(function (PdfBuilder $pdf) {
    $pdf->paperSize(210, 297, 'mm')
        ->margins(10, 10, 10, 10);
})->generate();
```

### Direct Binary Access

Raw binary contents of the PDF can be acquired using `binary()`. Only use it when streaming binary data to another service, handing-off the PDF data from the application.

```php
use Laragear\Dte\Models\SiiDte;

$invoice = Dte::invoice()->buildSync();

$invoice->pdf()->binary();
```

