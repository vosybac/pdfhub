<?php

use App\Models\PdfDocument;
use App\Services\DocumentProcessor;
use Illuminate\Support\Facades\Artisan;

Artisan::command('pdf:process', function (?string $id = null) {
    $processor = app(DocumentProcessor::class);
    $query = PdfDocument::query()->whereIn('status', ['pending', 'failed', 'processing'])->orderBy('id');

    if ($id) {
        $query = PdfDocument::query()->where('id', $id);
    }

    $documents = $query->get();

    if ($documents->isEmpty()) {
        $this->info('No documents to process.');

        return;
    }

    foreach ($documents as $document) {
        $this->info("Processing document #{$document->id}: {$document->original_filename}");
        $result = $processor->process($document);
        $count = $result->authors()->count();
        $this->line("    -> status: {$result->status}, authors: {$count}");

        if ($result->error) {
            $this->error("    -> error: {$result->error}");
        }
    }

    $this->info('Done.');
})->purpose('Process pending PDF documents');
