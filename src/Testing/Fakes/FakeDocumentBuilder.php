<?php

namespace Laragear\Dte\Testing\Fakes;

use Illuminate\Contracts\Events\Dispatcher;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Events\DteCreated;
use Laragear\Dte\Events\DteCreating;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use function app;

/**
 * Shared behavior for all fake document builders.
 *
 * @method DteType documentType()
 * @method array attributes()
 * @method array payloadData()
 * @method void validate()
 */
trait FakeDocumentBuilder
{
    /**
     * Documents created by all fakes of this concrete type.
     *
     * @var list<SiiDte>
     */
    protected static array $created = [];

    /**
     * Whether to persist created documents to the database.
     */
    protected static bool $storeDte = true;

    /**
     * Skip storing the document to the database.
     */
    public static function withoutStoringDte(): static
    {
        static::$storeDte = false;

        return app(static::class);
    }

    public function draft(): SiiDte
    {
        $this->validate();

        $this->dispatch(new DteCreating($this));

        $dte = $this->buildDte(DteStatus::Draft);

        $this->dispatch(new DteCreated($dte));

        return $dte;
    }

    public function build(bool $sync = false): SiiDte
    {
        if ($this->dte() !== null) {
            return $this->rebuild($sync);
        }

        $this->validate();

        $this->dispatch(new DteCreating($this));

        $dte = $this->buildDte(DteStatus::Pending);

        if ($sync) {
            $this->persistAndCompile($dte);
        }

        $this->dispatch(new DteCreated($dte));

        return $dte;
    }

    /**
     * Rebuild a hydrated document preserving its folio.
     */
    protected function rebuild(bool $sync): SiiDte
    {
        $dte = $this->dte();

        $dte->forceFill($this->attributes())->saveQuietly();
        $dte->payload->forceFill(['data' => $this->payloadData()])->saveQuietly();

        if ($sync) {
            app(Compile::class)->forDte($dte);
        }

        static::$created[] = $dte;

        return $dte;
    }

    /**
     * Dispatch a fake lifecycle event.
     */
    protected function dispatch(object $event): void
    {
        app(Dispatcher::class)->dispatch($event);
    }

    /**
     * Build an SiiDte from the current builder state.
     */
    protected function buildDte(DteStatus $status): SiiDte
    {
        $dte = new SiiDte;
        $dte->fill([...$this->attributes(), 'status' => $status]);

        if (static::$storeDte) {
            $dte->saveOrFail();
            $dte->payload()->create(['data' => $this->payloadData()]);
        } else {
            $dte->exists = true;
            $dte->wasRecentlyCreated = true;

            $payload = new SiiDtePayload;
            $payload->data = $this->payloadData();
            $dte->setRelation('payload', $payload);
        }

        static::$created[] = $dte;

        return $dte;
    }

    /**
     * Persist and compile a DTE synchronously.
     */
    protected function persistAndCompile(SiiDte $dte): void
    {
        if (!static::$storeDte) {
            $dte->exists = false;
            $dte->saveOrFail();
            $dte->payload()->create(['data' => $this->payloadData()]);
        }

        app(Compile::class)->forDte($dte);
    }

    /**
     * Assert the expected number of documents were created.
     */
    public static function assertCreated(?int $times = null): void
    {
        $count = count(static::$created);

        if ($times === null) {
            if ($count === 0) {
                static::fail('No documents were created.');
            }

            return;
        }

        if ($count !== $times) {
            static::fail("Expected {$times} documents to be created, but {$count} were created.");
        }
    }

    /**
     * Assert no documents were created.
     */
    public static function assertNotCreated(): void
    {
        static::assertCreated(0);
    }

    /**
     * Return all created documents.
     *
     * @return list<SiiDte>
     */
    public static function created(): array
    {
        return static::$created;
    }

    /**
     * Return the last created document, or null.
     */
    public static function lastCreated(): ?SiiDte
    {
        return static::$created[array_key_last(static::$created)] ?? null;
    }

    /**
     * Flush all created documents.
     */
    public static function flushCreated(): void
    {
        static::$created = [];
        static::$storeDte = true;
    }

    /**
     * Fail the test with a message.
     */
    protected static function fail(string $message): never
    {
        static::assertTrue(false, $message);
    }
}
