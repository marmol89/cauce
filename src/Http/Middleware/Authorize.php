<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function __construct(
        protected Gate $gate,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $gate = $this->gate->forUser($request->user());

        if (! $gate->check('viewCauce', [$request])) {
            abort(403);
        }

        return $next($request);
    }
}
