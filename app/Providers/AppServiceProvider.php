<?php

namespace App\Providers;

use App\Models\WhatsAppAccount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->ensurePublicStorageLink();

        Route::bind('whatsappAccount', function (string $value) {
            if (request()->is('api/*/webhooks/*')) {
                return WhatsAppAccount::withoutGlobalScopes()
                    ->withTrashed()
                    ->findOrFail($value);
            }

            return WhatsAppAccount::query()->findOrFail($value);
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(180)->by($request->ip());
        });
    }

    protected function ensurePublicStorageLink(): void
    {
        $link = public_path('storage');

        if (is_link($link) || file_exists($link)) {
            return;
        }

        if (is_dir(storage_path('app/public'))) {
            Artisan::call('storage:link');
        }
    }
}
