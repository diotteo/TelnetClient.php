<?php
/**
 * Telnet class
 *
 * Used to execute remote commands via telnet connection
 * Uses sockets functions and fgetc() to process result
 *
 * All methods throw Exceptions on error
 *
 * Written by Dalibor Andzakovic <dali@swerve.co.nz>
 * Based on the code originally written by Marc Ennaji and extended by
 * Matthias Blaser <mb@adfinis.ch>
 *
 * Extended by Christian Hammers <chammers@netcologne.de>
 * Modified by Frederik Sauer <fsa@dwarf.dk>
 *
 * Modified by Olivier Diotte <olivier+github@diotte.ca>
 */
class Telnet {
	private static $DEBUG = FALSE;

	private $host;
	private $port;
	private $timeout;
	private $stream_timeout_sec;
	private $stream_timeout_usec;

	private $socket = NULL;
	private $buffer = NULL;
	private $prompt;
	private $errno;
	private $errstr;
	private $strip_prompt = TRUE;

	/* NVT special characters
	 * specified in the same order as in RFC854
	 * same name as there, with a NVT_ prefix (to avoid clash with PHP keywords)
	 */

	// Codes that have special meaning to the NVT Printer
	const NVT_NUL  = "\x00";
	const NVT_LF   = "\x0A";
	const NVT_CR   = "\x0D";

	const NVT_BEL  = "\x07";
	const NVT_BS   = "\x08";
	const NVT_HT   = "\x09";
	const NVT_VT   = "\x0B";
	const NVT_FF   = "\x0C";

	private static $NVTP_SPECIALS = array(
			self::NVT_NUL  => 'NUL',
			self::NVT_LF   => 'LF',
			self::NVT_CR   => 'CR',
			self::NVT_BEL  => 'BEL',
			self::NVT_BS   => 'BS',
			self::NVT_HT   => 'HT',
			self::NVT_VT   => 'VT',
			self::NVT_FF   => 'FF',
			);

	/* TELNET command characters
	 * "Note that these codes and code sequences have the indicated meaning
	 * only when immediately preceded by an IAC." RFC854
	 */
	/* RFC1123:
	 * MUST:   SE, NOP, DM, IP, AO, AYT, SB
	 * SHOULD: EOR, EC, EL, BRK
	 */
	const CMD_SE   = "\xF0"; //Subnegotiation End
	const CMD_NOP  = "\xF1";
	const CMD_DM   = "\xF2"; //Data Mark
	const CMD_BRK  = "\xF3"; //Break
	const CMD_IP   = "\xF4"; //Interrupt Process
	const CMD_AO   = "\xF5"; //Abort Output
	const CMD_AYT  = "\xF6"; //Are You There
	const CMD_EC   = "\xF7"; //Erase Character
	const CMD_EL   = "\xF8"; //Erase Line
	const CMD_GA   = "\xF9"; //Go Ahead
	const CMD_SB   = "\xFA"; //Subnegotiation (start)
	const CMD_WILL = "\xFB";
	const CMD_WONT = "\xFC";
	const CMD_DO   = "\xFD";
	const CMD_DONT = "\xFE";
	const CMD_IAC  = "\xFF";

	private static $CMDS = array(
			self::CMD_SE   => 'SE',
			self::CMD_NOP  => 'NOP',
			self::CMD_DM   => 'DM',
			self::CMD_BRK  => 'BRK',
			self::CMD_IP   => 'IP',
			self::CMD_AO   => 'AO',
			self::CMD_AYT  => 'AYT',
			self::CMD_EC   => 'EC',
			self::CMD_EL   => 'EL',
			self::CMD_GA   => 'GA',
			self::CMD_SB   => 'SB',
			self::CMD_WILL => 'WILL',
			self::CMD_WONT => 'WONT',
			self::CMD_DO   => 'DO',
			self::CMD_DONT => 'DONT',
			self::CMD_IAC  => 'IAC'
			);


