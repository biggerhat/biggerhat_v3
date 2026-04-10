<?php

namespace App\Http\Controllers;

use Inertia\Response;
use Inertia\ResponseFactory;

class CompareController extends Controller
{
    public function index(): Response|ResponseFactory
    {
        return inertia('Compare/Index');
    }
}
