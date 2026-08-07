<?php

namespace App\Services;

use Illuminate\Support\Str;

class AuthorExtractor
{
    private array $institutionKeywords = [
        'university', 'institute', 'institution', 'laboratory', 'laboratories', 'college',
        'center', 'centre', 'school', 'department', 'dept', 'hospital', 'lab', 'faculty',
        'academy', 'foundation', 'corporation', 'inc', 'ltd', 'gmbh', 'corp', 'company',
        'co', 'associates', 'research', 'national', 'federal', 'ministry', 'agency',
        'polytechnic', 'univ', 'technion', 'clinic', 'observatory', 'council', 'society',
        'bureau', 'consortium', 'institute of technology', 'state university', 'technology',
        'sciences', 'engineering', 'medical', 'scientific', 'division', 'group', 'ioe',
    ];

    public function extract(array $pages, array $metadata = []): array
    {
        $text = $this->headerText($pages);
        $header = $this->truncateAtBody($text);

        $emails = $this->extractEmails($header);
        $lines = $this->cleanLines($header);

        $zoneStart = $this->findAuthorZoneStart($lines);
        $title = $this->guessTitle($lines, $zoneStart, $metadata['title'] ?? null);

        $authors = [];
        $affiliations = [];
        if ($zoneStart !== null) {
            [$affiliations, $authors] = $this->collectTokens($lines, $zoneStart);
        }

        if (empty($authors)) {
            $metaAuthors = $this->parseMetaAuthors($metadata['author'] ?? null);
            $authors = array_map(fn ($n) => [
                'name' => $n,
                'markers' => [],
                'affiliation' => null,
                'email' => null,
            ], $metaAuthors);
        }

        if (empty($authors)) {
            return [
                'title' => $title,
                'authors' => [],
                'emails' => $emails,
                'raw_header' => $header,
            ];
        }

        $this->assignAffiliations($authors, $affiliations);
        $this->assignEmails($authors, $emails);

        return [
            'title' => $title,
            'authors' => array_map(fn ($a) => [
                'name' => $a['name'],
                'affiliation' => $a['affiliation'],
                'email' => $a['email'],
            ], $authors),
            'emails' => $emails,
            'raw_header' => $header,
        ];
    }

    private function headerText(array $pages): string
    {
        return implode("\n", array_slice($pages, 0, 2));
    }

    private function truncateAtBody(string $text): string
    {
        $markers = [
            '/\babstract\b/i',
            '/\bindex\s*terms?\b/i',
            '/\bkeywords?\b/i',
            '/\bintroduction\b/i',
            '/\bbody\b/i',
        ];

        $positions = [];
        foreach ($markers as $marker) {
            if (preg_match($marker, $text, $m, PREG_OFFSET_CAPTURE)) {
                $positions[] = $m[0][1];
            }
        }

        if (! empty($positions)) {
            $text = substr($text, 0, min($positions));
        }

        return $text;
    }

