<?php

$authors = [
    ['name' => 'Xiangnan He', 'affiliation' => null, 'email' => null],
    ['name' => 'Tao Chen', 'affiliation' => null, 'email' => null],
    ['name' => 'Min-Yen Kan', 'affiliation' => null, 'email' => null],
    ['name' => 'Xiao Chen', 'affiliation' => null, 'email' => null],
];
$emails = ["xiangnan@comp.nus.edu.sg","kanmy@comp.nus.edu.sg","taochen@comp.nus.edu.sg","chenxiao3310@ict.ac.cn"];

$assigned = array_fill(0, count($authors), false);
$usedEmails = [];

foreach ($emails as $email) {
    $local = strtolower(preg_replace('/[^a-z@.]/', '', explode('@', $email)[0]));
    $bestIndex = null;
    $bestScore = 0;

    foreach ($authors as $i => &$author) {
        if ($assigned[$i]) {
            continue;
        }
        $nameLower = strtolower(preg_replace('/[^a-z\s]/', '', $author['name']));
        $words = preg_split('/\s+/', trim($nameLower));
        $lastName = end($words);
        $firstName = $words[0] ?? '';

        $score = 0;
        if ($lastName !== '' && str_contains($local, $lastName)) {
            $score += 2;
        }
        if ($firstName !== '' && str_contains($local, $firstName)) {
            $score += 1;
        }
        if ($lastName !== '' && str_contains($local, substr($firstName, 0, 1) . $lastName)) {
            $score += 2;
        }

        echo "  email=[$local] vs [{$author['name']}] local?[$local] lastName=[$lastName] firstName=[$firstName] score=$score\n";

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
        echo "  => assigned $email to [{$authors[$bestIndex]['name']}]\n";
    }
}

foreach ($authors as $a) {
    echo "{$a['name']} => {$a['email']}\n";
}
