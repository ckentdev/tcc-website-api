<?php

namespace App\Providers;

use App\Services\Assistant\HandbookRetriever;
use App\Services\Assistant\OpenAiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenAiClient::class, function () {
            return OpenAiClient::fromConfig();
        });

        $this->app->singleton(HandbookRetriever::class, function (Application $app) {
            try {
                return new HandbookRetriever($app->make(OpenAiClient::class));
            } catch (RuntimeException) {
                return new HandbookRetriever(null);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers();

            if ($this->app->environment('production')) {
                $rule = $rule->uncompromised();
            }

            return $rule;
        });

        RateLimiter::for('cms-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perHour(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('assistant-follow-up', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('student-auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
