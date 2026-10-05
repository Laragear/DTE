<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Models\SiiIecv;

/**
 * @see SiiIecv
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_iecvs', function (Blueprint $table): void {
            $table->id();

            // Indexing the RUT will make retrieval faster for both issuer and sender.
            $table->rut('issuer')->index();
            $table->rut('sender')->index();

            // VENTA (sales) or COMPRA (purchases), matching the IECV <TipoOperacion> tag.
            $table->string('type', 20);

            // Tax period in AAAA-MM format, matching the IECV <PeriodoTributario> tag.
            $table->string('period', 7);

            $table->date('resolution_date');
            $table->unsignedInteger('resolution_number');
            // Nullable because the book row is persisted before the XML exists, so a
            // crash while building or signing leaves a recoverable row behind.
            $table->longText('xml')->nullable();
            $table->string('track_id')->nullable()->unique();
            // Avoid full table sweeps as the table grows when polling for pending books
            $table->string('status')->default(IecvStatus::DEFAULT->value)->index();
            $table->json('errors')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('poll_at')->nullable();
            $table->timestamps();
        });

        // Partial unique index: only one in-flight book per issuer, operation and
        // period. Terminal books (accepted, rejected, failed) are excluded so a
        // period can be filed again after the SII ruled on the previous book.
        DB::statement(
            "CREATE UNIQUE INDEX sii_iecvs_period_unique
             ON sii_iecvs (issuer_num, issuer_vd, type, period)
             WHERE status NOT IN ('accepted', 'rejected', 'failed')"
        );

        Schema::table('sii_dtes', function (Blueprint $table): void {
            $table->foreignIdFor(SiiIecv::class, 'sii_iecv_id')
                ->nullable()
                ->constrained('sii_iecvs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sii_dtes', function (Blueprint $table): void {
            $table->dropForeign(['sii_iecv_id']);
        });

        Schema::dropIfExists('sii_iecvs');
    }
};
