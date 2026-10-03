<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Models\SiiInterchangeLog;

/**
 * @see SiiInboundDocument
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_inbound_documents', function (Blueprint $table): void {
            $table->id();
            $table
                ->foreignIdFor(SiiInterchangeLog::class, 'sii_interchange_log_id')
                ->nullable()
                ->constrained('sii_interchange_logs')
                ->nullOnDelete();
            $table->rut('issuer');
            $table->rut('receiver');
            $table->unsignedTinyInteger('document_type');
            $table->unsignedInteger('folio');
            $table->date('issued_on');
            $table->unsignedInteger('amount_total');
            $table->string('status')->default('received')->index();
            $table->string('claim_status')->nullable()->index();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'issuer_num',
                'issuer_vd',
                'receiver_num',
                'receiver_vd',
                'document_type',
                'folio',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_inbound_documents');
    }
};
