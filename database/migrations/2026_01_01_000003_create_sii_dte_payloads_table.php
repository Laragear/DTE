<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;

/**
 * @see SiiDtePayload
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_dte_payloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(SiiDte::class, 'sii_dte_id')->unique()->constrained('sii_dtes')->cascadeOnDelete();
            $table->json('header_id_doc')->nullable();
            $table->json('header_issuer')->nullable();
            $table->json('header_receiver')->nullable();
            $table->json('header_transport')->nullable();
            $table->json('header_totals')->nullable();
            $table->json('header_other_currency')->nullable();
            $table->json('detail_items')->nullable();
            $table->json('subtotals')->nullable();
            $table->json('global_modifiers')->nullable();
            $table->json('references')->nullable();
            $table->json('commissions')->nullable();
            $table->json('emission_georef')->nullable();
            $table->json('timber_handling')->nullable();
            $table->longText('xml')->nullable();
            $table->longText('sii_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_dte_payloads');
    }
};
