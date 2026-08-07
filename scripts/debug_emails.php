<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$md = file_get_contents('C:\Users\B\AppData\Local\Temp\opencode\doc6_md.json');
$md = json_decode($md, true)['markdown'];

$parser = new App\Services\MarkerAuthorParser();

$r = new ReflectionClass($parser);
foreach (['extractEmails', 'stripEmailsFromLines', 'splitAffiliation', 'splitNames'] as $m) {
    $method = $r->getMethod($m);
    $method->setAccessible(true);
    if ($m === 'extractEmails') {
        $lines = explode("\n", $md);
        $hdr = implode("\n", array_slice($lines, 0, 12));
        $emails = $method->invoke($parser, $hdr);
        echo "emails = " . json_encode($emails) . "\n";
    }
}
