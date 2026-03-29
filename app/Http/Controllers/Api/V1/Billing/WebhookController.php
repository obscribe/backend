<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function stripe(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'invoice.paid' => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => null,
        };

        return response('OK', 200);
    }

    protected function handleCheckoutCompleted(object $session): void
    {
        $userId = $session->metadata->user_id ?? $session->client_reference_id ?? null;
        $planId = $session->metadata->plan_id ?? null;

        if (!$userId || !$planId) {
            Log::warning('Stripe checkout.session.completed missing metadata', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $user = User::find($userId);
        $plan = Plan::find($planId);

        if (!$user || !$plan) {
            return;
        }

        $expiresAt = $plan->interval === 'yearly'
            ? now()->addDays(365)
            : now()->addDays(30);

        // Expire any existing active subscription
        $user->subscriptions()
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'stripe',
            'provider_subscription_id' => $session->subscription ?? null,
            'starts_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount_cents' => $session->amount_total ?? $plan->price_cents,
            'currency' => $plan->currency,
            'payment_provider' => 'stripe',
            'provider_payment_id' => $session->id,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Upgrade user tier
        $user->update(['tier' => 'pro']);
    }

    protected function handleInvoicePaid(object $invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (!$stripeSubId) {
            return;
        }

        $subscription = Subscription::where('provider_subscription_id', $stripeSubId)
            ->where('payment_provider', 'stripe')
            ->latest()
            ->first();

        if (!$subscription) {
            return;
        }

        $plan = $subscription->plan;

        $newExpiry = $plan->interval === 'yearly'
            ? $subscription->expires_at->addDays(365)
            : $subscription->expires_at->addDays(30);

        $subscription->renew($newExpiry);

        Payment::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount_cents' => $invoice->amount_paid ?? $plan->price_cents,
            'currency' => $plan->currency,
            'payment_provider' => 'stripe',
            'provider_payment_id' => $invoice->id,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $subscription->user->update(['tier' => 'pro']);
    }

    protected function handleInvoicePaymentFailed(object $invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (!$stripeSubId) {
            return;
        }

        Subscription::where('provider_subscription_id', $stripeSubId)
            ->where('payment_provider', 'stripe')
            ->where('status', 'active')
            ->update(['status' => 'past_due']);
    }

    protected function handleSubscriptionDeleted(object $stripeSub): void
    {
        $subscription = Subscription::where('provider_subscription_id', $stripeSub->id)
            ->where('payment_provider', 'stripe')
            ->latest()
            ->first();

        if ($subscription) {
            $subscription->update(['status' => 'expired']);
            // Downgrade user if no other active subscriptions
            if (!$subscription->user->subscriptions()->active()->exists()) {
                $subscription->user->update(['tier' => 'free']);
            }
        }
    }

    public function plisio(Request $request): Response
    {
        // Verify Plisio webhook
        $data = $request->all();
        $secret = config('services.plisio.webhook_secret');

        if ($secret) {
            $receivedHash = $request->header('Hash') ?? ($data['verify_hash'] ?? null);
            unset($data['verify_hash']);
            ksort($data);
            $expectedHash = hash_hmac('sha1', json_encode($data, JSON_UNESCAPED_UNICODE), $secret);

            if (!hash_equals($expectedHash, $receivedHash ?? '')) {
                Log::warning('Plisio webhook verification failed');
                return response('Invalid signature', 400);
            }
        }

        $status = $data['status'] ?? null;

        if (!in_array($status, ['completed', 'confirmed'])) {
            // Not a completed payment, acknowledge and move on
            return response('OK', 200);
        }

        $extra = json_decode($data['extra'] ?? '{}', true);
        $userId = $extra['user_id'] ?? null;
        $planId = $extra['plan_id'] ?? null;

        if (!$userId || !$planId) {
            Log::warning('Plisio webhook missing extra data', $data);
            return response('OK', 200);
        }

        $user = User::find($userId);
        $plan = Plan::find($planId);

        if (!$user || !$plan) {
            return response('OK', 200);
        }

        // Prevent duplicate processing
        $txnId = $data['txn_id'] ?? $data['order_number'] ?? uniqid('plisio_');
        if (Payment::where('provider_payment_id', $txnId)->where('status', 'completed')->exists()) {
            return response('OK', 200);
        }

        $expiresAt = $plan->interval === 'yearly'
            ? now()->addDays(365)
            : now()->addDays(30);

        // Expire existing active subscriptions
        $user->subscriptions()
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'plisio',
            'starts_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'payment_provider' => 'plisio',
            'provider_payment_id' => $txnId,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $user->update(['tier' => 'pro']);

        return response('OK', 200);
    }
}
