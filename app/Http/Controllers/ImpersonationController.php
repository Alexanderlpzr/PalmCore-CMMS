<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $service) {}

    /**
     * Begin impersonating the given user. Authorization (Super Admin only, never
     * another Super Admin, etc.) is enforced inside the service.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $reason = $request->string('reason')->trim()->toString();

        $this->service->start($request->user(), $user, $reason !== '' ? $reason : null, $request);

        return redirect('/admin');
    }

    /**
     * Exit impersonation and restore the original Super Admin session.
     */
    public function leave(Request $request): RedirectResponse
    {
        $this->service->stop($request);

        return redirect('/admin');
    }
}
