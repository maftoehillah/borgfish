<?php

namespace App\Http\Middleware;

use App\Services\SystemSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServeStaticMaintenancePage
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(SystemSettingService::class);

        if (! $settings->maintenanceEnabled()) {
            return $next($request);
        }

        if ($this->shouldBypassInfrastructure($request)) {
            return $next($request);
        }

        if ($this->shouldBypassForAdmin($request)) {
            return $next($request);
        }

        if ($this->inExceptList($request, $settings->maintenanceExcept())) {
            return $next($request);
        }

        $htmlPath = $this->resolveHtmlPath($settings->maintenanceHtmlPath());
        $status = $settings->maintenanceStatus();

        if (is_file($htmlPath) && is_readable($htmlPath)) {
            return response(file_get_contents($htmlPath) ?: '', $status, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        return response(
            $this->fallbackHtml($settings->maintenanceMessage()),
            $status,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function inExceptList(Request $request, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);

            if ($pattern === '') {
                continue;
            }

            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function shouldBypassInfrastructure(Request $request): bool
    {
        if ($request->is('up')) {
            return true;
        }

        if ($request->is('api/tripay/callback')) {
            return true;
        }

        if ($request->is('media/*') || $request->is('storage/*')) {
            return true;
        }

        if ($request->is('livewire/*') || $request->is('livewire-*/*')) {
            return true;
        }

        if ($request->is('build/*') || $request->is('images/*')) {
            return true;
        }

        return in_array($request->path(), ['favicon.ico', 'robots.txt'], true);
    }

    private function shouldBypassForAdmin(Request $request): bool
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return true;
        }

        $user = $request->user();
        if ($user && method_exists($user, 'isAdminUser') && $user->isAdminUser()) {
            return true;
        }

        if ($this->hasAdminReferer($request)) {
            if ($request->is('livewire/*') || $request->is('livewire-*/*')) {
                return true;
            }

            if ($request->is('filament/*') || $request->is('admin/*')) {
                return true;
            }
        }

        return false;
    }

    private function hasAdminReferer(Request $request): bool
    {
        $refererPath = trim((string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH), '/');

        return $refererPath === 'admin' || str_starts_with($refererPath, 'admin/');
    }

    private function fallbackHtml(string $message): string
    {
        $escapedMessage = e($message);

        return "<!doctype html><html lang=\"id\"><head><meta charset=\"UTF-8\"><title>Maintenance</title></head><body><h1>Situs sedang maintenance</h1><p>{$escapedMessage}</p></body></html>";
    }

    private function resolveHtmlPath(string $configuredPath): string
    {
        $configuredPath = trim($configuredPath);

        if ($configuredPath === '') {
            return public_path('maintenance.html');
        }

        if ($this->isAbsolutePath($configuredPath)) {
            return $configuredPath;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath);

        if (str_starts_with($normalized, 'public' . DIRECTORY_SEPARATOR)) {
            return base_path($normalized);
        }

        return public_path($normalized);
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1
            || str_starts_with($path, '\\\\')
            || str_starts_with($path, '/');
    }
}
