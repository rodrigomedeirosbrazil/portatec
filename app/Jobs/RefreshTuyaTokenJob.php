<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TuyaAccount;
use App\Services\Tuya\TuyaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshTuyaTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TuyaService $tuyaService): void
    {
        $accounts = TuyaAccount::query()
            ->where('active', true)
            ->where('expires_at', '<', now()->addMinutes(5))
            ->get();

        foreach ($accounts as $account) {
            try {
                if ($tuyaService->refreshToken($account)) {
                    Log::info('Tuya token refreshed', ['tuya_account_id' => $account->id]);
                } else {
                    Log::warning('Tuya token refresh failed', ['tuya_account_id' => $account->id]);
                }
            } catch (\Throwable $e) {
                Log::error('Tuya token refresh error', [
                    'tuya_account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
