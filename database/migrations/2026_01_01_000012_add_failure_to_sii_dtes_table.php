<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        Schema::table('sii_dtes', function (Blueprint $table): void {
            $table->json('failure')->nullable()->after('repairs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sii_dtes', function (Blueprint $table): void {
            $table->dropColumn('failure');
        });
    }
};
