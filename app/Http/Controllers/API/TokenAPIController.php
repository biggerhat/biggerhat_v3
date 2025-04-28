<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Token;
use Illuminate\Http\Request;

class TokenAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        return Token::where('name', 'LIKE', "%{$name}%")->get();
    }
}
