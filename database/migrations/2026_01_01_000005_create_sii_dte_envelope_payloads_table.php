<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;

/**
 * @see SiiDteEnvelopePayload
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_dte_envelope_payloads', function (Blueprint $table): void {
            $table->id();
            $table
                ->foreignIdFor(SiiDteEnvelope::class, 'sii_dte_envelope_id')
                ->unique()
                ->constrained('sii_dte_envelopes')
                ->cascadeOnDelete();
            $table->longText('xml');
            $table->longText('sii_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_dte_envelope_payloads');
    }
};
