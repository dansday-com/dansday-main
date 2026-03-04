<?php

namespace App\Http\Middleware;

use App\Models\General;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RunSetupOnFirstVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->ensureStorageLink();
        $this->runMigrationsIfNeeded();
        $this->runSeedIfNeeded();

        return $next($request);
    }

    /** Create public/storage symlink if missing (zip deployments often omit symlinks). */
    protected function ensureStorageLink(): void
    {
        $link = public_path('storage');
        if (file_exists($link) && (is_link($link) || is_dir($link))) {
            return;
        }
        $target = storage_path('app/public');
        if (! is_dir($target)) {
            @mkdir($target, 0755, true);
        }
        try {
            Artisan::call('storage:link');
        } catch (\Throwable) {
            if (! file_exists($link) && function_exists('symlink')) {
                @symlink($target, $link);
            }
        }
    }

    /** Run migrations when DB has no tables (e.g. first deploy / shared hosting). */
    protected function runMigrationsIfNeeded(): void
    {
        try {
            if (Schema::hasTable('page_setting')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $lockFile = storage_path('app/.migrate.lock');
        $dir = dirname($lockFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fp = @fopen($lockFile, 'c');
        if (! $fp || ! flock($fp, LOCK_EX | LOCK_NB)) {
            if ($fp) {
                fclose($fp);
            }
            return;
        }

        try {
            if (Schema::hasTable('page_setting')) {
                return;
            }
            Artisan::call('migrate', ['--force' => true]);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** Seed when no settings row exists. */
    protected function runSeedIfNeeded(): void
    {
        try {
            if (General::find(1)) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $lock = Cache::lock('db_seed_once', 60);

        if (! $lock->get()) {
            return;
        }

        try {
            if (General::find(1)) {
                return;
            }
            Artisan::call('db:seed', ['--force' => true]);
        } finally {
            $lock->release();
        }
    }
}
