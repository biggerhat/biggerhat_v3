<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackCategoryEnum;
use App\Enums\FeedbackStatusEnum;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Public feedback inbox. Anyone can submit — logged-in users get their
 * name/email prefilled and linked to the record; anonymous users can leave
 * the contact fields empty.
 *
 * The page the user was on when submitting is captured from the Referer
 * header so admins can reproduce bugs without asking follow-up questions.
 * A honeypot field + per-IP throttle keep drive-by spam off the inbox.
 */
class FeedbackController extends Controller
{
    public function show(): Response|ResponseFactory
    {
        return inertia('Feedback/Create', [
            'categories' => collect(FeedbackCategoryEnum::cases())->map(fn (FeedbackCategoryEnum $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'category' => ['required', Rule::enum(FeedbackCategoryEnum::class)],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot — bots typically fill every field. Humans see nothing
            // (the field is hidden via CSS in the Vue template).
            'website' => ['nullable', 'size:0'],
        ]);

        $user = Auth::user();

        Feedback::create([
            'user_id' => $user?->id,
            'name' => $validated['name'] ?? $user?->name,
            'email' => $validated['email'] ?? $user?->email,
            'category' => $validated['category'],
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'url' => $request->headers->get('referer'),
            'status' => FeedbackStatusEnum::New,
            'submitter_ip' => $request->ip(),
        ]);

        return redirect()->route('feedback.show')->withMessage("Thanks — we've got it. We'll follow up if needed.");
    }
}
