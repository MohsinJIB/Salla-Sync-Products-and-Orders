<?php

namespace App\Actions\App;

use App\Actions\BaseAction;
use App\Models\User;
use App\Services\SallaAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use League\OAuth2\Client\Token\AccessToken;

/**
 * @property string $merchant
 * @property string $created_at
 * @property string $event
 * @property array $data
 */
class StoreAuthorize extends BaseAction // handles the app.store.authorize event
{
    public function handle(): void
    {
        /** @var SallaAuthService $service */
        $service = app('salla.auth');

        if (! $service->isEasyMode()) {
            return;
        }

        $storeDetails = $service->getResourceOwner(new AccessToken($this->data));
        // Use the store name when the demo store has no owner name.
        $merchantName = $storeDetails->getStoreOwnerName()
            ?: $storeDetails->getStoreName()
            ?: 'Salla Merchant';
        $user = User::query()->firstOrCreate([
            'email' => $storeDetails->getEmail(),
        ], [
            'name' => $merchantName,
            'password' => Hash::make(Str::random()),
        ]);

        $user->token()->updateOrCreate([], [ //Saves the merchant ID, access token, refresh token, and expiry in oauth_tokens
            'merchant' => $storeDetails->getStoreId(),
            'access_token' => $this->data['access_token'],
            'expires_in' => $this->data['expires'],
            'refresh_token' => $this->data['refresh_token'],
        ]);
    }
}
