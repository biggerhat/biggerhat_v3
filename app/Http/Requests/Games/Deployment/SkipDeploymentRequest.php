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
        return [];
    }
}
