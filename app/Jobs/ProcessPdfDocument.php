<?php

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Services\DocumentProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPdfDocument implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public int $documentId) {}

    public function handle(DocumentProcessor $processor): void
    {
        $document = PdfDocument::query()->find($this->documentId);

        if (! $document) {
            return;
        }

        $processor->process($document);
    }
}
