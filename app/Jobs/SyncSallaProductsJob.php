<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SallaProductSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncSallaProductsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    // Retry temporary API or network failures.
    public int $tries = 3;

    // Keep this below the database queue's retry-after period.
    public int $timeout = 60;

    // Prevent duplicate product-sync jobs for ten minutes.
    public int $uniqueFor = 600;
    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $userId,)
    {
        //
    }

    /**
     * Execute the job. Execute the product synchronization in a queue worker.
     */
    public function handle(SallaProductSyncService $syncService,
    ): void {
        $user = User::query()
            ->whereHas('token')
            ->findOrFail($this->userId);

        $syncedProducts = $syncService->sync($user);

        // Record useful evidence without logging credentials or payloads.
        Log::info('Salla product synchronization completed.', [
            'user_id' => $user->id,
            'products_synced' => $syncedProducts,
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
     * Allow only one product-sync job per authorized user.
     */
    public function uniqueId(): string
    {
        return 'salla-products-user-'.$this->userId;
    }
}