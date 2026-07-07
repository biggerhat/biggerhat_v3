<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Public thank-you page for Ko-fi supporters. Only lists users holding the
 * Supporter role who have opted in via Settings > Profile
 * (show_on_supporters_page) — the role alone does not make someone public.
 */
class SupportersController extends Controller
{
    public function index(): Response|ResponseFactory
    {
        return inertia('Supporters/Index', [
            'supporters' => User::role('supporter')
                ->where('show_on_supporters_page', true)
                ->orderByDesc('supporter_since')
                ->get(['id', 'name', 'supporter_since'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'supporter_since' => $user->supporter_since?->format('M Y'),
                ]),
        ]);
    }
}
