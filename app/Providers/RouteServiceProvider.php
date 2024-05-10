<?php

namespace App\Providers;

use App\Models\Media;
use App\Models\User;
use App\Models\Version;
use Auth;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('delivery')
                ->group(base_path('routes/delivery.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

        Route::bind('media', function (string $identifier): Media {
            $user = Auth::user() ?? User::whereName(Route::getCurrentRoute()->parameter('user'))->firstOrFail();
            return $user->Media()->whereIdentifier($identifier)->firstOrFail();
        });

        Route::bind('version', function (int $versionNumber): Version {
            $media = Route::getCurrentRoute()->parameter('media');
            return $media->Versions()->whereNumber($versionNumber)->firstOrFail();
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
