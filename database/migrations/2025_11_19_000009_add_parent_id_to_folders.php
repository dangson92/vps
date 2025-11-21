<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('folders') && !Schema::hasColumn('folders', 'parent_id')) {
            Schema::table('folders', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('website_id');
                $table->index(['parent_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('folders') && Schema::hasColumn('folders', 'parent_id')) {
            Schema::table('folders', function (Blueprint $table) {
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
    }
};