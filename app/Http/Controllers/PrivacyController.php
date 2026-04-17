<?php

namespace App\Http\Controllers;

use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Serves the public privacy policy page. Kept thin — all of the policy
 * content lives in the Vue page so non-developers can edit copy without
 * touching PHP.
 */
class PrivacyController extends Controller
{
    public function show(): Response|ResponseFactory
    {
        return inertia('Privacy');
    }
}