	const OPT_TXBIN     = "\x00"; //Transmit binary, RFC856
	const OPT_ECHO      = "\x01"; //Echo, RFC857
	const OPT_SGA       = "\x03"; //Suppress Go Ahead, RFC858
	const OPT_STATUS    = "\x05"; //Status, RFC859
	const OPT_TIMMRK    = "\x06"; //Timing Mark, RFC860
	const OPT_EXTOPL    = "\xFF"; //Extended options list, RFC861
	const OPT_EOR       = "\x19"; //25, End of record, RFC885
	const OPT_3270_R    = "\x1D"; //29, 3270 Regimes (?), RFC1041
	const OPT_NAWS      = "\x1F"; //31, Negotiate About Window Size, RFC1073
	const OPT_TERMSPD   = "\x20"; //32, Terminal speed, RFC1079
	const OPT_TERMTYP   = "\x18"; //24, Terminal type, RFC1091
	const OPT_XDISPLOC  = "\x23"; //35, X Display Location, RFC1096
	const OPT_LINEMODE  = "\x22"; //34, Linemode, RFC1116
	const OPT_NEW_ENV   = "\x27"; //39, New environment variable, RFC1572
	const OPT_R_FLW_CTR = "\x21"; //33, Remote Flow Control, RFC1080

	private static $OPTS = array(
			self::OPT_TXBIN     => 'Transmit Binary',
			self::OPT_ECHO      => 'Echo',
			self::OPT_SGA       => 'Suppress Go Ahead',
			self::OPT_STATUS    => 'Status',
			self::OPT_TIMMRK    => 'Timing Mark',
			self::OPT_EXTOPL    => 'Extended Options List',
			self::OPT_EOR       => 'End Of Record',
			self::OPT_3270_R    => '3270-Regime',
			self::OPT_NAWS      => 'Negotiate About Window Size',
			self::OPT_TERMSPD   => 'Terminal Speed',
			self::OPT_TERMTYP   => 'Terminal Type',
			self::OPT_XDISPLOC  => 'X Display Location',
			self::OPT_LINEMODE  => 'Linemode',
			self::OPT_NEW_ENV   => 'New Environment',
			self::OPT_R_FLW_CTR => 'Remote Flow Control'
			);


	private $global_buffer = '';

	const TELNET_ERROR = FALSE;
	const TELNET_OK = TRUE;


	/**
	 * Ideally, this would work if subclasses
	 * defined only their own static $DEBUG field, but the parent
	 * class doesn't have access to children's private fields (unsurprisingly)
	 *
	 * Therefore, child classes need to define both their own $DEBUG field AND
	 * copy this method. This seems to be the cleanest way to do it as
	 * it breaks cleanly if a child class doesn't do it
	 */
	public static function setDebug($enable) {
		static::$DEBUG = !!$enable;
	}


	private static function getCodeStrOrHexStr($code, $CODE_LIST) {
		if (array_key_exists($code, $CODE_LIST)) {
			return $CODE_LIST[$code];
		}

		return '0x' . bin2hex($code);
	}


	public static function getNvtPrintSpecialStr($code) {
		return self::getCodeStrOrHexStr($code, self::$NVTP_SPECIALS);
	}


	public static function getCmdStr($code) {
		return self::getCodeStrOrHexStr($code, self::$CMDS);
	}


	public static function getOptStr($code) {
		return self::getCodeStrOrHexStr($code, self::$OPTS);
	}


	/**
	 * Constructor. Initialises host, port and timeout parameters
	 * defaults to localhost port 23 (standard telnet port)
	 *
	 * @param string $host Host name or IP addres
	 * @param int $port TCP port number
	 * @param int $timeout Connection timeout in seconds
	 * @param string $prompt Telnet prompt string
	 * @param float $streamTimeout Stream timeout in decimal seconds
	 * @return void
	 */
	public function __construct($host = '127.0.0.1', $port = '23', $timeout = 10, $prompt = '$', $stream_timeout = 1) {
		$this->host = $host;
		$this->port = $port;
		$this->timeout = $timeout;
		$this->setPrompt($prompt);
		$this->setStreamTimeout($stream_timeout);

		$this->connect();
	}

	/**
	 * Destructor. Cleans up socket connection and command buffer
	 *
	 * @return void
	 */
	public function __destruct() {
		// clean up resources
		$this->disconnect();
		$this->buffer = NULL;
		$this->global_buffer = NULL;
	}

