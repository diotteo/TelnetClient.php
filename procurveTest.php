#! /usr/bin/env php
<?php

require_once('TelnetClient.php');

$port = 23;
$host = '192.168.253.1';
$username = ' ';
$password = null;
$verbosity = 0;
$debug = 0;
$cmdList = array('ls /');
$loginPrompt = 'Press any key to continue';
$passPrompt = null;
$prompt = '^[[:alnum:]-]+(\(config\))?#';


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
$telnet->setPruneCtrlSeq(true);
$telnet->login($username, $password, $loginPrompt, $passPrompt);

$PAGER_LINE = '-- MORE --, next page: Space, next line: Enter, quit: Control-C';

foreach ($cmdList as $cmd) {
	$telnet->sendCommand($cmd);
	do {
		$line = $telnet->getLine($matchesPrompt);

		if (strncmp($line, $PAGER_LINE, strlen($PAGER_LINE)) === 0) {
			$telnet->sendCommand(' ', false);
		} else {
			print($line);
		}
	} while (!$matchesPrompt);
}

$telnet->disconnect();

?>
