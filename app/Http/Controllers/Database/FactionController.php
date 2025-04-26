<?php

namespace App\Http\Controllers\Database;

use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FactionController extends Controller
{
    public function view(Request $request, FactionEnum $factionEnum)
    {
        dd($factionEnum->label());

    }
}
