<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'applications' => $request->user()
                ->applications()
                ->where('enabled', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
