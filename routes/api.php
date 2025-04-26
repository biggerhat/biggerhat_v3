<?php

use App\Http\Controllers\API\CharacterAPIController;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/characters', [CharacterAPIController::class, 'find']);
});
