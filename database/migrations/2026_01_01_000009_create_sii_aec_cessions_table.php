<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\Dte\Models\SiiAecCession;
use Laragear\Dte\Models\SiiDte;

/**
 * @see SiiAecCession
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sii_aec_cessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(SiiDte::class, 'sii_dte_id')->constrained('sii_dtes')->cascadeOnDelete();
            $table->unsignedInteger('cession_number')->default(1);
            $table->rut('assignee');
            $table->unsignedInteger('amount_total');
            $table->date('last_due_on');
            $table->text('terms')->nullable();
            $table->json('data')->nullable();
            $table->longText('xml')->nullable();
            $table->string('track_id')->nullable()->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['sii_dte_id', 'cession_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sii_aec_cessions');
    }
};
