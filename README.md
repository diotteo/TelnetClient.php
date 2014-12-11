TelnetClient.php
================

Telnet client implementation in PHP.

This code is based on https://github.com/ngharo/Random-PHP-Classes/blob/master/Telnet.class.php
but was completely rewritten. I tried to keep interface compatibility as much as possible.

Things that I know not to work the same anymore:<br>
- Using the constructor with more than 2 arguments, the meaning and order of subsequent parameters have changed<br>
- Line endings should always be returned to the caller as "\n" but this guarantee is based on the assumption of a correct server implementation (one that encodes line endings as \<CR\> \<LF\> in the default (text) state)
- buffer and global_buffer as well as their associated methods are gone
- The constructor no longer does a connect() call
- The subclassing interface is probably broken (I couldn't keep the getBuffer() method without skipping the state machine)

Many things are still wrong (though it was like that in upstream versions too):
- we DONT/WONT Suppress Go Ahead, Echo and Linemode but expect them to work, etc.

Usage example:
---
```php
require_once(__DIR__ . '/TelnetClient.php');

use TelnetClient\TelnetClient;

//Uncomment this to get debug logging
//TelnetClient::setDebug(true);

$telnet = new TelnetClient('127.0.0.1', 23);
$telnet->connect();
$telnet->setPrompt('$'); //setRegexPrompt() to use a regex
//$telnet->setPruneCtrlSeq(true); //Enable this to filter out ANSI control/escape sequences
$telnet->login('telnetuser', 'weak');

$cmdResult = $telnet->exec('ls /');

$telnet->disconnect();

print("The contents of / is: \"{$cmdResult}\"\n");
```

Alternatively, have a look at testTelnet.php:
```shell
$ ./testTelnet.php -h
$ ./testTelnet.php -u telnetuser -p weak -H 127.0.0.1 -P 23 -c "ls /"
```
