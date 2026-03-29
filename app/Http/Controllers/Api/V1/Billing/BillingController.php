<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        if (!$subscription) {
            return response()->json([
                'subscribed' => false,
                'subscription' => null,
                'plan' => null,
            ]);
        }

        return response()->json([
            'subscribed' => true,
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'payment_provider' => $subscription->payment_provider,
                'starts_at' => $subscription->starts_at->toISOString(),
                'expires_at' => $subscription->expires_at->toISOString(),
                'cancelled_at' => $subscription->cancelled_at?->toISOString(),
                'is_active' => $subscription->isActive(),
            ],
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'formatted_price' => $subscription->plan->formatted_price,
                'interval' => $subscription->plan->interval,
            ],
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->with('plan:id,name,slug')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($payments);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription();

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription to cancel.'], 404);
        }

        $subscription->cancel();

        // Don't downgrade tier yet — they keep access until expires_at
        return response()->json([
            'message' => 'Subscription cancelled. You will retain access until ' . $subscription->expires_at->toDateString() . '.',
            'expires_at' => $subscription->expires_at->toISOString(),
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        $subscription = $request->user()
            ->subscription()
            ->where('status', 'cancelled')
            ->where('expires_at', '>', now())
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No cancelled subscription to resume.'], 404);
        }

        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
        ]);

        return response()->json([
            'message' => 'Subscription resumed.',
        ]);
    }
}
