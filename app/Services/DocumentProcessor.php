<?php

namespace App\Services;

use App\Models\Author;
use App\Models\PdfDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentProcessor
{
    public function __construct(
        private PdfTextExtractor $extractor,
        private AuthorExtractor $authorExtractor,
        private MarkerExtractor $markerExtractor,
        private MarkerAuthorParser $markerAuthorParser,
    ) {}

    public function process(PdfDocument $document): PdfDocument
    {
        $document->status = 'processing';
        $document->error = null;
        $document->save();

        try {
            $path = $document->filePath();
            if (! is_file($path)) {
                throw new \RuntimeException('PDF file is missing on disk.');
            }

            $result = $this->extractor->extract($path);
            $pages = $result['pages'];
            $metadata = $result['metadata'];
            $text = implode("\n", $pages);

            $parsed = $this->parseAuthors($path, $pages, $metadata);

            $document->page_count = $result['page_count'];
            $document->title = $parsed['title'] ?? $metadata['title'] ?? $document->title;
            $document->extracted_text = mb_substr($text, 0, 200000);
            $document->save();

            DB::transaction(function () use ($document, $parsed) {
                $document->authors()->detach();

                $seen = [];
                foreach ($parsed['authors'] as $order => $parsedAuthor) {
                    $author = $this->findOrCreateAuthor($parsedAuthor);

                    if (isset($seen[$author->id])) {
                        continue;
                    }
                    $seen[$author->id] = true;

                    $document->authors()->attach($author->id, [
                        'author_order' => $order,
                        'affiliation' => $parsedAuthor['affiliation'],
                        'email' => $parsedAuthor['email'],
                    ]);
                }
            });

            $document->status = 'processed';
            $document->processed_at = now();
            $document->save();

            return $document;
        } catch (\Throwable $e) {
            $document->status = 'failed';
            $document->error = $e->getMessage();
            $document->save();

            return $document;
        }
    }

    public function parseAuthors(string $path, array $pages, array $metadata): array
    {
        if (config('marker.enabled')) {
            try {
                $markdown = $this->markerExtractor->extract($path);
                $parsed = $this->markerAuthorParser->extract($markdown);

                if (! empty($parsed['authors'])) {
                    $parsed['source'] = 'marker';

                    return $parsed;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Marker extraction failed, falling back: ' . $e->getMessage());
            }
        }

        $parsed = $this->authorExtractor->extract($pages, $metadata);
        $parsed['source'] = 'regex';

        return $parsed;
    }

    public function findOrCreateAuthor(array $parsed): Author
    {
        $name = trim($parsed['name']);
        $email = $parsed['email'] ?? null;
        $affiliation = $parsed['affiliation'] ?? null;

        $normalized = Author::normalizeName($name);

        $author = Author::query()->where('normalized_name', $normalized)->first();

        if (! $author && $email) {
            $author = Author::query()->where('email', $email)->first();
        }

        if ($author) {
            $dirty = false;
            if ($email && ! $author->email) {
                $author->email = $email;
                $dirty = true;
            }
            if ($affiliation && ! $author->affiliation) {
                $author->affiliation = $affiliation;
                $dirty = true;
            }
            if ($dirty) {
                $author->save();
            }

            return $author;
        }

        $words = preg_split('/\s+/', $name);
        $firstName = count($words) > 1 ? $words[0] : null;
        $lastName = end($words);

        return Author::create([
            'full_name' => $name,
            'normalized_name' => $normalized,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'affiliation' => $affiliation,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
        ]);
    }
}
