<?php

namespace App\Providers;

use App\Services\AuthService;
use App\Services\BookingService;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\BookingServiceInterface;
use App\Services\Contracts\QueueMonitoringServiceInterface;
use App\Services\Contracts\RoomServiceInterface;
use App\Services\QueueMonitoringService;
use App\Services\RoomService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        BookingServiceInterface::class => BookingService::class,
        AuthServiceInterface::class => AuthService::class,
        RoomServiceInterface::class => RoomService::class,
        QueueMonitoringServiceInterface::class => QueueMonitoringService::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BookingServiceInterface::class, BookingService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(RoomServiceInterface::class, RoomService::class);
        $this->app->bind(QueueMonitoringServiceInterface::class, QueueMonitoringService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mencegah lazy loading saat bukan di environment production
        Model::preventLazyLoading(! app()->isProduction());

        // Audit 496 Parameter 29: RateLimiter untuk endpoint pemesanan rapat (30 request per menit)
        RateLimiter::for('bookings', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
