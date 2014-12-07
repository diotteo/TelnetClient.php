#! /usr/bin/env php
<?php

require_once(__DIR__ . '/TelnetClient.php');

function cleanMsg($str) {
	$clean = preg_replace("/[^\n[:print:]]/", "", $str);
	return $clean;
}

use TelnetClient\TelnetClient;

TelnetClient::setDebug(true);

$port = 23;
$cmd = 'ls /';
switch ($argc) {
case 6:
	$cmd = $argv[5];
	//FALLTHROUGH
case 5:
	$port = $argv[4];
	//FALLTHROUGH
case 4:
	$host = $argv[3];
	$pass = $argv[2];
	$user = $argv[1];
	break;
default:
	print("Usage: {$argv[0]} <username> <password> <hostname> [<port>] [<cmd>]\n");
	exit(1);
}

$out = '';
$telnet = new TelnetClient($host, $port);
$telnet->connect();
$telnet->setPrompt('$');
$telnet->login($user, $pass);
$out = $telnet->exec($cmd);

print("out=\n" . $out . "\n");
print(cleanMsg("Global buffer=\n" . $telnet->getGlobalBuffer()) . "\n");

$telnet->disconnect();

print("\n");

?>
