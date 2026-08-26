<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        // Use a known secret without reading the real .env secret.
        config()->set('services.salla.webhook_secret', self::SECRET);
    }

    public function test_it_accepts_a_valid_salla_signature(): void
    { //checks a valid request:
        $payload = $this->webhookPayload();

        // Generate the same signature Salla would send.
        $signature = hash_hmac('sha256', $payload, self::SECRET);

        $response = $this->sendWebhook($payload, $signature);

        $response->assertOk();
    }

    public function test_it_rejects_an_invalid_salla_signature(): void
    { //checks an attacker or broken request:
        $payload = $this->webhookPayload();

        $response = $this->sendWebhook($payload, 'invalid-signature');

        $response->assertStatus(401);
    }

    private function webhookPayload(): string //creates a Salla-shaped webhook
    {
        return json_encode([
            'event' => 'test.event',
            'merchant' => 123456789,
            'data' => [],
        ], JSON_THROW_ON_ERROR);
    }

    private function sendWebhook(string $payload, string $signature)
    {
        // Send the raw JSON so the HMAC is calculated from identical bytes.
        return $this->call(
            'POST',
            '/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SALLA_SIGNATURE' => $signature,
            ],
            $payload
        );
    }
}