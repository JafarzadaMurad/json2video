<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plans table (dynamic pricing from admin)
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->unsignedInteger('max_render_minutes')->nullable(); // NULL = unlimited
            $table->unsignedInteger('max_video_duration')->nullable(); // max seconds per video
            $table->string('max_resolution', 20)->default('full-hd');
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->boolean('has_watermark')->default(false);
            $table->boolean('has_priority_queue')->default(false);
            $table->boolean('has_webhook')->default(true);
            $table->boolean('has_templates')->default(true);
            $table->unsignedInteger('storage_days')->default(3);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Add plan_id to users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('password')
                  ->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
        });
        Schema::dropIfExists('plans');
    }
};
