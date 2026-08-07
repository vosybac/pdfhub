<?php

namespace App\Services;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    public function extract(string $path): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);

        $details = $pdf->getDetails();
        $metadata = [
            'title' => $details['Title'] ?? null,
            'author' => $details['Author'] ?? null,
            'subject' => $details['Subject'] ?? null,
            'keywords' => $details['Keywords'] ?? null,
            'creation_date' => $details['CreationDate'] ?? null,
        ];

        $pages = [];
        foreach ($pdf->getPages() as $page) {
            $pages[] = $page->getText();
        }

        return [
            'metadata' => $metadata,
            'pages' => $pages,
            'page_count' => count($pages),
        ];
    }
}
