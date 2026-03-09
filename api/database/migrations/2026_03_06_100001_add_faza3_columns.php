<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add category and preview_url to templates table.
     * Add tracking columns to usage_logs for middleware-based tracking.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('preview_url', 500)->nullable()->after('thumbnail_path');
            $table->string('category', 100)->default('general')->after('description');
        });

        // Add tracking columns to usage_logs
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('api_key_id')->nullable()->after('user_id');
            $table->string('endpoint', 255)->nullable()->after('api_key_id');
            $table->string('method', 10)->nullable()->after('endpoint');
            $table->unsignedSmallInteger('status_code')->nullable()->after('method');
            $table->string('ip_address', 45)->nullable()->after('status_code');
            $table->string('user_agent', 500)->nullable()->after('ip_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['preview_url', 'category']);
        });

        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropColumn(['api_key_id', 'endpoint', 'method', 'status_code', 'ip_address', 'user_agent']);
        });
    }
};
