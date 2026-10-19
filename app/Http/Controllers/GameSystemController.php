<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameSystemSwitchRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class GameSystemController extends Controller
{
    private const COOKIE = 'preferred_game_system';

    public function switch(GameSystemSwitchRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
