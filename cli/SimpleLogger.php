<?php

namespace cli;

class SimpleLogger{
	public function info(string $message) : void{
		echo $message.PHP_EOL;
	}

	public function error(string $message) : void{
		echo $message.PHP_EOL;
	}
}
