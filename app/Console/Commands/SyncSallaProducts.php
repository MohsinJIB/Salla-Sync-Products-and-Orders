<?php

namespace App\Console\Commands;

use App\Jobs\SyncSallaProductsJob;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('salla:sync-products {--user= : Sync one authorized local user ID}')]
#[Description('Queue Salla product synchronization for authorized merchants.')]
class SyncSallaProducts extends Command
{
    public function handle(): int
    {
        $userId = $this->option('user');

        // Reject an invalid user ID before querying the database.
        if ($userId !== null && ! ctype_digit((string) $userId)) {
            $this->error('The --user option must be a numeric local user ID.');

            return self::FAILURE;
        }

        // Select users who have stored Salla authorization credentials.
        $users = User::query()
            ->whereHas('token')
            ->when(
                $userId !== null,
                fn ($query) => $query->whereKey((int) $userId),
            )
            ->get(['id']);

        if ($users->isEmpty()) {
            $this->error('No authorized Salla users were found.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            // Request one unique background sync job per merchant.
            SyncSallaProductsJob::dispatch($user->id);

            $this->line("Requested product sync for user #{$user->id}.");
        }

        // Display counts without exposing access or refresh tokens.
        $this->info("Requested {$users->count()} product sync job(s).");

        return self::SUCCESS;
    }
}