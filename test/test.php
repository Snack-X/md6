<?php

require("../md6.php");

$md6 = new md6hash();
$result = file("result.csv");

$total = 0;
$ok = 0;

foreach($result as $line) {
	$a = explode(",", trim($line));
	$size = intval($a[0]);
	$data = $a[1];
	$comp = $a[2];

	$hash = $md6->hex($data, $size);

	$total++;
	if($hash === $comp) $ok++;
}

echo "{$ok} / {$total} test(s) passed.".PHP_EOL;