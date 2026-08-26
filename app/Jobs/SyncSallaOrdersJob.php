<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SallaOrderSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncSallaOrdersJob implements ShouldQueue, ShouldBeUnique // <-- “Do not queue another instance of this job when an equivalent one is already waiting or running.”
{
    use Queueable;

    // Retry temporary API or network failures.
    public int $tries = 3;

    // Keep this below the database queue's retry-after period.
    public int $timeout = 60;

    // Prevent duplicate order-sync jobs for ten minutes.
    public int $uniqueFor = 600;

    /**
     * Store only the local user ID in the queued payload.
     */
    public function __construct(
        public readonly int $userId,
    ) {
    }

    /**
     * Execute the order synchronization in a queue worker.
     */
    public function handle(
        SallaOrderSyncService $syncService,
    ): void {
        // Confirm that this user still has Salla authorization.
        $user = User::query()
            ->whereHas('token')
            ->findOrFail($this->userId);

        $syncedOrders = $syncService->sync($user);

        // Log counts only—never credentials or customer payloads.
        Log::info('Salla order synchronization completed.', [
            'user_id' => $user->id,
            'orders_synced' => $syncedOrders,
        ]);
    }

    /**
     * Wait progressively longer after each failed attempt.
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    /**
     * Allow only one order-sync job per authorized user.
     */
    public function uniqueId(): string
    {
        return 'salla-orders-user-' . $this->userId;
    }
}