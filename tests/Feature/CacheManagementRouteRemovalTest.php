<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use ReflectionFunction;
use ReflectionMethod;
use Tests\TestCase;

/**
 * SEC-CACHE-AUTH-01 — the public cache-management endpoint stays removed.
 *
 * GET|HEAD /clear-all (named clear.all) ran config:clear, cache:clear, route:clear, view:clear and
 * optimize for any visitor: it sat in the web group only, and EnsureAdminAccess gates admin/* paths
 * alone. It was removed outright, with no replacement endpoint.
 *
 * These tests only inspect the registered route collection and read the source of each route's
 * handler. They never dispatch a request and never call an Artisan command, so they cannot clear
 * or rebuild a cache.
 */
class CacheManagementRouteRemovalTest extends TestCase
{
    /** The operations the removed route ran, plus the combined clear. */
    private const CACHE_COMMANDS = ['config:clear', 'cache:clear', 'route:clear', 'view:clear', 'optimize', 'optimize:clear'];

    public function test_the_clear_all_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('clear.all'), 'The named route clear.all is registered again.');

        $found = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (trim($route->uri(), '/') === 'clear-all') {
                $found[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        $this->assertSame([], $found, "A /clear-all route is registered:\n" . implode("\n", $found));
    }

    public function test_no_route_handler_runs_cache_management_commands(): void
    {
        $offenders = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $source = $this->handlerSource($route);
            if ($source === null) {
                continue;
            }

            $label = implode('|', $route->methods()) . ' ' . $route->uri();
            if (preg_match('/\bArtisan::/', $source)) {
                $offenders[] = "{$label} (calls Artisan)";
            }
            foreach (self::CACHE_COMMANDS as $command) {
                if (preg_match('/[\'"]' . preg_quote($command, '/') . '[\'"]/', $source)) {
                    $offenders[] = "{$label} (names {$command})";
                }
            }
        }

        $this->assertSame([], $offenders, "Route handlers that run cache-management operations:\n" . implode("\n", $offenders));
    }

    public function test_unrelated_routes_remain_registered(): void
    {
        $registered = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                $registered["{$method} {$route->uri()}"] = true;
            }
        }

        $expected = [
            'GET api/characters',              // public catalogue read
            'GET api/channels/{channel}',      // member read
            'POST api/channels/by-region',
            'POST api/{any}',                  // the API catch-all
            'GET admin/characters/create',     // admin web surface
            'GET explain_videos/{filename}',   // neighbouring closure route in routes/web.php
            'GET login',                       // routes/auth.php, required just after the removed route
            'POST logout',
            'GET up',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $registered, "Expected route {$key} is no longer registered.");
        }

        foreach (['login', 'logout', 'dashboard', 'profile.edit', 'admin.characters.index', 'admin.channels.index'] as $name) {
            $this->assertTrue(Route::has($name), "Expected named route {$name} is no longer registered.");
        }
    }

    /** The source text of the closure or controller method that handles the route, or null. */
    private function handlerSource(RegisteredRoute $route): ?string
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof Closure) {
            $reflection = new ReflectionFunction($uses);
        } elseif (is_string($uses) && str_contains($uses, '@')) {
            [$class, $method] = explode('@', $uses, 2);
            if (! method_exists($class, $method)) {
                return null;
            }
            $reflection = new ReflectionMethod($class, $method);
        } else {
            return null;
        }

        $file = $reflection->getFileName();
        if ($file === false || ! is_readable($file)) {
            return null;
        }

        $lines = file($file);
        $start = $reflection->getStartLine() - 1;

        return implode('', array_slice($lines, $start, $reflection->getEndLine() - $start));
    }
}
