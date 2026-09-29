<?php

namespace App\Http\Controllers;

use App\Models\WebhookInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $paymentId = $request->query('data.id') ?? $request->query('data_id') ?? $request->input('data.id');
        if (! is_scalar($paymentId) || ! preg_match('/^[a-zA-Z0-9_-]{1,100}$/', (string) $paymentId)) {
            return response()->json(['status' => 'invalid'], 422);
        }

        $secret = config('services.payment.webhook_secret');
        if ($secret) {
            $parts = [];
            foreach (explode(',', (string) $request->header('x-signature')) as $part) {
                $pair = explode('=', trim($part), 2);
                if (count($pair) === 2) {
                    $parts[$pair[0]] = $pair[1];
                }
            }

            $requestId = $request->header('x-request-id');
            if (! $requestId || ! ctype_digit($parts['ts'] ?? '') || ! preg_match('/^[a-f0-9]{64}$/i', $parts['v1'] ?? '')) {
                return response()->json(['status' => 'unauthorized'], 401);
            }

            $manifest = 'id:'.strtolower((string) $paymentId).';request-id:'.$requestId.';ts:'.$parts['ts'].';';
            if (! hash_equals(hash_hmac('sha256', $manifest, $secret), strtolower($parts['v1']))) {
                return response()->json(['status' => 'unauthorized'], 401);
            }
        } elseif (app()->environment('production')) {
            return response()->json(['status' => 'unavailable'], 503);
        }

        WebhookInbox::create([
            'provider' => 'mercadopago',
            'payload' => ['data' => ['id' => (string) $paymentId]],
            'status' => 'pending',
        ]);

        return response()->json(['status' => 'received']);
    }
}
