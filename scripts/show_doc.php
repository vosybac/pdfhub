<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PdfDocument;

$d = PdfDocument::find(6);
echo "id={$d->id} file={$d->original_filename}\n";
echo "stored={$d->stored_path}\n";
echo "status={$d->status}\n";
echo "title={$d->title}\n";
