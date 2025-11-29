<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add template_package to websites
        Schema::table('websites', function (Blueprint $table) {
            $table->string('template_package')->nullable()->after('type');
        });

        // Migrate existing laravel1 websites to laravel-hotel-1
        DB::table('websites')
            ->where('type', 'laravel1')
            ->update(['template_package' => 'laravel-hotel-1']);

        // Update pages table - template_type can now store variants like 'detail', 'detail-variant2', etc.
        // No schema change needed, just using existing template_type field differently
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('template_package');
        });
    }
};
