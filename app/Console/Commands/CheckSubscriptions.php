<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'obscribe:check-subscriptions';
    protected $description = 'Expire overdue subscriptions and flag expiring-soon ones';

    public function handle(): int
    {
        // Expire overdue subscriptions
        $expired = Subscription::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            // Downgrade user if no other active subscriptions
            if (!$subscription->user->subscriptions()->active()->exists()) {
                $subscription->user->update(['tier' => 'free']);
            }

            $this->info("Expired subscription #{$subscription->id} for user #{$subscription->user_id}");
        }

        // Also expire cancelled subscriptions past their expiry
        Subscription::where('status', 'cancelled')
            ->where('expires_at', '<', now())
            ->each(function ($subscription) {
                $subscription->update(['status' => 'expired']);
                if (!$subscription->user->subscriptions()->active()->exists()) {
                    $subscription->user->update(['tier' => 'free']);
                }
                $this->info("Expired cancelled subscription #{$subscription->id} for user #{$subscription->user_id}");
            });

        // Log expiring-soon (within 7 days) for future notification use
        $expiringSoon = Subscription::expiringSoon()->count();
        if ($expiringSoon > 0) {
            $this->info("{$expiringSoon} subscription(s) expiring within 7 days.");
        }

        $this->info('Subscription check complete.');

        return self::SUCCESS;
    }
}
