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
$loginPrompt = 'login:';
$passPrompt = 'Password:';
$prompt = '$';
$pruneCtrlSeq = false;


function getOptLvl($val) {
	if (is_array($val)) {
		$lvl = count($val);
	} else {
		$lvl = 1;
	}

	return $lvl;
}


function printUsage() {
	global $argv;
	global $loginPrompt;
	global $passPrompt;
	global $prompt;

	print(<<<EOT
Usage: {$argv[0]} {options}

Options:
  -h
  --help: print this help
  
  -d
  --debug: enable debugging mode
  
  -v
  --verbosity: increase verbosity (currently unused)
  
  -H <hostname>
  --host <hostname>: connect to <hostname>
  
  -P <port>
  --port <port>: connect to <port>
  
  -u <user>
  --user <user>: Login with username <user>
  
  -p <pass>
  --pass <pass>: Login with password <pass>
  
  -c <cmd>
  --cmd <cmd>: Execute <cmd> (may be specified multiple times)
  
  --prompt <prompt>: look for <prompt> instead of {$prompt}
  --login-prompt <login-prompt>: look for <login-prompt> instead of {$loginPrompt}
  --password-prompt <pass-prompt>: look for <pass-prompt> instead of {$passPrompt}
  --prune-ctrl-seq: filters out the ANSI control sequences

EOT
);
	exit(1);
}


function parseArguments() {
	global $port;
	global $host;
	global $username;
	global $password;
	global $verbosity;
	global $debug;
	global $cmdList;
	global $loginPrompt;
	global $passPrompt;
	global $prompt;
	global $pruneCtrlSeq;

	$opts = getopt('dhc:H:P:u:p:v',
			array(
				'debug',
				'help',
				'verbosity',
				'cmd:',
				'host:',
				'port:',
				'user:',
				'pass:',
				'prompt:',
				'login-prompt:',
				'password-prompt:',
				'prune-ctrl-seq'
			));

	foreach ($opts as $opt => $optval) {
		switch ($opt) {
		case 'help':
		case 'h':
			printUsage();
			break;
		case 'verbosity':
		case 'v':
			$verbosity = getOptLvl($optval);
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
		case 'prompt':
			$prompt = $optval;
			break;
		case 'login-prompt':
			$loginPrompt = $optval;
			break;
		case 'password-prompt':
			$passPrompt = $optval;
			break;
		case 'prune-ctrl-seq':
			$pruneCtrlSeq = true;
			break;

		//Of course, getopt just silently ignores unknown options...
		default:
			print("Unknown option \"{$opt}\"\n");
			exit(1);
		}
	}

	if (is_null($username) || is_null($password)) {
		print("Error, username or password is null\n");
		printUsage();
	}

	foreach (array(&$loginPrompt, &$passPrompt) as &$var) {
		if (strlen($var) === 0) {
			$var = null;
		}
	}
}

use TelnetClient\TelnetClient;


parseArguments();

TelnetClient::setDebug($debug > 0);

$out = '';
$telnet = new TelnetClient($host, $port);
$telnet->connect();
$telnet->setPruneCtrlSeq($pruneCtrlSeq);
$telnet->setPrompt($prompt);
$telnet->login($username, $password, $loginPrompt, $passPrompt);
foreach ($cmdList as $cmd) {
	print("\n[Executing cmd \"{$cmd}\"]\n");
	if (false) {
		$out = implode("\n", $telnet->exec($cmd));
		print("\n[output]=\"{$out}\"\n");
	} else {
		$telnet->sendCommand($cmd);
		do {
			$line = $telnet->getLine($matchesPrompt);
			print($line);
		} while (!$matchesPrompt);
	}
}

$telnet->disconnect();

?>
