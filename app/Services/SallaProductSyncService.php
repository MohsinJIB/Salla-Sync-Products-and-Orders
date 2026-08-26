<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use RuntimeException;
use UnexpectedValueException;

class SallaProductSyncService
{
    private const PRODUCTS_ENDPOINT =
        'https://api.salla.dev/admin/v2/products';

    private const PER_PAGE = 60;

    public function __construct(
        private readonly SallaAuthService $salla,
    ) {
    }

    /**
     * Synchronize every product belonging to one merchant.
     */
    public function sync(User $user): int
    {
        $merchantId = (int) $user->token?->merchant;

        if ($merchantId <= 0) {
            throw new RuntimeException(
                'The user does not have an authorized Salla merchant token.'
            );
        }

        $this->salla->forUser($user);

        $page = 1;
        $syncedProducts = 0;

        do {
            // Request the maximum page size to reduce API calls.
            $response = $this->salla->request(
                'GET',
                self::PRODUCTS_ENDPOINT.'?'.http_build_query([
                    'page' => $page,
                    'per_page' => self::PER_PAGE,
                ])
            );

            if (($response['success'] ?? false) !== true) {
                throw new RuntimeException(
                    'Salla returned an unsuccessful products response.'
                );
            }

            foreach ($response['data'] ?? [] as $product) {
                $this->upsertProduct($merchantId, $product);
                $syncedProducts++;
            }

            // Advance sequentially until the final API page.
            $pagination = $response['pagination'] ?? [];
            $currentPage = (int) ($pagination['currentPage'] ?? $page);
            $totalPages = (int) (
                $pagination['totalPages'] ?? $currentPage
            );
            $page = $currentPage + 1;
        } while ($currentPage < $totalPages);

        return $syncedProducts;
    }

    /**
     * Create or update one locally stored Salla product.
     */
    private function upsertProduct(
        int $merchantId,
        array $product,
    ): void {
        if (! isset($product['id'], $product['name'])) {
            throw new UnexpectedValueException(
                'A Salla product is missing its ID or name.'
            );
        }

        // The composite key prevents duplicates on repeated syncs.
        Product::query()->updateOrCreate(
            [
                'merchant_id' => $merchantId,
                'salla_id' => (int) $product['id'],
            ],
            [
                'name' => (string) $product['name'],
                'sku' => filled($product['sku'] ?? null)
                    ? (string) $product['sku']
                    : null,
                'type' => $product['type'] ?? null,
                'status' => $product['status'] ?? null,
                'price' => data_get($product, 'price.amount'),
                'currency' => data_get($product, 'price.currency'),
                'quantity' => $product['quantity'] ?? null,
                'is_available' => (bool) (
                    $product['is_available'] ?? false
                ),

                // Retain fields that are not stored in dedicated columns.
                'payload' => $product,
                'synced_at' => now(),
            ],
        );
    }
}