<?php
/**
 * TelnetClient class
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
 * Rewritten by Olivier Diotte <olivier+github@diotte.ca>
 */
class TelnetClient {
	/* NVT special characters
	 * specified in the same order as in RFC854
	 * same name as there, with a NVT_ prefix (to avoid clash with PHP keywords)
	 */

	// Codes that have special meaning to the NVT Printer
	const NVT_NUL  = "\x00";
	const NVT_LF   = "\n"; //"\x0A";
	const NVT_CR   = "\r"; //"\x0D";

	const NVT_BEL  = "\x07";
	const NVT_BS   = "\x08";
	const NVT_HT   = "\x09";
	const NVT_VT   = "\x0B";
	const NVT_FF   = "\x0C";

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
	const CMD_IAC  = "\xFF"; //Interpret As Command

	const OPT_TXBIN     = "\x00"; //Transmit binary, RFC856
	const OPT_ECHO      = "\x01"; //Echo, RFC857
	const OPT_SGA       = "\x03"; //Suppress Go Ahead, RFC858 (makes connection full-duplex instead of half-duplex)
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

	const STATE_DEFAULT = 0;
	const STATE_CMD = 1;

	const TELNET_ERROR = FALSE;
	const TELNET_OK = TRUE;


	private static $DEBUG = FALSE;

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


	private $host;
	private $ip_address;
	private $port;
	private $connect_timeout; //Timeout to connect to remote
	private $socket_timeout; //Timeout to wait for data

	private $socket = NULL;
	private $buffer = NULL;
	private $regex_prompt;
	private $errno;
	private $errstr;
	private $strip_prompt = TRUE;

	private $state;
	private $a_c;

