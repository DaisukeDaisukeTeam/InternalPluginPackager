<?php

namespace cli;

class SimpleLogger{
	public function info(string $message) : void{
		echo $message.PHP_EOL;
	}

	public function infoWithoutNewLine(string $message) : void{
		echo $message;
	}

	public function requestInput(string $message) : string{
		echo $message;
		return trim((string) fgets(STDIN));
	}

	public function warning(string $message) : void{
		echo $message.PHP_EOL;
	}

	public function error(string $message) : void{
		echo $message.PHP_EOL;
	}
}
