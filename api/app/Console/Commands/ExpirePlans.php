<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Command;

class ExpirePlans extends Command
{
    protected $signature = 'plans:expire';
    protected $description = 'Downgrade users with expired plans back to Free';

    public function handle(): int
    {
        $freePlan = Plan::where('slug', 'free')->first();

        if (!$freePlan) {
            $this->error('Free plan not found!');
            return 1;
        }

        $expired = User::whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now())
            ->where('plan_id', '!=', $freePlan->id)
            ->get();

        foreach ($expired as $user) {
            $oldPlan = $user->plan?->name ?? 'Unknown';
            $user->update([
                'plan_id' => $freePlan->id,
                'plan_expires_at' => null,
            ]);
            $this->info("Downgraded {$user->name} ({$user->email}) from {$oldPlan} → Free");
        }

        $this->info("Done. {$expired->count()} user(s) downgraded.");
        return 0;
    }
}