	private $global_buffer = '';


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
	 * @return void
	 */
	public function __construct($host = '127.0.0.1', $port = 23, $connect_timeout = 1.0, $socket_timeout = 10.0) {
		$this->host = $host;
		$this->port = $port;

		$this->state = self::STATE_NORMAL;
		$this->connect_timeout = $connect_timeout;
		$this->socket_timeout = $socket_timeout;
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
	 * Attempts connection to remote host.
	 *
	 * @return boolean Returns TRUE if successful
	 */
	public function connect() {
		$this->ip_address = gethostbyname($this->host);

		if (filter_var($this->ip_address, FILTER_VALIDATE_IP) === FALSE) {
			throw new ErrorException("Cannot resolve $this->host");
		}

		// attempt connection - suppress warnings
		$this->socket = @fsockopen($this->ip_address, $this->port, $this->errno, $this->errstr, $this->connect_timeout);
		if ($this->socket === FALSE) {
			throw new ErrorException("Cannot connect to $this->host on port $this->port");
		}
		stream_set_blocking($this->socket, 0);

		return self::TELNET_OK;
	}


	/**
	 * Closes IP socket
	 *
	 * @return boolean
	 */
	public function disconnect() {
		if (is_resource($this->socket)) {
			if (fclose($this->socket) === FALSE) {
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
		return $this->buffer;
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
	public function login($username, $password, $login_prompt = 'login:', $password_prompt = 'Password:') {
		$prompt = $this->regex_prompt;
		try {
			$this->setPrompt($login_prompt);
			$this->waitPrompt();
			$this->write($username);
			$this->setPrompt($password_prompt);
			$this->waitPrompt();
			$this->write($password);

			//Reset prompt
			$this->regex_prompt = $prompt;

			$this->waitPrompt();
		} catch (Exception $e) {
			throw new Exception("Login failed", 0, $e);
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
		$this->regex_prompt = $str;
		return self::TELNET_OK;
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


	private function asyncGetc() {
		$c = fgetc($this->socket);
		return $c;
	}


	/**
	 * Clears internal command buffer
	 *
	 * @return void
	 */
	private function clearBuffer() {
		$this->buffer = '';
	}


	/**
	 * Reads up to $length bytes of data (TELNET commands are not counted) or wait for $this->socket_timeout seconds, whichever occurs first
	 *
	 * @param mixed $length: maximum number of data bytes to read. Either a non-negative int or NULL (infinite length)
	 *
	 * @return string the raw data read as a string
	 */
	/* FIXME: Refactor such that it is possible to also just get all received data
	 * while still processing said data in the state machine
	 */
	private function waitForData($length = NULL, &$hasTimedout) {

		if (is_null($length) && is_null($this->socket_timeout)) {
			throw new ErrorException('Would wait infinitely');
		} else if (!is_null($length) && (!is_int($length) || $length < 1)) {
			throw new InvalidArgumentException('$length must be a positive int');
		}

		$data = '';
		$endTs = microtime(TRUE) + $this->socket_timeout;
		$a_c = array();
		while ((is_null($this->socket_timeout) || microtime(TRUE) < $endTs)
				&& (is_null($length) || strlen($data) < $length)) {
			$isGetMoreData = FALSE;
			$c = $this->asyncGetc();
			if ($c === FALSE) {
				usleep(5);
				continue;
			} else {
				//Reset the timeout
				$endTs = microtime(TRUE) + $this->socket_timeout;
			}
			$a_c[] = $c;

			$isGetMoreData = $this->processStateMachine($a_c);

			if (!$isGetMoreData && count($a_c) > 0) {
				$newData = implode($a_c);
				if (self::$DEBUG) {
					print("Adding " . (ctype_print($newData) ? "\"{$newData}\"" : "(0x" . bin2hex($newData) . ")") . " to buffer\n");
					//print("Adding \"{$newData}\" (0x" . bin2hex($newData) . ") to buffer (count = " . count($a_c) . " len = " . strlen($newData) . ")\n");
					//var_dump($a_c);
				}
				$data .= $newData;
				$a_c = array();
			}
		}

		return $data;
	}


	private function processStateMachine(&$a_c) {
		$isGetMoreData = FALSE;

		switch ($this->state) {
		case self::STATE_DEFAULT:
			$isGetMoreData = $this->processStateMachineDefaultState($a_c);
			break;
		case self::STATE_CMD:
			$isGetMoreData = $this->processStateMachineCmdState($a_c);
			break;
		//case self::STATE_BINARY:
		//	break;
		//case self::STATE_OPT:
		//	break;
		//case self::STATE_NEG_NO:
		//	break;
		//case self::STATE_NEG_YES:
		//	break;
		default:
			throw new ErrorException("Unimplement state {$this->state}");
			break;
		}

		return $isGetMoreData;
	}


	private function processStateMachineDefaultState(&$a_c) {
		$isGetMoreData = FALSE;

		switch ($a_c[0]) {
		case self::CMD_IAC:
			if (count($a_c) < 2) {
				$isGetMoreData = TRUE;
				break;
			}
			$cmd = $a_c[1];
			if ($cmd === self::CMD_IAC) {
				/* Is this supposed to happen in normal mode? (Yes,
				 * "With the current set-up, only the IAC need be doubled to be sent as data" --RFC854) */

				//Add (only) one IAC character to the data
				$isGetMoreData = FALSE;
				$a_c = array(self::CMD_IAC);

			} else {
				$this->state = self::STATE_CMD;
			}
			break;

		case self::NVT_CR:
			if (count($a_c) < 2) {
				$isGetMoreData = true;
			} else {
				switch ($a_c[1]) {
				case self::NVT_LF:
					//Replace <CR> <LF> by "\n" (only in STATE_DEFAULT)
					$a_c = array("\n");
					break;
				}
			}
			break;
		default:
			//Pass, raw data
		}

		return $isGetMoreData;
	}


	private function processStateMachineCmdState(&$a_c) {
		$isGetMoreData = FALSE;

		if (count($a_c) < 3) {
			//Get more data
			$isGetMoreData = TRUE;

		} else {
			$opt = $a_c[2];
			$replyCmd = NULL;
			switch ($cmd) {
			case self::CMD_SB:
				if ($opt === self::CMD_SE) {
					//Empty subnegotiation?! (pass)
				} else if (end($a_c) !== self::CMD_SE) {
					//Get more data
					$isGetMoreData = TRUE;
				} else {
					//TODO: Handle subnegotiation here
					if (self::$DEBUG) {
						print("Silently dropping subnegotiation (to be implemented)\n");
					}
				}
				break;

			//TODO: Handle other commands
			case self::CMD_DO: //FALLTHROUGH
			case self::CMD_DONT:
				$replyCmd = self::CMD_WONT;
				break;

			case self::CMD_WILL:
				$replyCmd = self::CMD_DONT;
				break;
			case self::CMD_WONT:
				//Pass, we are not supposed to "acknowledge" WONTs
				break;

			default:
				if (self::$DEBUG) {
					print('Ignoring unknown command character 0x' . bin2hex($cmd) . "\n");
				}
			}

			if (!is_null($replyCmd)) {
				fwrite($this->socket, self::CMD_IAC . $replyCmd . $opt);

				if (self::$DEBUG) {
					$str = sprintf("[CMD %s]", self::getCmdStr($cmd));
					$str .= sprintf("[OPT %s]", self::getOptStr($opt));
					print($str . "\n");
				}
			}
			if (!$isGetMoreData) {
				$a_c = array();
				$this->state = self::STATE_DEFAULT;
			}
		}

		return $isGetMoreData;
	}


	/**
	 * Write command to a socket
	 *
	 * @param string $buffer Stuff to write to socket
	 * @param boolean $add_newline Default TRUE, adds newline to the command
	 * @return boolean
	 */
	protected function write($buffer, $add_newline = TRUE) {
		if (!is_resource($this->socket)) {
			throw new Exception("Telnet connection closed");
		}

		// clear buffer from last command
		$this->clearBuffer();

		if ($add_newline) {
			$buffer .= self::NVT_CR . self::NVT_LF;
		}

		/*
		 * FIXME: This is dubious: why not rely on DO ECHO?
		 * (Admittedly the original code WONT/DONT all options and my test servers don't respect DONT ECHO)
		 */
		//FIXME: Allow toggling this
		//FIXME: This also gets a pass at the <CR> <LF> filtering, which is bad
		/* FIXME: This also doesn't respect the order of things:
		 * since we return as soon as the prompt is found (regardless of whether or not more characters have been received or not), appending to the global buffer means those characters get written after (while they were received before)
		 */
		$this->global_buffer .= $buffer;
		$ret = fwrite($this->socket, $buffer);
		if ($ret !== strlen($buffer)) { //|| $ret === FALSE) {
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
	 * Reads socket until prompt is encountered
	 */
	protected function waitPrompt() {
		if (self::$DEBUG) {
			print("\nWaiting for prompt \"{$this->regex_prompt}\"\n");
		}

		$this->clearBuffer();
		do {
			$data = $this->waitForData(1, $hasTimedout);
			$this->buffer .= $data;
			$this->global_buffer .= $data;

			if ($hasTimedout) {
				throw new ErrorException("Connection timed out");
			}
		} while (preg_match("/{$this->regex_prompt}$/", $this->buffer) == 0);
	}
}
