<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiCaf;

/**
 * @see SiiCaf
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_cafs', function (Blueprint $table): void {
            $table->id();
            $table->rut('issuer');
            $table->unsignedTinyInteger('document_type');
            $table->unsignedInteger('folio_from');
            $table->unsignedInteger('folio_to');
            $table->unsignedInteger('folio_current');
            $table->json('folio_annuled')->nullable();
            $table->date('authorized_on');
            $table->date('expires_on')->nullable();
            $table->timestamp('depleted_at')->nullable();
            $table->longText('xml');
            $table->timestamps();

            $table->unique(['issuer_num', 'issuer_vd', 'document_type', 'folio_from', 'folio_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_cafs');
    }
};
