<?php

namespace Laragear\Dte\Pdf;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View as ViewContract;
use InvalidArgumentException;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Data\PdfData;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Pdf\Ted\Pdf417Encoder;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;
use Spatie\LaravelPdf\PdfBuilder as SpatiePdfBuilder;
use Symfony\Component\HttpFoundation\Response;

use function implode;
use function trim;

class PdfBuilder implements Responsable
{
    protected SiiDte $dte;

    protected ?string $disk = null;

    protected ?string $path = null;

    protected bool $force = false;

    protected ?Closure $customization = null;

    protected ?int $barcodeColumns = null;

    protected ?int $barcodeErrorCorrectionLevel = null;

    /**
     * Create a new PDF Builder instance.
     */
    public function __construct(
        protected Repository $config,
        protected Filesystem $storage,
        protected ViewFactory $view,
        protected Pdf417Encoder $barcode,
        protected TedExtractor $extractor,
        protected Compile $compile,
    ) {
        //
    }

    /**
     * Set the DTE for the builder.
     */
    public function forDte(SiiDte $dte): static
    {
        $this->dte = $dte;

        return $this;
    }

    /**
     * Set the disk to store the PDF.
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Set the path to store the PDF.
     */
    public function as(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Force the PDF to be generated even if it already exists.
     */
    public function force(mixed $condition = true): static
    {
        $this->force = value($condition, $this->dte);

        return $this;
    }

    /**
     * Set a callback to customize the underlying PDF builder.
     *
     * @param  Closure(SpatiePdfBuilder):void  $callback
     */
    public function customize(Closure $callback): static
    {
        $this->customization = $callback;

        return $this;
    }

    /**
     * Set the number of columns for the TED barcode.
     */
    public function tedColumns(int $columns): static
    {
        // Use 15 for large formats (Letter) and 9 for smaller ones.
        $this->barcodeColumns = $columns;

        return $this;
    }

    /**
     * Set the error correction level for the TED barcode (0-8).
     */
    public function tedErrorCorrectionLevel(int $level): static
    {
        // Higher values add more redundancy. Default is 5.
        $this->barcodeErrorCorrectionLevel = $level;

        return $this;
    }

    /**
     * Resolve the Spatie PDF Builder instance.
     */
    protected function resolveSpatieBuilder(?string $view = null, array $data = []): SpatiePdfBuilder|FakePdfBuilder
    {
        $html = $this->view($view, $data)->render();

        // Using the facade allows us to test with `Pdf::fake()`.
        $builder = Pdf::html($html)
            ->driver($this->config->get('dte.pdf.driver', 'dompdf'))
            ->format('letter');

        if ($this->customization) {
            ($this->customization)($builder);
        }

        return $builder;
    }

    /**
     * Get the View instance for the PDF.
     */
    public function view(?string $customView = null, array $data = []): ViewContract
    {
        $viewName = $customView
            ?? $this->config->get('dte.pdf.views.'.$this->dte->document_type->value)
            ?? $this->config->get('dte.pdf.views.default');

        $xml = $this->dte->payload?->xml;

        // PDFs render from the signed XML; pending documents compile inline first.
        if (! is_string($xml) && $this->dte->payload !== null && $this->dte->status->isCompilable()) {
            $this->dte = $this->compile->forDte($this->dte);
            $xml = $this->dte->payload?->xml;
        }

        $xml ?? throw new InvalidArgumentException('The DTE must have an XML payload to generate a PDF.');

        $ted = $this->extractor->extract($xml);

        if ($this->barcodeColumns !== null) {
            $this->barcode->setColumns($this->barcodeColumns);
        }

        if ($this->barcodeErrorCorrectionLevel !== null) {
            $this->barcode->setSecurityLevel($this->barcodeErrorCorrectionLevel);
        }

        return $this->view->make($viewName, array_merge([
            'dte' => $this->dte,
            'barcode' => $this->barcode->generate($ted),
            'cedible' => false,
        ], $data));
    }

    /**
     * Resolve the disk and path for this DTE's PDF.
     *
     * @return array{disk: string, path: string}
     */
    protected function resolveDiskAndPath(): array
    {
        return [
            'disk' => $this->disk
                ?? $this->config->get('dte.pdf.disk') ?? $this->config->get('filesystems.default'),
            'path' => $this->path
                ?? trim($this->config->get('dte.pdf.prefix', 'dte/pdf'), '/').'/'.$this->uniqueName('.pdf'),
        ];
    }

    /**
     * Generates and stores the PDF.
     */
    public function generate(): PdfData
    {
        ['disk' => $disk, 'path' => $path] = $this->resolveDiskAndPath();

        $storage = $this->storage->disk($disk);

        if ($this->force || ! $storage->exists($path)) {
            $storage->put($path, $this->binary());
        }

        return new PdfData($disk, $path);
    }

    /**
     * Direct binary access for advanced usage.
     */
    public function binary(): string
    {
        return $this->resolveSpatieBuilder()->generatePdfContent();
    }

    /**
     * Download the PDF as a Response.
     */
    public function download(?string $name = null, array $headers = []): SpatiePdfBuilder|FakePdfBuilder
    {
        return $this->resolveSpatieBuilder()->headers($headers)->download($name ?? $this->uniqueName('.pdf'));
    }

    /**
     * Returns an unique PDF name for this DTE.
     */
    public function uniqueName(string $append = ''): string
    {
        return implode('_', [
            $this->dte->issuer_rut->formatBasic(),
            $this->dte->document_type->value,
            $this->dte->folio,
            $this->dte->created_at->format('Y-m-d_His'),
        ]).$append;
    }

    /**
     * Get the URL to the PDF.
     */
    public function url(): string
    {
        $pdf = $this->generate();

        return $this->storage->disk($pdf->disk)->url($pdf->path);
    }

    /**
     * Get a temporary URL to the PDF.
     */
    public function temporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        $pdf = $this->generate();

        $disk = $this->storage->disk($pdf->disk);

        // The Filesystem contract does not expose temporary URLs; only the
        // adapter instance returned by disk() does.
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        return $disk->temporaryUrl($pdf->path, $expiration, $options);
    }

    /**
     * Delete the PDF if it exists.
     */
    public function delete(): bool
    {
        ['disk' => $disk, 'path' => $path] = $this->resolveDiskAndPath();

        return $this->storage->disk($disk)->delete($path);
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        return $this->resolveSpatieBuilder()->inline()->toResponse($request);
    }
}
