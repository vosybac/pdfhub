<?php

namespace App\Http\Controllers;

use App\Services\NetworkService;

class DashboardController extends Controller
{
    public function index()
    {
        $service = app(NetworkService::class);
        $graph = $service->graphData();
        $stats = $service->stats();

        return view('dashboard.index', compact('graph', 'stats'));
    }

    public function network()
    {
        $service = app(NetworkService::class);

        return response()->json($service->graphData());
    }
}
