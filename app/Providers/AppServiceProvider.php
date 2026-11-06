<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use App\Models\SystemSetting;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\SquarePaymentService;
use App\Services\PushService;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton('system.settings', function () {
            return Cache::rememberForever('system_settings', function () {
                return SystemSetting::first();
            });
        });

        $this->app->singleton(SquarePaymentService::class, function ($app) {
            return new SquarePaymentService();
        });

        // Register PushService for dependency injection
        $this->app->singleton(PushService::class, function ($app) {
            return new PushService();
        });

        // Register FCM Channel
        $this->app->singleton(FcmChannel::class, function ($app) {
            return new FcmChannel($app->make(PushService::class));
        });
    }


    public function boot(): void
    {
        /*
         | Laravel paginates with its Tailwind views by default. This admin is
         | Bootstrap-based and never loads Tailwind, so the paginator's
         | `<svg class="w-5 h-5">` arrows had no size rule at all and rendered
         | at their intrinsic 1082px. Bootstrap markup is already styled by
         | admin-v2.css (.pagination / .page-item / .page-link).
         |
         | Only the admin paginates (customers, setmore reviews), so this is
         | safe to set globally.
         */
        Paginator::useBootstrapFive();

        Mail::alwaysReplyTo('admin@jjsfirewoodperth.com.au', "JJ's Firewood Perth");

        $this->shareSettingsWithViews();

        $this->registerCacheInvalidation();

        // === ORDER CENTRE: Auto audit-log every Order change ===
        // Side-effect observer; never modifies orders.
        Order::observe(OrderObserver::class);
    }


    protected function shareSettingsWithViews(): void
    {
        View::composer('*', function ($view) {
            $settings = Cache::remember('system_settings', 3600, function () {
                return SystemSetting::first();
            });
            $view->with('systemSettings', $settings);
        });
    }


    protected function registerCacheInvalidation(): void
    {
        $clearCache = function ($settings) {
            // Clear cache
            Cache::forget('system_settings');

            if ($this->app->bound('system.settings')) {
                $this->app->forgetInstance('system.settings');
            }
        };

        SystemSetting::created($clearCache);
        SystemSetting::updated($clearCache);
        SystemSetting::deleted($clearCache);
    }
}
