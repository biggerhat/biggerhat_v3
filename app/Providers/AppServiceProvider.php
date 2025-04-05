<?php

namespace App\Providers;

use App\Enums\MessageTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        RedirectResponse::macro('withMessage', function (string $message, ?MessageTypeEnum $messageType = MessageTypeEnum::success) {
            return $this->with('flash', [
                'messageStyle' => $messageType->value,
                'message' => $message,
            ]);
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
