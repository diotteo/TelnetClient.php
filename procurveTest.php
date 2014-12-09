#! /usr/bin/env php
<?php

require_once(__DIR__ . '/TelnetClient.php');

$port = 23;
$host = '192.168.253.1';
$username = ' ';
$password = null;
$verbosity = 0;
$debug = 0;
$cmdList = array('ls /');
$loginPrompt = 'Press any key to continue';
$passPrompt = null;
$prompt = '^[[:cntrl]]*[[:alnum:]-]*(\(config\))?# ';


function cleanMsg($str) {
	$clean = addcslashes($str, "\r\t\"");

	$s = '';
	for ($i = 0; $i < strlen($clean); $i++) {
		$c = $clean[$i];
		if ($c === "\n" || !ctype_cntrl($c)) {
			$s .= $c;
		} else {
			$s .= '0x' . bin2hex($c);
		}
	}
	return $s;
}

use TelnetClient\TelnetClient;


TelnetClient::setDebug($debug > 0);

$cmdList = array('sh ru');

$out = '';
$telnet = new TelnetClient($host, $port);
$telnet->connect();
$telnet->setRegexPrompt($prompt);
$telnet->login($username, $password, $loginPrompt, $passPrompt);
foreach ($cmdList as $cmd) {
	print("\n[Executing cmd \"{$cmd}\"]\n");
	if (false) {
		$out = implode("\n", $telnet->exec($cmd));
		print("\n[output]=\"" . cleanMsg($out) . "\"\n");
	} else {
		$telnet->sendCommand($cmd);
		do {
			$line = $telnet->getLine($matchesPrompt);
			print(cleanMsg($line));
		} while (!$matchesPrompt);
	}
}

$telnet->disconnect();

?>