	/**
	 * Attempts connection to remote host. Returns TRUE if successful.
	 *
	 * @return boolean
	 */
	public function connect() {
		// check if we need to convert host to IP
		if (!preg_match('/([0-9]{1,3}\\.){3,3}[0-9]{1,3}/', $this->host)) {
			$ip = gethostbyname($this->host);

			if ($this->host == $ip) {
				throw new Exception("Cannot resolve $this->host");
			} else {
				$this->host = $ip;
			}
		}

		// attempt connection - suppress warnings
		$this->socket = @fsockopen($this->host, $this->port, $this->errno, $this->errstr, $this->timeout);

		if (!$this->socket) {
			throw new Exception("Cannot connect to $this->host on port $this->port");
		}

		if (!empty($this->prompt)) {
			$this->waitPrompt();
		}

		return self::TELNET_OK;
	}

	/**
	 * Closes IP socket
	 *
	 * @return boolean
	 */
	public function disconnect() {
		if ($this->socket) {
			if (!fclose($this->socket)) {
				throw new Exception("Error while closing telnet socket");
			}
			$this->socket = NULL;
		}
		return self::TELNET_OK;
	}

	/**
	 * Executes command and returns a string with result.
	 * This method is a wrapper for lower level private methods
	 *
	 * @param string $command Command to execute
	 * @param boolean $add_newline Default TRUE, adds newline to the command
	 * @return string Command result
	 */
	public function exec($command, $add_newline = TRUE) {
		$this->write($command, $add_newline);
		$this->waitPrompt();
		return $this->getBuffer();
	}

	/**
	 * Attempts login to remote host.
	 * This method is a wrapper for lower level private methods and should be
	 * modified to reflect telnet implementation details like login/password
	 * and line prompts. Defaults to standard unix non-root prompts
	 *
	 * @param string $username Username
	 * @param string $password Password
	 * @return boolean
	 */
	public function login($username, $password, $loginPrompt = 'login:', $passwordPrompt = 'Password:') {
		$prompt = $this->prompt;
		try {
			$this->setPrompt($loginPrompt);
			$this->waitPrompt();
			$this->write($username);
			$this->setPrompt($passwordPrompt);
			$this->waitPrompt();
			$this->write($password);
			$this->setPrompt($prompt);
			$this->waitPrompt();
		} catch (Exception $e) {
			throw new Exception("Login failed: {$e->getMessage()}");
		}

		return self::TELNET_OK;
	}

	/**
	 * Sets the string of characters to respond to.
	 * This should be set to the last character of the command line prompt
	 *
	 * @param string $str String to respond to
	 * @return boolean
	 */
	public function setPrompt($str = '$') {
		return $this->setRegexPrompt(preg_quote($str, '/'));
	}

	/**
	 * Sets a regex string to respond to.
	 * This should be set to the last line of the command line prompt.
	 *
	 * @param string $str Regex string to respond to
	 * @return boolean
	 */
	public function setRegexPrompt($str = '\$') {
		$this->prompt = $str;
		return self::TELNET_OK;
	}

	/**
	 * Sets the stream timeout.
	 * 
	 * @param float $timeout
	 * @return void
	 */
	public function setStreamTimeout($timeout) {
		$this->stream_timeout_usec = (int)(fmod($timeout, 1) * 1000000);
		$this->stream_timeout_sec = (int)$timeout;
	}

	/**
	 * Set if the buffer should be stripped from the buffer after reading.
	 *
	 * @param $strip boolean if the prompt should be stripped.
	 * @return void
	 */
	public function stripPromptFromBuffer($strip) {
		$this->strip_prompt = $strip;
	}

	/**
	 * Gets character from the socket
	 *
	 * @return void
	 */
	protected function getc() {
		stream_set_timeout($this->socket, $this->stream_timeout_sec, $this->stream_timeout_usec);
		$c = fgetc($this->socket);
		$this->global_buffer .= $c;
		return $c;
	}

	/**
	 * Clears internal command buffer
	 *
	 * @return void
	 */
	public function clearBuffer() {
		$this->buffer = '';
	}

