<?php

namespace cli\description;

class ShaDescription implements DescriptionInterface{

	public string $owner;
	public string $repositoryName;
	public string $commitsha;

	/**
	 * @param string $owner
	 * @param string $repositoryName
	 * @param ?string $commitsha
	 */
	public function __construct(string $owner, string $repositoryName, ?string $commitsha = null){
		$this->owner = $owner;
		$this->repositoryName = $repositoryName;
		$this->commitsha = $commitsha;
	}

	public function getGithubRepoName() : string{
		return $this->owner.'/'.$this->repositoryName;
	}

	public function getName() : string{
		return $this->repositoryName;
	}

	public function getGithubCommitsha() : ?string{
		return $this->commitsha;
	}

	public function getGithubZipballurl() : string{
		return "https://github.com/".$this->getGithubRepoName()."/archive/".$this->getGithubCommitsha().".zip";
	}

	public function getManifestContents() : string{
		//$url = "/repos/".$this->getGithubRepoName()."/contents/.poggit.yml";
	}

	public function getUrlVersion() : ?string{
		return null;
	}

	public function getVersion() : string{
		return $this->getGithubCommitsha();
	}

	public function getCacheName() : string{
		return substr($this->getGithubCommitsha(), 0, 20);
	}

	public static function CheckFormat(string $require, string $version) : bool{
		return preg_match("/[a-zA-Z0-9]*\/[a-zA-Z0-9]*/u", $require)&&preg_match('/^[0-9a-f]{40}$/', $version);
	}

	public static function init(string $require, string $version) : static{
		$array = explode("/", $require);
		return new self($array[0], $array[1], $version);
	}
}