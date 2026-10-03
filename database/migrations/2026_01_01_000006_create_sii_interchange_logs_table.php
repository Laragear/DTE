<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiInterchangeLog;

/**
 * @see SiiInterchangeLog
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_interchange_logs', function (Blueprint $table): void {
            $table->id();
            $table
                ->foreignIdFor(SiiDteEnvelope::class, 'sii_dte_envelope_id')
                ->nullable()
                ->constrained('sii_dte_envelopes')
                ->nullOnDelete();
            $table->string('message_id')->nullable()->unique();
            $table->string('direction', 20)->index();
            $table->string('type', 40)->index();
            $table->string('sender');
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->longText('raw_email')->nullable();
            $table->longText('response_xml')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_interchange_logs');
    }
};
