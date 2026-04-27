<?php

namespace App\Providers;

use App\Enums\MessageTypeEnum;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Support\ActivityLogStatus;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        RedirectResponse::macro('withMessage', function (string $message, ?string $messageTitle = null, ?MessageTypeEnum $messageType = MessageTypeEnum::success) {
            Session::flash('messageType', $messageType->value);
            Session::flash('messageTitle', $messageTitle);
            Session::flash('message', $message);

            return $this;
        });

        Blueprint::macro('dropForeignSafe', function ($args) {
            if (app()->runningUnitTests() && \DB::getDriverName() === 'sqlite') {
                // Do nothing
                /** @see Blueprint::ensureCommandsAreValid */
            } else {
                $this->dropForeign($args);
            }
        });

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict();

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // opcodesio/log-viewer authorization gate. Same super_admin bar as
        // Telescope — these are both diagnostics surfaces, not for content
        // creators or other limited roles.
        Gate::define('viewLogViewer', fn ($user) => $user?->hasRole('super_admin') ?? false);

        // If the activity_log table doesn't exist (fresh checkout, migrations
        // not yet run), short-circuit Spatie's logger globally. Otherwise every
        // model save with LogsAdminActivity tries to insert into a missing
        // table and crashes the request. Wrapped in try/catch so the boot
        // path never blocks startup if Schema::hasTable itself fails.
        try {
            if (! Schema::hasTable('activity_log')) {
                $this->app->make(ActivityLogStatus::class)->disable();
            }
        } catch (Throwable) {
            // DB unreachable — nothing to do here, leave logger in default state.
        }
    }
}
