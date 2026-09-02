<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\VisitStatsService;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private readonly VisitStatsService $visitStats)
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $stats = $this->visitStats->summary();
        $stats['clients'] = Client::withCount('visits')->get();

        return view('admin.dashboard', compact('stats'));
    }
}