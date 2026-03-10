<?php

namespace App\Providers;

use App\Models\General;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $publicPath = $this->app->basePath('public');
        $publicHtmlPath = $this->app->basePath('public_html');

        // Use the folder that is actually the document root so uploads go to the right place
        if (! empty($_SERVER['DOCUMENT_ROOT'])) {
            $docRoot = realpath((string) $_SERVER['DOCUMENT_ROOT']);
            $publicReal = is_dir($publicPath) ? realpath($publicPath) : false;
            $publicHtmlReal = is_dir($publicHtmlPath) ? realpath($publicHtmlPath) : false;
            if ($docRoot && $publicHtmlReal && $docRoot === $publicHtmlReal) {
                $this->app->usePublicPath($publicHtmlPath);
            } elseif ($docRoot && $publicReal && $docRoot === $publicReal) {
                $this->app->usePublicPath($publicPath);
            }
        } else {
            // CLI / no document root: use public_html if it exists (e.g. shared hosting deploy)
            if (is_dir($publicHtmlPath)) {
                $this->app->usePublicPath($publicHtmlPath);
            }
        }
    }

    public function boot(): void
    {
        Model::automaticallyEagerLoadRelationships();

        Schema::defaultStringLength(191);

        $this->runMigrationsOnWebBoot();

        // Use current request URL for asset() so preview (e.g. 20.dansday.com) and production share one codebase
        if ($this->app->runningInConsole() === false && request()->hasHeader('Host')) {
            $rootUrl = request()->getScheme() . '://' . request()->getHttpHost();
            URL::forceRootUrl(rtrim($rootUrl, '/'));
            if (request()->header('X-Forwarded-Proto') === 'https') {
                URL::forceScheme('https');
            }
        }

        // Force uploads disk to use current public path (works even when config is cached)
        $appUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost')), '/');
        config([
            'filesystems.disks.uploads.root' => public_path('uploads'),
            'filesystems.disks.uploads.url'   => $appUrl.'/uploads',
        ]);

        // OpenAI URL and key come only from General settings (DB), not env
        if (Schema::hasTable('page_setting') && Schema::hasColumn('page_setting', 'openai_url')) {
            try {
                $general = General::find(1);
                if ($general) {
                    $url = isset($general->openai_url) ? trim((string) $general->openai_url) : '';
                    $key = isset($general->openai_key) ? trim((string) $general->openai_key) : '';
                    if ($url !== '') {
                        config(['ai.providers.openai.url' => $url]);
                        config(['prism.providers.openai.url' => $url]);
                    }
                    if ($key !== '') {
                        config(['ai.providers.openai.key' => $key]);
                        config(['prism.providers.openai.api_key' => $key]);
                    }
                }
            } catch (\Throwable $e) {
                // ignore (e.g. migration not run yet)
            }
        }
    }

    /** Run pending migrations when the web app boots (e.g. after deploy). */
    protected function runMigrationsOnWebBoot(): void
    {
        if ($this->app->runningInConsole()) {
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
            Artisan::call('migrate', ['--force' => true]);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
