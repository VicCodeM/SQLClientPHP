<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    /**
     * Display the main SQL Studio workspace.
     */
    public function index(Request $request): Response
    {
        $connections = Connection::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('Studio/Index', [
            'connections' => $connections,
            'activeConnectionId' => $connections->first()?->id,
        ]);
    }
}
