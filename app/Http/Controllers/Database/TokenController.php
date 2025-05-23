<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Http\Resources\TokenResource;
use App\Models\Token;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function index(Request $request)
    {
        $tokens = Token::orderBy('name', 'ASC')->get();

        return inertia('Tokens/Index', [
            'tokens' => TokenResource::collection($tokens)->toArray($request),
        ]);
    }
}
