<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;

/**
 * @see SiiDte
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_dtes', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(SiiCaf::class, 'sii_caf_id')->nullable()->constrained('sii_cafs')->nullOnDelete();
            $table->unsignedInteger('sii_dte_envelope_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->unsignedTinyInteger('pack_retries')->default(0);
            $table->rut('issuer');
            $table->rut('receiver');
            $table->unsignedTinyInteger('document_type');
            $table->unsignedInteger('folio')->nullable();
            $table->date('issued_on')->nullable();
            $table->unsignedInteger('amount_net')->default(0);
            $table->unsignedInteger('amount_exempt')->default(0);
            $table->unsignedInteger('amount_taxes')->default(0);
            $table->json('taxes')->nullable();
            $table->boolean('iva_common_use')->default(false);
            $table->unsignedInteger('amount_total')->default(0);
            $table->string('status')->default('draft')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->json('repairs')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['issuer_num', 'issuer_vd', 'document_type', 'folio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_dtes');
    }
};
