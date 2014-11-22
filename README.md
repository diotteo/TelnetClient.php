Telnet.class.php
================

Telnet client implementation in PHP

Usage example:
```php
require_once(__DIR__ . '/Telnet.class.php');

$telnet = new Telnet('127.0.0.1', 23, 10, '');
$telnet->login('telnetuser', 'weak');
$cmdResult = $telnet->exec('ls /');

$telnet->disconnect();

print("The contents of / is: \"{$cmdResult}\"\n");
```