	/**
	 * Reads characters from the socket and adds them to command buffer.
	 * Handles telnet control characters. Stops when prompt is ecountered.
	 *
	 * @param string $prompt
	 * @return boolean
	 */
	protected function readTo($prompt) {
		if (!$this->socket) {
			throw new Exception("Telnet connection closed");
		}

		// clear the buffer
		$this->clearBuffer();

		$until_t = time() + $this->timeout;
		do {
			// time's up (loop can be exited at end or through continue!)
			if (time() > $until_t) {
				throw new Exception("Couldn't find the requested : '$prompt' within {$this->timeout} seconds");
			}

			$c = $this->getc();

			if ($c === FALSE) {
				if (empty($prompt)) {
					return self::TELNET_OK;
				}
				throw new Exception("Couldn't find the requested : '" . $prompt . "', it was not in the data returned from server: " . $this->buffer);
			}

			// Interpret As Command
			if ($c == self::CMD_IAC) {
				if (self::$DEBUG) {
					printf("[IAC 0x%s]", bin2hex($c));
				}
				if ($this->negotiateTelnetOptions()) {
					continue;
				}
			}

			// append current char to global buffer
			$this->buffer .= $c;
			if (self::$DEBUG) {
				printf($c);
			}

			// we've encountered the prompt. Break out of the loop
			if (!empty($prompt) && preg_match("/{$prompt}$/", $this->buffer)) {
				return self::TELNET_OK;
			}

		} while ($c != self::NVT_NUL);
	}

	/**
	 * Write command to a socket
	 *
	 * @param string $buffer Stuff to write to socket
	 * @param boolean $add_newline Default TRUE, adds newline to the command
	 * @return boolean
	 */
	protected function write($buffer, $add_newline = TRUE) {
		if (!$this->socket) {
			throw new Exception("Telnet connection closed");
		}

		// clear buffer from last command
		$this->clearBuffer();

		if ($add_newline == TRUE) {
			$buffer .= "\r\n";
		}

		$this->global_buffer .= $buffer;
		if (!fwrite($this->socket, $buffer) < 0) {
			throw new Exception("Error writing to socket");
		}

		return self::TELNET_OK;
	}

	/**
	 * Returns the content of the command buffer
	 *
	 * @return string Content of the command buffer
	 */
	protected function getBuffer() {
		// Remove all carriage returns from line breaks
		$buf = preg_replace('/\r\n|\r/', "\n", $this->buffer);
		// Cut last line from buffer (almost always prompt)
		if ($this->strip_prompt) {
			$buf = explode("\n", $buf);
			unset($buf[count($buf) - 1]);
			$buf = implode("\n", $buf);
		}
		return trim($buf);
	}

	/**
	 * Returns the content of the global command buffer
	 *
	 * @return string Content of the global command buffer
	 */
	public function getGlobalBuffer() {
		return $this->global_buffer;
	}

	/**
	 * Telnet control character magic
	 *
	 * @param string $command Character to check
	 * @return boolean
	 */
	protected function negotiateTelnetOptions() {
		$cmd = $this->getc();

		$replyCmd = null;
		switch ($cmd) {
		case self::CMD_IAC: //FIXME: This is supposed to happen in binary mode
			throw new Exception('Error: Something Wicked Happened');
			break;
		case self::CMD_DO: //FALLTHROUGH
		case self::CMD_DONT:
			$replyCmd = self::CMD_WONT;
			break;

		case self::CMD_WILL: //FALLTHROUGH
		case self::CMD_WONT:
			$replyCmd = self::CMD_DONT;
			break;

		default:
			if (self::$DEBUG) {
				print('Ignoring unknown command character 0x' . bin2hex($cmd) . "\n");
			}
			//FIXME: Should we return OK?
			return self::TELNET_OK;
		}

		$opt = $this->getc();
		fwrite($this->socket, self::CMD_IAC . $replyCmd . $opt);

		if (self::$DEBUG) {
			$str = sprintf("[CMD %s]", self::getCmdStr($cmd));
			$str .= sprintf("[OPT %s]", self::getOptStr($opt));
			print($str . "\n");
		}

		return self::TELNET_OK;
	}

	/**
	 * Reads socket until prompt is encountered
	 */
	protected function waitPrompt() {
		if (self::$DEBUG) {
			print("\nWaiting for prompt \"{$this->prompt}\"\n");
		}
		return $this->readTo($this->prompt);
	}
}
