<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDteEnvelope;

/**
 * @see SiiDteEnvelope
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_dte_envelopes', function (Blueprint $table): void {
            $table->id();

            // Indexing the RUT will make retrieval faster for both issuer and sender.
            $table->rut('issuer')->index();
            $table->rut('sender')->index();

            $table->string('type', 20);
            $table->string('track_id')->nullable()->unique();
            $table->date('resolution_date');
            $table->unsignedInteger('resolution_number');
            // Avoid full table sweeps as the table grows when polling for pending envelopes
            $table->string('status')->default(EnvelopeStatus::DEFAULT->value)->index();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->json('repairs')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('poll_at')->nullable();
            $table->timestamps();
        });

        Schema::table('sii_dtes', function (Blueprint $table): void {
            $table
                ->foreign('sii_dte_envelope_id')
                ->references('id')
                ->on('sii_dte_envelopes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sii_dtes', function (Blueprint $table): void {
            $table->dropForeign(['sii_dte_envelope_id']);
        });

        Schema::dropIfExists('sii_dte_envelopes');
    }
};
