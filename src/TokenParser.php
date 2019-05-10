<?php

namespace Phptestgen;

class TokenParser
{
	private $tokens;

	private $tokens_by_line;

	public function __construct() {}

	public function parseCode(string $php_code)
	{
		$this->tokens = token_get_all($php_code);
	}

	public function getTokensByLine()
	{
		if (!isset($this->tokens_by_line)) {
			$this->tokens_by_line = $this->parseTokensByLine($this->tokens);
		}

		return $this->tokens_by_line;
	}

	private function parseTokensByLine(array $tokens)
	{
		$tokens_by_line = [];

		foreach($tokens as $token_index => $token_value) {
			if (isset($token_value[2])) {
				$token_line_number = $token_value[2];
				if (!isset($tokens_by_line[$token_line_number])) {
					$tokens_by_line[$token_line_number] = [ $token_value ];
				} else {
					$tokens_by_line[$token_line_number][] = $token_value;
				}
			}
		}

		return $tokens_by_line;
	}

	public function getMethods()
	{
		$tokens_by_line = $this->getTokensByLine();

		$methods = [];

		foreach ($tokens_by_line as $token_line) {
			$is_func = array_search(T_FUNCTION, array_column($token_line, 0));

			if ($is_func !== false) {
				foreach ($token_line as $token) {

				}
			}
		}

		return $methods;
	}
}