<?php

namespace StreetMesh\Venue\Http;

use Closure;
use Illuminate\Http\Request;
use StreetMesh\Venue\Visitors;

/**
 * This part of the venue is for people who have arrived.
 *
 * Not authentication in the usual sense — there is nothing here to be
 * authenticated against. It asks only whether this browser is acting under a
 * permission somebody's own server gave us, and sends them to the door if not.
 *
 * Where they were heading is remembered, because being asked to identify
 * yourself and then dumped at the entrance is the small rudeness that makes a
 * federated sign-in feel worse than a local one.
 */
final class RequireVisitor
{
    public function __construct(private readonly Visitors $visitors) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->visitors->current($request) !== null) {
            return $next($request);
        }

        $request->session()->put(Visitors::INTENDED_KEY, $request->fullUrl());

        return redirect()->route('venue.connect');
    }
}
