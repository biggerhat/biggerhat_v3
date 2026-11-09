<?php

use App\Http\Controllers\Cooperative\CooperativeController;
use Illuminate\Support\Facades\Route;

/** M4E Cooperative Mode (Index of the Untold) public reference pages. Admin catalogs live in routes/admin.php. */
Route::get('/cooperative', [CooperativeController::class, 'index'])->name('cooperative.index');
Route::get('/cooperative/encounters/{slug}', [CooperativeController::class, 'encounter'])->name('cooperative.encounter');