    private function extractEmails(string $text): array
    {
        preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[0])));
    }

    private function cleanLines(string $text): array
    {
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        $lines = explode("\n", $text);
        $lines = array_map(fn ($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), $lines);

        return array_values(array_filter($lines, fn ($l) => $l !== ''));
    }

    private function isAffiliationLike(string $line, ?string $next): bool
    {
        if ($this->isEmailLine($line)) {
            return false;
        }

        $stripped = $this->stripLeadingSymbols($line);
        $lower = strtolower($stripped);

        foreach ($this->institutionKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        if ($this->isEmailLine($next) && ! $this->emailMatchesName($next, $line)) {
            return true;
        }

        return false;
    }

    private function cleanAffiliation(string $line): string
    {
        $line = $this->stripLeadingSymbols($line);
        $line = preg_replace('/\s{2,}/', ' ', $line);
        $line = preg_replace('/\b(E-?mail|Email)\s*:?.*$/i', '', $line);

        return trim($line);
    }

    private function stripLeadingSymbols(string $line): string
    {
        $line = preg_replace('/^\s*(?:[\d*\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#+^]\s*)+/u', '', $line);

        return trim($line);
    }

    private function isEmailLine(?string $line): bool
    {
        return $line !== null && str_contains($line, '@');
    }

    private function isSymbolLine(?string $line): bool
    {
        if ($line === null || $line === '') {
            return false;
        }

        return (bool) preg_match('/^[\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}*+#^.\s]+$/u', $line);
    }

    private function isMarkerLine(?string $line): bool
    {
        if ($line === null) {
            return false;
        }

        return preg_match('/\d/', $line) && (bool) preg_match('/^[\d,.\s]+$/u', $line);
    }

    private function isNameToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $clean = preg_replace('/[\d*+\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#^]+$/u', '', $token);
        $clean = trim($clean);

        if (strlen($clean) < 3 || strlen($clean) > 70) {
            return false;
        }

        if (preg_match('/et\s*al/i', $clean)) {
            return false;
        }

        $lower = strtolower($clean);
        foreach ($this->institutionKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return false;
            }
        }

        $words = preg_split('/\s+/', $clean);
        if (count($words) < 2 || count($words) > 7) {
            return false;
        }

        $last = end($words);
        if (! preg_match('/^\p{Lu}\p{L}+$/u', $last)) {
            return false;
        }

        foreach ($words as $word) {
            if (preg_match('/^\p{Lu}\.?$/u', $word)) {
                continue;
            }
            if (! preg_match('/^\p{Lu}\p{L}+$/u', $word)) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeNameLine(string $line): bool
    {
        if (strlen($line) > 220) {
            return false;
        }

        $tokens = $this->splitNameTokens($line);
        $nameTokens = array_filter($tokens, fn ($t) => $this->isNameToken($t));

        return count($nameTokens) >= 2;
    }

    private function splitNameTokens(string $line): array
    {
        $line = preg_replace('/\b(and|&)\b/i', ',', $line);
        $parts = preg_split('/\s*,\s*/', $line);

        return array_values(array_filter($parts, fn ($p) => trim($p) !== ''));
    }

    private function emailMatchesName(string $email, string $name): bool
    {
        $local = strtolower(explode('@', $email)[0]);
        $local = preg_replace('/[^a-z]/', '', $local);

        $words = preg_split('/\s+/', Str::ascii(trim($name)));
        $words = array_map(fn ($w) => strtolower(preg_replace('/[^a-z]/', '', $w)), $words);
        $words = array_filter($words, fn ($w) => strlen($w) >= 3);

        foreach ($words as $word) {
            if ($local !== '' && str_contains($local, $word)) {
                return true;
            }
        }

        return false;
    }

    private function findAuthorZoneStart(array $lines): ?int
    {
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            if ($this->looksLikeNameLine($line)) {
                return $i;
            }

            $next = $lines[$i + 1] ?? null;
            $next2 = $lines[$i + 2] ?? null;

            if ($this->isMultiNameRun($line) && ($this->isAffiliationLike((string) $next, $next2) || $this->isEmailLine($next))) {
                return $i;
            }

            if (! $this->isNameToken($line)) {
                continue;
            }

            if ($this->isSymbolLine($next) || $this->isAffiliationLike((string) $next, $next2)) {
                return $i;
            }

            if ($this->isEmailLine($next) && $this->emailMatchesName($next, $line)) {
                return $i;
            }

            if ($this->isSymbolLine($next2) && ($this->isAffiliationLike((string) $next, $next2) || $this->isEmailLine($next))) {
                return $i;
            }
        }

        return null;
    }

    private function collectTokens(array $lines, int $start): array
    {
        $tokens = [];
        $affByAuthor = [];
        $affList = [];
        $nameTokenCount = 0;
        $count = count($lines);

        for ($i = $start; $i < $count; $i++) {
            $line = $lines[$i];

            if (strlen($line) > 250) {
                break;
            }

            if ($this->isEmailLine($line)) {
                continue;
            }

            if ($this->isMarkerLine($line)) {
                $tokens[] = $line;
                continue;
            }

            if ($this->isSymbolLine($line)) {
                continue;
            }

            $next = $lines[$i + 1] ?? null;
            if ($this->isAffiliationLike($line, $next)) {
                $aff = $this->cleanAffiliation($line);
                $affList[] = $aff;
                if ($nameTokenCount > 0) {
                    $affByAuthor[$nameTokenCount - 1] = $aff;
                }
                continue;
            }

            $valid = [];
            foreach ($this->splitNameTokens($line) as $token) {
                if ($this->isNameToken($token)) {
                    $valid[] = $token;
                }
            }

            if (empty($valid)) {
                if ($this->isMultiNameRun($line)) {
                    foreach ($this->splitMultiNameRun($line) as $name) {
                        $tokens[] = $name;
                        $nameTokenCount++;
                    }
                }
                continue;
            }

            foreach ($valid as $token) {
                $tokens[] = $token;
                $nameTokenCount++;
            }
        }

        $authors = $this->parseNames($tokens);

        foreach ($affByAuthor as $idx => $aff) {
            if (isset($authors[$idx]) && $authors[$idx]['affiliation'] === null) {
                $authors[$idx]['affiliation'] = $aff;
            }
        }

        $affList = array_values(array_unique($affList));

        return [$affList, $authors];
    }

    private function parseNames(array $tokens): array
    {
        $authors = [];
        $pendingMarkers = [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            if (preg_match('/^[\d,.\s]+$/u', $token) && ! empty($authors)) {
                preg_match_all('/\d+/', $token, $m);
                $pendingMarkers = array_merge($pendingMarkers, $m[0]);
                continue;
            }

            if (! $this->isNameToken($token)) {
                if ($this->isMultiNameRun($token)) {
                    foreach ($this->splitMultiNameRun($token) as $name) {
                        $authors[] = [
                            'name' => $name,
                            'markers' => [],
                            'affiliation' => null,
                            'email' => null,
                        ];
                    }
                }
                continue;
            }

            $markers = $pendingMarkers;
            $pendingMarkers = [];
            preg_match_all('/\d+/', $token, $m);
            $markers = array_merge($markers, $m[0]);

            $clean = preg_replace('/[\d*+\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#^]+$/u', '', $token);
            $clean = trim(preg_replace('/\s{2,}/', ' ', $clean));

            $authors[] = [
                'name' => $clean,
                'markers' => array_values(array_unique($markers)),
                'affiliation' => null,
                'email' => null,
            ];
        }

        return $authors;
    }

    private function isMultiNameRun(string $token): bool
    {
        $clean = preg_replace('/[\d*+\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#^]+$/u', '', trim($token));
        $clean = trim($clean);

        if (strlen($clean) < 10) {
            return false;
        }

        $words = preg_split('/\s+/', $clean);
        if (count($words) < 6) {
            return false;
        }

        foreach ($words as $word) {
            if (! preg_match('/^\p{Lu}\p{L}+$/u', $word)) {
                return false;
            }
        }

        $lower = strtolower($clean);
        foreach ($this->institutionKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return false;
            }
        }

        return true;
    }

    private function splitMultiNameRun(string $token): array
    {
        $clean = preg_replace('/[\d*+\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#^]+$/u', '', trim($token));
        $words = preg_split('/\s+/', trim($clean));
        $names = [];

        $i = 0;
        $count = count($words);
        while ($i < $count) {
            if ($i + 1 < $count) {
                $names[] = $words[$i] . ' ' . $words[$i + 1];
                $i += 2;
            } else {
                $last = array_pop($names);
                $names[] = $last . ' ' . $words[$i];
                $i++;
            }
        }

        return $names;
    }

    private function assignAffiliations(array &$authors, array $affiliations): void
    {
        if (empty($affiliations)) {
            return;
        }

        $unassigned = [];
        foreach ($authors as $i => $author) {
            if ($author['affiliation'] === null) {
                $unassigned[] = $i;
            }
        }

        if (empty($unassigned)) {
            return;
        }

        $indexed = [];
        foreach ($affiliations as $i => $aff) {
            $indexed[($i + 1)] = $aff;
        }

        $hasMarkers = array_reduce($authors, fn ($carry, $a) => $carry || ! empty($a['markers']), false);

        if ($hasMarkers) {
            foreach ($unassigned as $i) {
                $mapped = [];
                foreach ($authors[$i]['markers'] as $marker) {
                    if (isset($indexed[$marker])) {
                        $mapped[] = $indexed[$marker];
                    }
                }
                $authors[$i]['affiliation'] = $mapped ? implode('; ', array_values(array_unique($mapped))) : ($affiliations[0] ?? null);
            }

            return;
        }

        if (count($affiliations) === count($authors)) {
            foreach ($unassigned as $i) {
                $authors[$i]['affiliation'] = $affiliations[$i] ?? $affiliations[0];
            }

            return;
        }

        if (count($affiliations) === 1) {
            foreach ($unassigned as $i) {
                $authors[$i]['affiliation'] = $affiliations[0];
            }

            return;
        }

        foreach ($unassigned as $i) {
            $authors[$i]['affiliation'] = $affiliations[0];
        }
    }

    private function assignEmails(array &$authors, array $emails): void
    {
        if (empty($emails)) {
            return;
        }

        if (count($authors) === 1) {
            $authors[0]['email'] = $emails[0];

            return;
        }

        if (count($emails) === count($authors)) {
            foreach ($authors as $i => &$author) {
                $author['email'] = $emails[$i];
            }
            unset($author);

            return;
        }

        $assigned = array_fill(0, count($authors), false);

        foreach ($emails as $email) {
            $local = strtolower(preg_replace('/[^a-z@.]/', '', explode('@', $email)[0]));
            foreach ($authors as $i => &$author) {
                if ($assigned[$i]) {
                    continue;
                }
                $lastName = strtolower(preg_replace('/[^a-z]/', '', $this->lastName($author['name'])));
                $firstInitial = strtolower(substr(preg_replace('/[^a-z]/', '', explode(' ', $author['name'])[0]), 0, 1));
                if ($lastName !== '' && (str_contains($local, $lastName) || str_contains($local, $firstInitial . $lastName))) {
                    $author['email'] = $email;
                    $assigned[$i] = true;
                    break;
                }
            }
            unset($author);
        }

        if (! in_array(false, $assigned, true)) {
            return;
        }

        if (count($emails) !== count($authors)) {
            return;
        }

        foreach ($authors as $i => &$author) {
            if ($author['email'] === null && isset($emails[$i])) {
                $author['email'] = $emails[$i];
            }
        }
        unset($author);
    }

    private function lastName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));

        return end($words);
    }

    private function guessTitle(array $lines, ?int $zoneStart, ?string $metaTitle): ?string
    {
        if ($metaTitle) {
            return trim($metaTitle);
        }

        if ($zoneStart !== null && $zoneStart > 0) {
            $line = $lines[$zoneStart - 1];

            if (strlen($line) >= 8 && str_word_count($line) >= 3) {
                return $line;
            }
        }

        foreach ($lines as $line) {
            if (strlen($line) > 10 && ! $this->looksLikeNameLine($line)) {
                return $line;
            }
        }

        return null;
    }

    private function parseMetaAuthors(?string $meta): array
    {
        if (empty($meta)) {
            return [];
        }

        $meta = preg_replace('/et\s*al\.?/i', '', $meta);
        $tokens = $this->splitNameTokens($meta);

        return array_values(array_filter(array_map(fn ($t) => trim($t), $tokens), fn ($t) => $this->isNameToken($t)));
    }
}
