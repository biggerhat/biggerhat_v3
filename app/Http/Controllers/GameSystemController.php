<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class GameSystemController extends Controller
{
    private const ALLOWED = ['malifaux', 'tos'];

    private const COOKIE = 'preferred_game_system';

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'system' => ['required', 'string', 'in:'.implode(',', self::ALLOWED)],
        ]);

        $system = $validated['system'];

        Cookie::queue(Cookie::make(
            name: self::COOKIE,
            value: $system,
            minutes: 60 * 24 * 365,
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: false,
            sameSite: 'lax',
        ));

        $target = $system === 'tos' ? route('tos.index') : route('index');

        return redirect($target);
    }
}
