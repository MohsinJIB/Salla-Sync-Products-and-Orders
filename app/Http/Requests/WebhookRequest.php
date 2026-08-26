<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Secret shared privately between Salla and our application.
        $secret = (string) config('services.salla.webhook_secret');

        // Signature generated and sent by Salla.
        $signature = (string) $this->header('X-Salla-Signature');

        // Never accept unsigned requests or an unconfigured application.
        if ($secret === '' || $signature === '') {
            return false;
        }

        // Recreate the signature using the exact raw request body.
        $expected = hash_hmac(
            'sha256',
            $this->getContent(),
            $secret
        );

        // Compare securely to avoid timing-based attacks.
        return hash_equals($expected, $signature);
    }

    protected function failedAuthorization(): void
    {
        // Tell Salla that webhook authentication failed.
        throw new HttpResponseException(response('', 401));
    }

    public function rules(): array
    {
        return [
            // The Salla event name, such as app.store.authorize.
            'event' => ['required', 'string'],

            // The store ID is at the top level of the webhook.
            'merchant' => ['required', 'integer'],

            // Must exist, but may be empty for some lifecycle events.
            'data' => ['present', 'array'],
        ];
    }
}