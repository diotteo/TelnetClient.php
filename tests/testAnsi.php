#! /usr/bin/env php
<?php

require_once(__DIR__ . '/AnsiAsciiControlParser.php');

$f = fopen('test5.raw', 'rb');
$s = stream_get_contents($f);

$parser = new AnsiAsciiControlParser();
$parser->parse($s);

//var_dump($parser->getTokenList());
print($parser->getTextString() . "\n");

?>
