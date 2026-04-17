<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackCategoryEnum;
use App\Enums\FeedbackStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Admin inbox for user-submitted feedback. Feedback is store-only for
 * users — admins can triage status, add private notes, or delete.
 */
class FeedbackAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        $status = $request->query('status');
        $category = $request->query('category');

        $query = Feedback::with('user:id,name,email')
            ->latest();

        if ($status && FeedbackStatusEnum::tryFrom($status)) {
            $query->where('status', $status);
        }

        if ($category && FeedbackCategoryEnum::tryFrom($category)) {
            $query->where('category', $category);
        }

        $entries = $query->paginate(30)->withQueryString();

        // Unfiltered counts per status so the filter pills can show badges.
        $counts = Feedback::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return inertia('Admin/Feedback/Index', [
            'entries' => $entries,
            'filters' => [
                'status' => $status,
                'category' => $category,
            ],
            'counts' => [
                'new' => (int) ($counts['new'] ?? 0),
                'read' => (int) ($counts['read'] ?? 0),
                'archived' => (int) ($counts['archived'] ?? 0),
            ],
            'statuses' => collect(FeedbackStatusEnum::cases())->map(fn (FeedbackStatusEnum $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'categories' => collect(FeedbackCategoryEnum::cases())->map(fn (FeedbackCategoryEnum $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(FeedbackStatusEnum::class)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $feedback->update($validated);

        return back();
    }

    public function destroy(Feedback $feedback): RedirectResponse
    {
        $feedback->delete();

        return back()->withMessage('Feedback deleted.');
    }
}
