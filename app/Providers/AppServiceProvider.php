<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Request::macro('validatedExcept', function ($except = []) {
            return Arr::except($this->validated(), $except);
        });

        Gate::define('viewPulse', function (User $user) {
            return true;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid) => $isValid && ! self::expireIdleToken($token)
        );
    }

    /**
     * REQ-016 / REQ-017: delete a token unused for longer than the idle timeout.
     *
     * Compared in whole seconds, because the database keeps timestamps without
     * fractions: exactly 30 minutes is still active, 30 minutes and 1 second is not.
     */
    public static function expireIdleToken(PersonalAccessToken $token): bool
    {
        $minutes = (int) config('sanctum.idle_timeout');
        $lastActivity = $token->last_used_at ?? $token->created_at;

        if ($minutes <= 0 || $lastActivity === null) {
            return false;
        }

        if (now()->getTimestamp() - $lastActivity->getTimestamp() <= $minutes * 60) {
            return false;
        }

        $token->delete();

        return true;
    }
}
