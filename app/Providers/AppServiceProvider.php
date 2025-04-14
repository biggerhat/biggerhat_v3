<?php

namespace App\Providers;

use App\Enums\MessageTypeEnum;
use Illuminate\Database\Eloquent\Model;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict();
    }
}
