<?php

namespace App\Http\Middleware;

use App\Support\ModulePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (is_string($routeName) && str_starts_with($routeName, 'utilisateurs.')) {
            abort(403, 'Accès réservé aux administrateurs.');
        }

        $module = ModulePermissions::moduleForRoute($routeName);

        if ($module === null) {
            return $next($request);
        }

        if (! $user->canView($module)) {
            abort(403, 'Vous n\'avez pas la permission de consulter ce module.');
        }

        if (! $request->isMethodSafe() && ! $user->canWrite()) {
            abort(403, 'Compte gestionnaire : consultation uniquement.');
        }

        return $next($request);
    }
}
