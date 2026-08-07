<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$md = file_get_contents('C:\Users\B\AppData\Local\Temp\opencode\doc6_md.json');
$md = json_decode($md, true)['markdown'];

$parser = new App\Services\MarkerAuthorParser();
$r = new ReflectionClass($parser);

$cleanLines = $r->getMethod('cleanLines'); $cleanLines->setAccessible(true);
$lines = $cleanLines->invoke($parser, $md);

echo "--- first 15 cleaned lines ---\n";
foreach (array_slice($lines, 0, 15) as $i => $l) {
    echo "$i: [$l]\n";
}

$split = $r->getMethod('splitTitleAndHeader'); $split->setAccessible(true);
[$title, $headerLines] = $split->invoke($parser, $lines);
echo "--- title: $title ---\n";
foreach ($headerLines as $i => $l) {
    echo "H$i: [$l]\n";
}

$extractEmails = $r->getMethod('extractEmails'); $extractEmails->setAccessible(true);
$emails = $extractEmails->invoke($parser, implode("\n", $headerLines));
echo "emails=" . json_encode($emails) . "\n";
