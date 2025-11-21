<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (!Schema::hasColumn('pages', 'template_type')) {
                $table->string('template_type')->nullable()->after('title');
            }
            if (!Schema::hasColumn('pages', 'template_data')) {
                $table->json('template_data')->nullable()->after('template_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'template_data')) {
                $table->dropColumn('template_data');
            }
            if (Schema::hasColumn('pages', 'template_type')) {
                $table->dropColumn('template_type');
            }
        });
    }
};