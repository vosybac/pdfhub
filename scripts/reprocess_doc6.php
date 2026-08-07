<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PdfDocument;
use App\Services\DocumentProcessor;

$d = PdfDocument::find(6);
$processor = app(DocumentProcessor::class);

try {
    $d = $processor->process($d);
    echo "status={$d->status}\n";
    if ($d->error) {
        echo "error={$d->error}\n";
    }
    echo "title={$d->title}\n";
    echo "DB author_count=" . $d->authors()->count() . "\n";
    foreach ($d->authors as $a) {
        echo "  name={$a->full_name} | aff={$a->pivot->affiliation} | email={$a->pivot->email}\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString();
}
