<?php

class Sequence {
	protected $string;
	protected $complete;


	public function __construct($string, $complete = true) {
		$this->string = $string;
		$this->complete = $complete;
	}


	public function getString() {
		return $this->string;
	}
}


class TextSequence extends Sequence {
	//Always complete
	public function __construct($string, $complete = true) {
		parent::__construct($string, true);
	}
}


class EscapeSequence extends Sequence {
}


class ControlSequence extends Sequence {
}


class AnsiAsciiControlParser {
	const TEXT = 0;
	const ESCAPE = 1;
	const CONTROL = 2;

	private $sequenceList;


	public function __construct() {
		$this->sequenceList = array();
	}


	public function getSequenceList() {
		return $this->sequenceList;
	}


	public function getTextString() {
		$s = '';
		foreach ($this->sequenceList as $sequence) {
			if ($sequence instanceof TextSequence) {
				$s .= $sequence->getString();
			}
		}

		return $s;
	}


	public function getFullString() {
		$s = '';
		foreach ($this->sequenceList as $sequence) {
			$s .= $sequence->getString();
		}

		return $s;
	}


	private static function getEscCode($c) {
		return $c & 0x7F; //Escape sequences are treated as 7 bits (to be verified)
	}


	public function parse($s) {
		$this->sequenceList = array();

		$state = self::TEXT;
		$buffer = '';
		for ($i = 0; $i < strlen($s); $i++) {
			$escc = $s[$i];
			switch ($escc) {
			case "\x1B":
				switch ($state) {
				case self::ESCAPE:
					$this->sequenceList[] = new EscapeSequence($buffer, false);
					break;
				case self::CONTROL:
					$this->sequenceList[] = new ControlSequence($buffer, false);
					break;
				case self::TEXT:
					if (strlen($buffer) > 0) {
						$this->sequenceList[] = new TextSequence($buffer);
					}
					break;
				default:
					throw new ErrorException("Unknown state {$state}");
				}
				$buffer = '';

				if (strlen($s) > $i + 1 && $s[$i + 1] === "\x5B") {
					$state = self::CONTROL;
					$buffer .= $s[$i];
					$i++;
				} else {
					$state = self::ESCAPE;
				}

				$buffer .= $s[$i];
				break;

			default:
				$buffer .= $s[$i];

				$cval = ord($s[$i]);
				switch ($state) {
				case self::ESCAPE:
					if (0x30 <= $cval && $cval <= 0x7E) {
						$this->sequenceList[] = new EscapeSequence($buffer, true);
						$buffer = '';
						$state = self::TEXT;
					}
					break;
				case self::CONTROL:
					if (0x40 <= $cval && $cval <= 0x7E) {
						$this->sequenceList[] = new ControlSequence($buffer, true);
						$buffer = '';
						$state = self::TEXT;
					}
					break;
				}
			}
		}

		switch ($state) {
		case self::TEXT:
			if (strlen($buffer) > 0) {
				$this->sequenceList[] = new TextSequence($buffer);
			}
			break;
		case self::ESCAPE:
			$this->sequenceList[] = new EscapeSequence($buffer, false);
			break;
		case self::CONTROL:
			$this->sequenceList[] = new ControlSequence($buffer, false);
			break;
		default:
			throw new ErrorException("Unknown state {$state}");
		}
	}
}

?>
