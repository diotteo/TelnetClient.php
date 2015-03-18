#! /usr/bin/env php
<?php


class Text {
	public $string;
}


class EscapeSequence {
	public $string;
}


class AnsiEscapeSequenceParser {
	const TEXT = 0;
	const ESCAPE = 1;

	private $tokenList;


	public function __construct() {
		$tokenList = array();
	}


	private static function escapeSequenceFsm(&$a_c, &$isEscapeSequence) {
		$getMoreData = false;
		$isEsc = false;

		switch ($a_c[0]) {
		case "\x1B":
			if (count($a_c) < 2) {
				$getMoreData = true;
				break;
			}
			
			switch ($a_c[1]) {
			case '[':
				if (count($a_c) < 3) {
					$getMoreData = true;
					break;
				}
				switch ($a_c[2]) {
				case '=':
					if (count($a_c) < 5) {
						$getMoreData = true;
						break;
					}
					switch ($a_c[4]) {
					//set mode (Esc[=<value>h)
					case 'h': //FALLTHROUGH
					//reset mode (Esc[=<value>l)
					case 'l':
						$isEsc = true;
						break;
					}
					break;
				//Save cursor position (Esc[s)
				case 's': //FALLTHROUGH
				//Restore cursor position (Esc[u)
				case 'u': //FALLTHROUGH
				//Erase line (Esc[K)
				case 'K':
					$isEsc = true;
					break;
				case '2':
					if (count($a_c) < 3) {
						$getMoreData = true;
						break;
					}
					if ($a_c[3] === 'J') {
						$isEsc = true;
					}
					break;
				default:
					if (count($a_c) < 4) {
						$getMoreData = true;
						break;
					}
					switch ($a_c[3]) {
					case 'A':
					case 'B':
					case 'C':
					case 'D':
					//case 'm': //FIXME: m is _probably_ valid here
						$isEsc = true;
						break;

					/* Cursor position (Esc[<line>;<column>H)
					 * Cursor position (Esc[<line>;<column>f)
					 *
					 */
					case ';':
						switch ($a_c[4]) {
						case '"':
							if (count($a_c) < 5) {
								$getMoreData = true;
								break;
							}
							$len = count($a_c);
							break;
						default:
							//Skip count() === 5, no valid escape code with length 5
							if (count($a_c) < 6) {
								$getMoreData = true;
								break;
							}
							switch ($a_c[5]) {
							//Cursor position
							case 'H': //FALLTHROUGH
							case 'f':
								$isEsc = true;
								break;
							default:
								$len = count($a_c);
								if (!($len % 2) || $a_c[$len - 1] === ';') {
									$getMoreData = true;
									break;
								}
								if ($a_c[$len - 1] === 'm') {
									$isEsc = true;
								}
							}
						}
					}
				}
				break;
			}
			
		}

		$isEscapeSequence = $isEsc;
		return $getMoreData;
	}


	public function parse($s) {
		$this->buffer = '';
		$this->state = null;

		for ($i = 0; $i < strlen($s); $i++) {
			escapeSequenceFsm($this->buffer, $s[$i]);
			if ($s[$i] === "\x1B"
					&& $s[$i+1] === '[') {

				switch ($s[$i+2]) {
				case '=':
					switch ($s[$i+4]) {
					//set mode (Esc[=<value>h)
					case 'h': //FALLTHROUGH
					//reset mode (Esc[=<value>l)
					case 'l':
						$escLen = 4;
						break;
					}
					break;
				//Save cursor position (Esc[s)
				case 's': //FALLTHROUGH
				//Restore cursor position (Esc[u)
				case 'u': //FALLTHROUGH
				//Erase line (Esc[K)
				case 'K':
					$escLen = 2;
					break;
				case '2':
					if ($s[$i+3] === 'J') {
						$escLen = 3;
					}
					break;
				default:
					switch ($s[$i+3]) {
					case 'A':
					case 'B':
					case 'C':
					case 'D':
						$escLen = 3;
						break;

					/* Cursor position (Esc[<line>;<column>H)
					 * Cursor position (Esc[<line>;<column>f)
					 *
					 */
					case ';':
					}
				}
			}
			
			if (!$isEscape) {
				$buf .= $s[$i];
			} else {
				$tokenList[] = $buf;
				$buf = '';
			}
		}

		return $out;
	}
}

?>
