<?php

namespace App\Http\Controllers;

use App\Models\Miniature;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PDFController extends Controller
{
    public function index(Request $request)
    {
        dd('Test');
    }

    public function download(Request $request)
    {
        $miniature = Miniature::first();

        $pdf = Pdf::loadHTML('<h1>Test</h1>');

        return $pdf->stream('test.pdf');
    }
}
