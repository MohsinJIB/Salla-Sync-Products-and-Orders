<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;
use UnexpectedValueException;

class SallaOrderSyncService
{
    private const ORDERS_ENDPOINT =
        'https://api.salla.dev/admin/v2/orders';

    // The Orders endpoint recommends a maximum page size of 30.
    private const PER_PAGE = 30;

    public function __construct(
        private readonly SallaAuthService $salla,
    ) {
    }

    /**
     * Synchronize every order belonging to one merchant.
     */
    public function sync(User $user): int
    {
        $merchantId = (int) $user->token?->merchant;

        if ($merchantId <= 0) {
            throw new RuntimeException(
                'The user does not have an authorized Salla merchant token.'
            );
        }

        // Configure authenticated requests for this merchant.
        $this->salla->forUser($user);

        $page = 1;
        $syncedOrders = 0;

        do {
            // Pages must be requested sequentially.
            $response = $this->salla->request(
                'GET',
                self::ORDERS_ENDPOINT . '?' . http_build_query([
                    'page' => $page,
                    'per_page' => self::PER_PAGE,
                ])
            );

            if (($response['success'] ?? false) !== true) {
                throw new RuntimeException(
                    'Salla returned an unsuccessful orders response.'
                );
            }

            foreach ($response['data'] ?? [] as $order) {
                $this->upsertOrder($merchantId, $order);
                $syncedOrders++;
            }

            // Continue until the final response page.
            $pagination = $response['pagination'] ?? [];
            $currentPage = (int) ($pagination['currentPage'] ?? $page);
            $totalPages = (int) (
                $pagination['totalPages'] ?? $currentPage
            );

            $page = $currentPage + 1;
        } while ($currentPage < $totalPages);

        return $syncedOrders;
    }

    /**
     * Create or update one locally stored Salla order.
     */
    private function upsertOrder(
        int $merchantId,
        array $order,
    ): void {
        if (! isset($order['id'])) {
            throw new UnexpectedValueException(
                'A Salla order is missing its ID.'
            );
        }

        $orderedAt = data_get($order, 'date.date');
        $timezone = data_get($order, 'date.timezone') ?: 'UTC';

        // Store dates consistently in UTC.
        $orderedAtUtc = filled($orderedAt)
            ? CarbonImmutable::parse(
                (string) $orderedAt,
                (string) $timezone,
            )->utc()
            : null;

        // The composite key prevents duplicates on repeated syncs.
        Order::query()->updateOrCreate(
            [
                'merchant_id' => $merchantId,
                'salla_id' => (int) $order['id'],
            ],
            [
                'reference_id' => filled($order['reference_id'] ?? null)
                    ? (int) $order['reference_id']
                    : null,

                'customer_id' => filled(
                    data_get($order, 'customer.id')
                )
                    ? (int) data_get($order, 'customer.id')
                    : null,

                'status' => data_get($order, 'status.slug'),

                'payment_method' => filled(
                    $order['payment_method'] ?? null
                )
                    ? (string) $order['payment_method']
                    : null,

                'total' => data_get($order, 'total.amount'),
                'currency' => data_get($order, 'total.currency'),
                'ordered_at' => $orderedAtUtc,

                // Retain fields that do not have dedicated columns.
                'payload' => $order,
                'synced_at' => now(),
            ],
        );
    }
}