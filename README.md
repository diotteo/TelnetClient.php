TelnetClient.php
================

Telnet client implementation in PHP

Usage example:
---
```php
require_once(__DIR__ . '/TelnetClient.php');

use TelnetClient\TelnetClient;

//Uncomment this to get debug logging
//TelnetClient::setDebug(true);

$telnet = new TelnetClient('127.0.0.1', 23);
$telnet->connect();
$telnet->login('telnetuser', 'weak');
$cmdResult = $telnet->exec('ls /');

$telnet->disconnect();

print("The contents of / is: \"{$cmdResult}\"\n");
```

Alternatively, have a look at testTelnet.php:
$ ./testTelnet.php -h
$ ./testTelnet.php -u telnetuser -p weak -H 127.0.0.1 -P 23 -c "ls /"
