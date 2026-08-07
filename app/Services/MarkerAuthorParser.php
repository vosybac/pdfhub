<?php

namespace App\Services;

class MarkerAuthorParser
{
    private array $institutionKeywords = [
        'university', 'institute', 'institution', 'laboratory', 'laboratories', 'college',
        'center', 'centre', 'school', 'department', 'dept', 'hospital', 'lab', 'faculty',
        'academy', 'foundation', 'corporation', 'inc', 'ltd', 'gmbh', 'corp', 'company',
        'co', 'associates', 'research', 'national', 'federal', 'ministry', 'agency',
        'polytechnic', 'univ', 'technion', 'clinic', 'observatory', 'council', 'society',
        'bureau', 'consortium', 'institute of technology', 'state university', 'technology',
        'sciences', 'engineering', 'medical', 'scientific', 'division', 'group', 'ioe',
        'google', 'microsoft', 'ibm', 'amazon', 'facebook', 'meta', 'apple', 'nvidia',
        'intel', 'qualcomm', 'deepmind', 'openai', 'brain', 'labs', 'corp.', 'research lab',
    ];

    public function extract(string $markdown): array
    {
        $lines = $this->cleanLines($markdown);

        [$title, $headerLines] = $this->splitTitleAndHeader($lines);
        $header = implode("\n", $headerLines);

        $emails = $this->extractEmails($header);
        $headerLines = $this->stripEmailsFromLines($headerLines, $emails);

        $authors = $this->parseAuthorLines($headerLines, $emails);

        if (empty($authors)) {
            return [
                'title' => $title,
                'authors' => [],
                'emails' => $emails,
                'raw_header' => $header,
            ];
        }

        return [
            'title' => $title,
            'authors' => $authors,
            'emails' => $emails,
            'raw_header' => $header,
        ];
    }

    private function cleanLines(string $text): array
    {
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        $lines = explode("\n", $text);

        $out = [];
        foreach ($lines as $line) {
            $line = $this->stripMarkdown($line);
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function stripMarkdown(string $line): string
    {
        $line = preg_replace('/<sup>[^<]*?\/?sup>/u', '', $line);
        $line = preg_replace('/<[^>]+>/', '', $line);
        $line = preg_replace('/\x{2201}E?/u', '', $line);
        $line = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $line);
        $line = preg_replace('/\*{1,3}/', '', $line);
        $line = preg_replace('/`+/', '', $line);
        $line = preg_replace('/[\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}]+/u', '', $line);
        $line = preg_replace('/[\x{7ab6}\x{7acf}\x{7d80}\x{88cf}\x{f8f0}]+[\x{30fb}]*/u', '', $line);
        $line = preg_replace('/^\s*>\s?/', '', $line);
        $line = preg_replace('/[ \t]+/', ' ', $line);

        return trim($line);
    }

    private function splitTitleAndHeader(array $lines): array
    {
        $title = null;
        $start = 0;

        foreach ($lines as $i => $line) {
            if (preg_match('/^#{1,4}\s+(.+)$/', $line, $m)) {
                $title = trim($m[1]);
                $start = $i + 1;
                break;
            }
        }

        $headerLines = [];
        for ($i = $start; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('/^#{1,4}\s+/', $line)) {
                break;
            }
            if ($this->isBodyStartLine($line)) {
                break;
            }
            $headerLines[] = $line;
        }

        return [$title, $headerLines];
    }

    private function isBodyStartLine(string $line): bool
    {
        return (bool) preg_match('/^\s*(abstract|introduction|keywords?|index\s*terms?|1\.\s*introduction|acknowledg(e)?ment|body)\b/i', $line);
    }

    private function extractEmails(string $text): array
    {
        $emails = [];

        $text = preg_replace_callback('/\{([^}]*)\}@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/', function ($m) use (&$emails) {
            $domain = $m[2];
            $parts = preg_split('/[,;]|\s+/', $m[1]);
            $expanded = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $email = strtolower($part . '@' . $domain);
                $emails[] = $email;
                $expanded[] = $email;
            }

            return implode(' ', $expanded);
        }, $text);

        preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $matches);

        foreach ($matches[0] as $email) {
            $emails[] = strtolower($email);
        }

        return array_values(array_unique($emails));
    }

    private function stripEmailsFromLines(array $lines, array $emails): array
    {
        foreach ($lines as &$line) {
            $line = preg_replace('/\{[^}]*\}@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '', $line);
            foreach ($emails as $email) {
                $line = str_ireplace($email, '', $line);
            }
            $line = trim(preg_replace('/[ \t]+/', ' ', $line));
        }
        unset($line);

        return array_values(array_filter($lines, fn ($l) => $l !== ''));
    }

    private function parseAuthorLines(array $lines, array $emails): array
    {
        $authors = [];
        $affByIndex = [];

        foreach ($lines as $line) {
            if ($this->isHeadingLike($line) || $this->isBodyStartLine($line)) {
                break;
            }

            $line = $this->stripLeadingMarkers($line);
            if ($line === '' || $this->isSymbolLine($line) || $this->isMarkerLine($line)) {
                continue;
            }

            if (preg_match('/^corresponding\s+author\b/i', $line)) {
                continue;
            }
            if (preg_match('/^this\s+(work|research|study|paper).*(supported|funded|grant|project)/i', $line)) {
                continue;
            }

            [$namePart, $affPart] = $this->splitAffiliation($line);
            if ($affPart !== null) {
                $affPart = trim(preg_replace('/^[\d\s]+/', '', $affPart));
            }

            if ($namePart === '') {
                if ($affPart !== null) {
                    $this->assignAffiliationToAll($authors, $affPart);
                }
                continue;
            }

            $names = $this->splitNames($namePart);
            if (empty($names)) {
                if ($affPart !== null) {
                    $this->assignAffiliationToAll($authors, $affPart);
                }
                continue;
            }

            foreach ($names as $name) {
                $name = $this->cleanName($name);
                if ($name === '') {
                    continue;
                }
                $authors[] = [
                    'name' => $name,
                    'affiliation' => $affPart,
                    'email' => null,
                ];
                $affByIndex[] = $affPart;
            }
        }

        $this->assignEmails($authors, $emails);

        return $authors;
    }

    private function assignAffiliationToAll(array &$authors, ?string $aff): void
    {
        if ($aff === null || $aff === '') {
            return;
        }

        foreach ($authors as &$author) {
            if (empty($author['affiliation'])) {
                $author['affiliation'] = $aff;
            }
        }
        unset($author);
    }

    private function isHeadingLike(string $line): bool
    {
        return (bool) preg_match('/^#{1,4}\s+/', $line);
    }

    private function stripLeadingMarkers(string $line): string
    {
        $line = preg_replace('/^\s*(?:[\d*+\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#^]\s*)+/u', '', $line);

        return trim($line);
    }

    private function isSymbolLine(string $line): bool
    {
        return (bool) preg_match('/^[\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}*+#^.\s]+$/u', $line);
    }

    private function isMarkerLine(string $line): bool
    {
        return preg_match('/\d/', $line) && (bool) preg_match('/^[\d,.\s]+$/u', $line);
    }

    private function splitAffiliation(string $line): array
    {
        $lower = strtolower($line);
        $best = null;

        foreach ($this->institutionKeywords as $kw) {
            $pattern = '/(?<![a-z])' . preg_quote(strtolower($kw), '/') . '(?![a-z])/i';
            if (preg_match($pattern, $lower, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($best === null || $pos < $best) {
                    $best = $pos;
                }
            }
        }

        if ($best !== null && $best > 0) {
            $name = trim(substr($line, 0, $best));
            $aff = trim(substr($line, $best));

            if (preg_match('/(\d+)\s*$/', $name, $m)) {
                $name = trim(substr($name, 0, -strlen($m[1])));
                $aff = $m[1] . ' ' . $aff;
            }

            return [$name, $aff];
        }

        if ($best === 0) {
            return ['', $line];
        }

        return [$line, null];
    }

    private function splitNames(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $text = preg_replace('/\b(and|&)\b/i', ',', $text);
        $parts = preg_split('/\s*,\s*/', $text);

        $names = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $words = preg_split('/\s+/', $part);
            if (count($words) >= 6) {
                $names = array_merge($names, $this->splitNameRun($words));

                continue;
            }

            if ($this->isPlausibleName($part)) {
                $names[] = $part;
            }
        }

        return array_values(array_unique($names));
    }

    private function splitNameRun(array $words): array
    {
        $names = [];
        $i = 0;
        $count = count($words);

        while ($i < $count) {
            if ($i + 1 < $count) {
                $candidate = $words[$i] . ' ' . $words[$i + 1];
                if ($this->isPlausibleName($candidate)) {
                    $names[] = $candidate;
                    $i += 2;

                    continue;
                }
            }

            $names[] = $words[$i];
            $i++;
        }

        return $names;
    }

    private function cleanName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[\d*+\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#^]+$/u', '', $name);
        $name = preg_replace('/^[\d*+\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#^]+/u', '', $name);

        return trim($name);
    }

    private function isPlausibleName(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $clean = preg_replace('/[\d*+\x{2020}\x{2021}\x{00a7}\x{00b6}\x{2217}\x{2218}#^]+$/u', '', $token);
        $clean = trim($clean);

        if (strlen($clean) < 3 || strlen($clean) > 60) {
            return false;
        }

        if (preg_match('/et\s*al/i', $clean)) {
            return false;
        }

        $lower = strtolower($clean);
        foreach ($this->institutionKeywords as $kw) {
            if (preg_match('/(?<![a-z])' . preg_quote(strtolower($kw), '/') . '(?![a-z])/i', $lower)) {
                return false;
            }
        }

        $words = preg_split('/\s+/', $clean);
        if (count($words) < 1 || count($words) > 5) {
            return false;
        }

        $capWords = 0;
        foreach ($words as $word) {
            if (preg_match('/^\p{Lu}/u', $word)) {
                $capWords++;
            }
        }

        if ($capWords < min(2, count($words))) {
            return false;
        }

        foreach ($words as $word) {
            if (preg_match('/^\p{Lu}\.?$/u', $word) || preg_match('/^[\p{L}\-]+$/u', $word)) {
                continue;
            }
            if (! preg_match('/^\p{Lu}[\p{L}\-]*$/u', $word)) {
                return false;
            }
        }

        return true;
    }

    private function emailTokenMatch(string $local, string $token): bool
    {
        if ($local === '' || $token === '') {
            return false;
        }

        if (str_contains($local, $token)) {
            return true;
        }

        if (strlen($local) >= 4 && strlen($token) >= 4 && levenshtein($local, $token) <= 2) {
            return true;
        }

        return false;
    }

    private function assignEmails(array &$authors, array $emails): void
    {
        if (empty($emails) || empty($authors)) {
            return;
        }

        if (count($authors) === 1) {
            $authors[0]['email'] = $emails[0];

            return;
        }

        $assigned = array_fill(0, count($authors), false);
        $usedEmails = [];

        foreach ($emails as $email) {
            $local = preg_replace('/[^a-z@.]/', '', strtolower(explode('@', $email)[0]));
            $bestIndex = null;
            $bestScore = 0;

            foreach ($authors as $i => &$author) {
                if ($assigned[$i]) {
                    continue;
                }
                $nameLower = preg_replace('/[^a-z\s]/', '', strtolower($author['name']));
                $words = preg_split('/\s+/', trim($nameLower));
                $lastName = end($words);
                $firstName = $words[0] ?? '';

                $score = 0;
                if ($lastName !== '' && $this->emailTokenMatch($local, $lastName)) {
                    $score += 2;
                }
                if ($firstName !== '' && str_contains($local, $firstName)) {
                    $score += 1;
                }
                if ($lastName !== '' && $this->emailTokenMatch($local, substr($firstName, 0, 1) . $lastName)) {
                    $score += 2;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $i;
                }
            }
            unset($author);

            if ($bestIndex !== null && $bestScore > 0) {
                $authors[$bestIndex]['email'] = $email;
                $assigned[$bestIndex] = true;
                $usedEmails[] = $email;
            }
        }

        $remainingEmails = array_values(array_diff($emails, $usedEmails));
        $unassigned = [];
        foreach ($authors as $i => $author) {
            if (! $assigned[$i]) {
                $unassigned[] = $i;
            }
        }

        if (count($remainingEmails) === count($unassigned)) {
            foreach ($unassigned as $j => $i) {
                $authors[$i]['email'] = $remainingEmails[$j];
            }

            return;
        }

        if (count($emails) === count($authors) && empty($usedEmails)) {
            foreach ($authors as $i => &$author) {
                $author['email'] = $emails[$i];
            }
            unset($author);
        }
    }
}
