<?php

namespace App\Http\Middleware;

use App\Search\AlertService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly AlertService $alerts) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            'flash' => $request->session()->get('status'),
            'unreadAlertsCount' => $this->unreadAlertsCount($request),
        ];
    }

    /**
     * Shared so the nav badge is correct on every page rather than only the one
     * that happens to query for it.
     */
    private function unreadAlertsCount(Request $request): int
    {
        $user = $request->user();

        return $user === null ? 0 : $this->alerts->unreadCountFor($user);
    }
}
