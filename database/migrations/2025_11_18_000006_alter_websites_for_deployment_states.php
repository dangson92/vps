<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('websites', 'content_version')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->unsignedInteger('content_version')->default(1)->after('status');
            });
        }

        if (!Schema::hasColumn('websites', 'deployed_version')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->unsignedInteger('deployed_version')->default(0)->after('content_version');
            });
        }

        if (!Schema::hasColumn('websites', 'content_updated_at')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dateTime('content_updated_at')->nullable()->after('deployed_version');
            });
        }

        if (!Schema::hasColumn('websites', 'deployed_at')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dateTime('deployed_at')->nullable()->after('content_updated_at');
            });
        }

        if (!Schema::hasColumn('websites', 'suspended_at')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dateTime('suspended_at')->nullable()->after('deployed_at');
            });
        }

        // Indexes: 'status' was already indexed in initial migration.
        // Keep schema changes minimal here; add of domain index is optional and can be handled later.
    }

    public function down(): void
    {
        if (Schema::hasColumn('websites', 'suspended_at')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn('suspended_at');
            });
        }

        if (Schema::hasColumn('websites', 'deployed_at')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn('deployed_at');
            });
        }

        if (Schema::hasColumn('websites', 'content_updated_at')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn('content_updated_at');
            });
        }

        if (Schema::hasColumn('websites', 'deployed_version')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn('deployed_version');
            });
        }

        if (Schema::hasColumn('websites', 'content_version')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn('content_version');
            });
        }
    }
};