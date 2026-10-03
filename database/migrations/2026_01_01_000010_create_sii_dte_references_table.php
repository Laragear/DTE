<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteReference;

/**
 * @see SiiDteReference
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_dte_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(SiiDte::class, 'sii_dte_id')->constrained('sii_dtes')->cascadeOnDelete();
            $table->foreignIdFor(SiiDte::class, 'target_dte_id')->nullable()->constrained('sii_dtes')->nullOnDelete();
            $table->string('document_type', 4);
            $table->string('folio')->nullable();
            $table->date('date')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedTinyInteger('reference_code')->nullable();
            $table->timestamps();

            $table->index('target_dte_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_dte_references');
    }
};
