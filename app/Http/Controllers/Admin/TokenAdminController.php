<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Tokens/Index', [
            'tokens' => Token::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Tokens/TokenForm');
    }

    public function edit(Request $request, Token $token)
    {
        return inertia('Admin/Tokens/TokenForm', [
            'token' => $token,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $token = Token::create($validated);

        return redirect()->route('admin.tokens.index')->withMessage("{$token->name} created successfully.");
    }

    public function update(Request $request, Token $token)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $token->update($validated);

        return redirect()->route('admin.tokens.index')->withMessage("{$token->name} has been updated.");
    }

    public function delete(Request $request, Token $token)
    {
        $name = $token->name;
        $token->delete();

        return redirect()->route('admin.tokens.index')->withMessage("{$name} has been deleted.");
    }
}
