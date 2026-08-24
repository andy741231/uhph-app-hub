<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Round;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $rounds = Round::orderBy('created_at', 'desc')->limit(5)->get();
        $userCounts = [
            'total' => User::count(),
            'submitters' => User::where('role', 'submitter')->count(),
            'reviewers' => User::where('role', 'reviewer')->count(),
            'invited' => User::where('status', 'invited')->count(),
        ];

        return view('admin.dashboard', compact('rounds', 'userCounts'));
    }
}
