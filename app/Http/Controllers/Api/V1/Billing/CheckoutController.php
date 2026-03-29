<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;
use Stripe\BillingPortal\Session as PortalSession;

class CheckoutController extends Controller
{
    public function stripe(Request $request): JsonResponse
    {
        $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::active()->findOrFail($request->plan_id);
        $user = $request->user();

        Stripe::setApiKey(config('services.stripe.secret'));

        $interval = $plan->interval === 'yearly' ? 'year' : 'month';

        $session = StripeSession::create([
            'mode' => 'subscription',
            'customer_email' => $user->email,
            'client_reference_id' => (string) $user->id,
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => $plan->currency,
                    'product_data' => [
                        'name' => "Obscribe {$plan->name} ({$plan->interval})",
                    ],
                    'unit_amount' => $plan->price_cents,
                    'recurring' => [
                        'interval' => $interval,
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => config('app.url') . '/settings/billing?success=true',
            'cancel_url' => config('app.url') . '/settings/billing?cancelled=true',
        ]);

        return response()->json(['url' => $session->url]);
    }

    public function plisio(Request $request): JsonResponse
    {
        $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::active()->findOrFail($request->plan_id);
        $user = $request->user();

        $amount = number_format($plan->price_cents / 100, 2, '.', '');

        $response = Http::get('https://plisio.net/api/v1/invoices/new', [
            'api_key' => config('services.plisio.api_key'),
            'currency' => 'USD',
            'order_name' => "Obscribe {$plan->name} ({$plan->interval})",
            'order_number' => "sub_{$user->id}_{$plan->id}_" . time(),
            'amount' => $amount,
            'source_currency' => 'USD',
            'callback_url' => config('app.url') . '/api/v1/webhooks/plisio',
            'email' => $user->email,
            'success_callback_url' => config('app.url') . '/settings/billing?success=true',
            'cancel_callback_url' => config('app.url') . '/settings/billing?cancelled=true',
            'plugin' => 'Obscribe',
            'extra' => json_encode([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]),
        ]);

        $data = $response->json();

        if ($data['status'] !== 'success') {
            return response()->json([
                'message' => 'Failed to create crypto invoice. Please try again.',
            ], 502);
        }

        return response()->json(['url' => $data['data']['invoice_url']]);
    }

    public function portal(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        if (!$subscription || $subscription->payment_provider !== 'stripe') {
            return response()->json([
                'message' => 'No active Stripe subscription found.',
            ], 404);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        // We need the Stripe customer ID. Look it up from the subscription.
        $stripeSubId = $subscription->provider_subscription_id;

        if (!$stripeSubId) {
            return response()->json([
                'message' => 'No Stripe subscription ID on record.',
            ], 404);
        }

        try {
            $stripeSub = \Stripe\Subscription::retrieve($stripeSubId);
            $session = PortalSession::create([
                'customer' => $stripeSub->customer,
                'return_url' => config('app.url') . '/settings/billing',
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to create billing portal session.',
            ], 502);
        }
    }
}
