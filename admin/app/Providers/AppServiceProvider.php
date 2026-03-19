<?php

namespace App\Providers;

use App\Models\General;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $publicPath = $this->app->basePath('public');
        $publicHtmlPath = $this->app->basePath('public_html');

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
            if (is_dir($publicHtmlPath)) {
                $this->app->usePublicPath($publicHtmlPath);
            }
        }
    }

    public function boot(): void
    {
        Model::automaticallyEagerLoadRelationships();

        Schema::defaultStringLength(191);

        if ($this->app->runningInConsole() === false && request()->hasHeader('Host')) {
            $rootUrl = request()->getScheme() . '://' . request()->getHttpHost();
            URL::forceRootUrl(rtrim($rootUrl, '/'));
            if (request()->header('X-Forwarded-Proto') === 'https') {
                URL::forceScheme('https');
            }
        }

        $appUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost')), '/');
        config([
            'filesystems.disks.uploads.root' => public_path('uploads'),
            'filesystems.disks.uploads.url'   => $appUrl.'/uploads',
        ]);
    }
}
