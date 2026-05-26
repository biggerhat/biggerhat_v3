<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreWeeklyEventRequest;
use App\Http\Requests\Admin\Campaign\UpdateWeeklyEventRequest;
use App\Models\Campaign\WeeklyEvent;
use Illuminate\Http\Request;

class WeeklyEventAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/WeeklyEvent/Index', [
            'items' => WeeklyEvent::orderByRaw('is_black_joker DESC, is_red_joker DESC, flip_value ASC')
                ->get(['id', 'name', 'flip_value', 'is_black_joker', 'is_red_joker', 'requires_placement', 'is_one_time']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/WeeklyEvent/Form');
    }

    public function edit(Request $request, WeeklyEvent $weeklyEvent)
    {
        return inertia('Admin/Campaign/WeeklyEvent/Form', ['item' => $weeklyEvent]);
    }

    public function store(StoreWeeklyEventRequest $request)
    {
        $row = WeeklyEvent::create($request->validated());

        return redirect()->route('admin.campaign.weekly-events.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateWeeklyEventRequest $request, WeeklyEvent $weeklyEvent)
    {
        $weeklyEvent->update($request->validated());

        return redirect()->route('admin.campaign.weekly-events.index')->withMessage("{$weeklyEvent->name} updated.");
    }

    public function delete(Request $request, WeeklyEvent $weeklyEvent)
    {
        $name = $weeklyEvent->name;
        $weeklyEvent->delete();

        return redirect()->route('admin.campaign.weekly-events.index')->withMessage("{$name} deleted.");
    }
}
