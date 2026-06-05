<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function __construct(
        protected Gate $gate,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            try {
                $user = $request->user('api');
            } catch (\InvalidArgumentException $e) {
                Log::debug('Cauce: failed to resolve API user in Authorize middleware', [
                    'error' => $e->getMessage(),
                ]);
                $user = null;
            }
        }

        $gate = $this->gate->forUser($user);

        if (! $gate->check('viewCauce', [$request])) {
            abort(403);
        }

        return $next($request);
    }
}
