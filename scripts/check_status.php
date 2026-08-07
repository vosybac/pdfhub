<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo 'queued jobs: ' . DB::table('jobs')->count() . "\n";
echo 'failed jobs: ' . DB::table('failed_jobs')->count() . "\n";
echo "docs:\n";
foreach (App\Models\PdfDocument::orderBy('id')->get() as $d) {
    echo "  #{$d->id} {$d->original_filename} status={$d->status} authors=" . $d->authors()->count() . "\n";
}
