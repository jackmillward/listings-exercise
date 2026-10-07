<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use App\Search\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function __construct(private readonly AlertService $alerts) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection($this->alerts->forUser($this->user($request))),
        ]);
    }

    /**
     * Deliberately not mark-on-click-through: browsing listings would quietly
     * consume alerts the user never looked at.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $this->alerts->markAllReadForUser($this->user($request));

        return back()->with('status', 'Alerts marked as read');
    }
}
