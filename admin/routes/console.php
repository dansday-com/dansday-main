<?php

use App\Services\LinkedInScheduler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('linkedin:publish-due {--max=5 : Max scheduled posts to publish this run}', function () {
    $result = LinkedInScheduler::runDue((int) $this->option('max'));
    $this->info('Published: '.$result['published'].', retrying: '.$result['retrying'].', failed: '.$result['failed']);
    foreach (array_slice($result['errors'] ?? [], 0, 20) as $err) {
        $this->warn($err);
    }
})->purpose('Publish any LinkedIn posts whose scheduled time has passed (one-off / manual)');

Artisan::command('linkedin:work {--idle=60 : Seconds to wait between checks} {--max=5 : Max posts published per tick}', function () {
    $idle = max(5, (int) $this->option('idle'));
    $max = max(1, (int) $this->option('max'));

    $this->info('LinkedIn scheduler running (checking every '.$idle.'s).');

    while (true) {
        try {
            $result = LinkedInScheduler::runDue($max);

            foreach (array_slice($result['errors'] ?? [], 0, 5) as $err) {
                $this->warn($err);
            }
        } catch (\Throwable $e) {
            $this->warn('Scheduler tick failed: '.$e->getMessage());
        }

        sleep($idle);
    }
})->purpose('Long-running worker: publish scheduled LinkedIn posts as they come due (used by Docker/supervisord)');
