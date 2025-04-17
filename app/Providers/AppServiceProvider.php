<?php

namespace App\Providers;

use App\Classes\Delivery;
use App\Classes\Optimizer\Optimize;
use App\Models\Media;
use App\Models\User;
use App\Models\Version;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('optimize', fn(): Optimize => new Optimize());
        $this->app->singleton('delivery', fn(): Delivery => new Delivery());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
        ]);

        $this->configureRateLimiting();

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
