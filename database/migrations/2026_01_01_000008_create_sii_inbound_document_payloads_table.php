<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Models\SiiInboundDocumentPayload;

/**
 * @see SiiInboundDocumentPayload
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_inbound_document_payloads', function (Blueprint $table): void {
            $table->id();
            $table
                ->foreignIdFor(SiiInboundDocument::class, 'sii_inbound_document_id')
                ->unique()
                ->constrained('sii_inbound_documents')
                ->cascadeOnDelete();
            $table->longText('xml')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_inbound_document_payloads');
    }
};
