<?php

namespace cli\description;

use cli\http;

class BranchDescription extends ShaDescription{
	public static function isValidFormat(string $require, string $version) : bool{
		return preg_match("/[a-zA-Z0-9]*\/[a-zA-Z0-9]*/u", $require)&&preg_match("/[a-zA-Z0-9]*/", $version);
	}

	public static function init(http $http, string $require, string $version){
		$array = explode("/", $require);
		return new ShaDescription($array[0], $array[1], $version);
	}
}