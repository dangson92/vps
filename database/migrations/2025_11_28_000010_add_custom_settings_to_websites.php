<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('websites', 'custom_settings')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->json('custom_settings')->nullable()->after('nginx_config');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('websites', 'custom_settings')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn('custom_settings');
            });
        }
    }
};

