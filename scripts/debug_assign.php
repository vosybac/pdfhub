<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = new App\Services\MarkerAuthorParser();
$r = new ReflectionClass($parser);
$m = $r->getMethod('assignEmails'); $m->setAccessible(true);

$authors = [
    ['name' => 'Xiangnan He', 'affiliation' => null, 'email' => null],
    ['name' => 'Tao Chen', 'affiliation' => null, 'email' => null],
    ['name' => 'Min-Yen Kan', 'affiliation' => null, 'email' => null],
    ['name' => 'Xiao Chen', 'affiliation' => null, 'email' => null],
];
$emails = ["xiangnan@comp.nus.edu.sg","kanmy@comp.nus.edu.sg","taochen@comp.nus.edu.sg","chenxiao3310@ict.ac.cn"];

$args = [&$authors, $emails];
$m->invokeArgs($parser, $args);
foreach ($authors as $a) {
    echo "{$a['name']} => {$a['email']}\n";
}
