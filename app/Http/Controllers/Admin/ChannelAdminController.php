<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChannelRequest;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChannelAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Channels/Index', [
            'channels' => Channel::withCount('transmissions')->with('users:id,name')->orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Channels/ChannelForm', $this->getFormData());
    }

    public function edit(Request $request, Channel $channel)
    {
        $channel->loadMissing('users:id,name');

        return inertia('Admin/Channels/ChannelForm', array_merge(
            ['channel' => $channel],
            $this->getFormData(),
        ));
    }

    public function store(ChannelRequest $request)
    {
        $channel = $this->validateAndSave($request);

        return redirect()->route('admin.channels.index')->withMessage("{$channel->name} created successfully.");
    }

    public function update(ChannelRequest $request, Channel $channel)
    {
        $channel = $this->validateAndSave($request, $channel);

        return redirect()->route('admin.channels.index')->withMessage("{$channel->name} has been updated.");
    }

    public function delete(Request $request, Channel $channel)
    {
        $name = $channel->name;
        $channel->delete();

        return redirect()->route('admin.channels.index')->withMessage("{$name} has been deleted.");
    }

    private function getFormData(): array
    {
        return [
            'users' => fn () => User::orderBy('name')->get()->map(fn (User $u) => [
                'name' => $u->name,
                'value' => (string) $u->id,
            ]),
        ];
    }

    private function validateAndSave(ChannelRequest $request, ?Channel $channel = null): Channel
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            if ($channel && $channel->image) {
                Storage::disk('public')->delete($channel->image);
            }
            $validated['image'] = $request->file('image')->store('channels', 'public');
        } else {
            unset($validated['image']);
        }

        $userIds = $validated['user_ids'] ?? [];
        unset($validated['user_ids']);

        if (! $channel) {
            $channel = Channel::create($validated);
        } else {
            $channel->update($validated);
        }

        $channel->users()->sync($userIds);

        return $channel;
    }
}
