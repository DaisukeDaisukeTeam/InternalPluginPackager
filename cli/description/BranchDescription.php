<?php

namespace cli\description;

class BranchDescription implements DescriptionInterface{

	public function getGithubRepoName() : string{
		// TODO: Implement getGithubRepoName() method.
	}

	public function getName() : string{
		// TODO: Implement getName() method.
	}

	public function getGithubCommitsha() : ?string{
		// TODO: Implement getGithubCommitsha() method.
	}

	public function getGithubZipballurl() : string{
		// TODO: Implement getGithubZipballurl() method.
	}

	public function getManifestContentsUrl() : string{
		// TODO: Implement getManifestContentsUrl() method.
	}

	public function getUrlVersion() : ?string{
		// TODO: Implement getUrlVersion() method.
	}

	public function getCacheName() : string{
		// TODO: Implement getCacheName() method.
	}

	public static function CheckFormat(string $require, string $version) : bool{
		return preg_match("/[a-zA-Z0-9]*\/[a-zA-Z0-9]*/u", $require)&&preg_match("/[a-zA-Z0-9]*/", $version);
	}

	public static function init(string $require, string $version) : static{
		// TODO: Implement init() method.
	}
}