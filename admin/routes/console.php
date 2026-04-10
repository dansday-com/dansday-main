<?php

use App\Services\EmbeddingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('embeddings:sync-missing {--max=1 : Max rows to embed this run}', function () {
    $max = max(0, (int) $this->option('max'));
    $result = EmbeddingService::embedMissing($max, true);
    $this->info('Embedded: '.$result['embedded']);
    foreach (array_slice($result['errors'] ?? [], 0, 20) as $err) {
        $this->warn($err);
    }
})->purpose('Embed up to N database rows that do not yet have an embeddings row (one-off / manual)');

Artisan::command('embeddings:work {--sleep=1 : Seconds to wait after embedding a row} {--idle=5 : Seconds to wait when the queue is empty} {--prune-seconds=300 : Prune orphan embeddings at most this often (0 disables)}', function () {
    $sleepAfterRow = max(0, (int) $this->option('sleep'));
    $idle = max(0, (int) $this->option('idle'));
    $pruneSeconds = max(0, (int) $this->option('prune-seconds'));
    $lastPrune = 0;

    $this->info('Embedding worker running (1 row per tick).');

    while (true) {
        if ($pruneSeconds > 0 && (time() - $lastPrune) >= $pruneSeconds) {
            try {
                EmbeddingService::pruneOrphanEmbeddings();
            } catch (\Throwable $e) {
                $this->warn('Prune failed: '.$e->getMessage());
            }
            $lastPrune = time();
        }

        $result = EmbeddingService::embedMissing(1, false);

        foreach (array_slice($result['errors'] ?? [], 0, 5) as $err) {
            $this->warn($err);
        }

        if (($result['embedded'] ?? 0) > 0) {
            if ($sleepAfterRow > 0) {
                sleep($sleepAfterRow);
            }
        } elseif ($idle > 0) {
            sleep($idle);
        }
    }
})->purpose('Long-running worker: embed missing rows one at a time with delays (used by Docker/supervisord)');
