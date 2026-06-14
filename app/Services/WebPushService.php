<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private ?WebPush $webPush = null;

    public function __construct()
    {
        $publicKey = config('webpush.vapid.public_key');
        $privateKey = config('webpush.vapid.private_key');

        if ($publicKey && $privateKey) {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
        }
    }

    /**
     * Send a push notification to a single subscription.
     *
     * @param  array<string, mixed>  $payload
     * @return 'ok'|'expired'|'error'
     */
    public function send(PushSubscription $subscription, array $payload): string
    {
        if (! isset($this->webPush)) {
            return 'error';
        }

        $sub = Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding ?? 'aesgcm',
        ]);

        $report = $this->webPush->sendOneNotification($sub, json_encode($payload));

        if ($report->isSuccess()) {
            return 'ok';
        }

        if (in_array($report->getResponseStatusCode(), [404, 410], strict: true)) {
            return 'expired';
        }

        return 'error';
    }
}
