<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['slug' => 'pro-monthly'],
            [
                'name' => 'Pro',
                'description' => 'Unlimited notebooks, pages, and premium features.',
                'price_cents' => 500,
                'currency' => 'usd',
                'interval' => 'monthly',
                'features' => [
                    'Unlimited notebooks',
                    'Unlimited pages per notebook',
                    'Priority support',
                    'Early access to new features',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'pro-yearly'],
            [
                'name' => 'Pro',
                'description' => 'Unlimited notebooks, pages, and premium features. Save 20%!',
                'price_cents' => 4800,
                'currency' => 'usd',
                'interval' => 'yearly',
                'features' => [
                    'Unlimited notebooks',
                    'Unlimited pages per notebook',
                    'Priority support',
                    'Early access to new features',
                    'Save 20% vs monthly',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
    }
}
