<?php

namespace App\Http\Requests\Games\Deployment;

use Illuminate\Foundation\Http\FormRequest;

/** "Start without deploying": the crews are already set up somewhere else. */
class SkipDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        // nothing is needed: the action is the whole request (the optional flag is only there so a client may say it was confirmed)
        return ['confirmed' => ['sometimes', 'boolean']];
    }
}
