<?php

namespace App\Providers;

use App\Enums\MessageTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

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
            if (app()->runningUnitTests() && in_array(env('DB_CONNECTION'), ['sqlite', ':memory:'])) {
                // Do nothing
                /** @see Blueprint::ensureCommandsAreValid */
            } else {
                $this->dropForeign($args);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict();
    }
}
