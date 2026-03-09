<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Create default plans ────────────────
        $freePlan = Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'price_monthly' => 0,
            'max_render_minutes' => 10,
            'max_video_duration' => 180, // 3 minutes
            'max_resolution' => 'hd',
            'rate_limit_per_minute' => 30,
            'has_watermark' => true,
            'has_priority_queue' => false,
            'has_webhook' => false,
            'has_templates' => false,
            'storage_days' => 3,
            'sort_order' => 1,
        ]);

        $starterPlan = Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_monthly' => 29,
            'max_render_minutes' => 60,
            'max_video_duration' => 600, // 10 minutes
            'max_resolution' => 'full-hd',
            'rate_limit_per_minute' => 60,
            'has_watermark' => false,
            'has_priority_queue' => false,
            'has_webhook' => true,
            'has_templates' => true,
            'storage_days' => 3,
            'sort_order' => 2,
        ]);

        $proPlan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price_monthly' => 99,
            'max_render_minutes' => 300,
            'max_video_duration' => 1800, // 30 minutes
            'max_resolution' => '4k',
            'rate_limit_per_minute' => 120,
            'has_watermark' => false,
            'has_priority_queue' => true,
            'has_webhook' => true,
            'has_templates' => true,
            'storage_days' => 7,
            'sort_order' => 3,
        ]);

        // ─── Create test user with API key ───────
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@json2video.local',
            'password' => bcrypt('password'),
            'plan_id' => $starterPlan->id,
        ]);

        // Generate a known API key for testing
        $rawKey = 'j2v_test_key_for_development_only_1234';
        ApiKey::create([
            'user_id' => $user->id,
            'key_hash' => hash('sha256', $rawKey),
            'key_prefix' => substr($rawKey, 0, 8),
            'label' => 'Development Key',
            'is_active' => true,
        ]);

        echo "\n  Test API Key: {$rawKey}\n";
        echo "  Use this in X-API-Key header for testing.\n\n";
    }
}
