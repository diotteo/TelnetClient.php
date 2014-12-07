#! /usr/bin/env php
<?php

require_once(__DIR__ . '/TelnetClient.php');

$port = 23;
$host = '127.0.0.1';
$username = null;
$password = null;
$verbosity = 0;
$debug = 0;
$cmdList = array('ls /');


function getOptLvl($val) {
	if (is_array($val)) {
		$lvl = count($val);
	} else {
		$lvl = 1;
	}

	return $lvl;
}


function parseArguments() {
	global $argv;
	global $port;
	global $host;
	global $username;
	global $password;
	global $verbosity;
	global $debug;
	global $cmdList;

	$opts = getopt('dhc:H:P:u:p:v', array('debug', 'help', 'cmd:', 'host:', 'port:', 'user', 'pass'));

	foreach ($opts as $opt => $optval) {
		switch ($opt) {
		case 'help':
		case 'h':
			print("Usage: {$argv[0]} {options}\n");
			exit(1);
			break;
		case 'debug':
		case 'd':
			$debug = getOptLvl($optval);
			break;

		//Because PHP's getopt() sucks
		case 'cmd':
		case 'c':
			if (!is_array($optval)) {
				$cmdList = array($optval);
			} else {
				$cmdList = $optval;
			}
			break;

		case 'host':
		case 'H':
			$host = $optval;
			break;
		case 'port':
		case 'P':
			$port = $optval;
			break;
		case 'user':
		case 'u':
			$username = $optval;
			break;
		case 'pass':
		case 'p':
			$password = $optval;
			break;
		case 'v':
			$verbosity = getOptLvl($optval);
			break;
		default:
			print("Unknown option \"{$opt}\"\n");
			exit(1);
		}
	}
}


function cleanMsg($str) {
	$clean = preg_replace("/[^\n[:print:]]/", "", $str);
	return $clean;
}

use TelnetClient\TelnetClient;


parseArguments();

TelnetClient::setDebug($debug > 0);

$out = '';
$telnet = new TelnetClient($host, $port);
$telnet->connect();
$telnet->setPrompt('$');
$telnet->login($username, $password);
foreach ($cmdList as $cmd) {
	print("\n***Executing cmd \"{$cmd}\"***\n");
	$out = $telnet->exec($cmd);

	print("\n***out=***\n" . $out . "\n");
	print("\n***Global buffer=***\n" . $telnet->getGlobalBuffer() . "\n");
}

$telnet->disconnect();

?>
