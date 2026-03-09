<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Make job_id nullable in usage_logs — not all API requests are render jobs.
     */
    public function up(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
            $table->uuid('job_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->uuid('job_id')->nullable(false)->change();
            $table->foreign('job_id')->references('id')->on('render_jobs');
        });
    }
};
