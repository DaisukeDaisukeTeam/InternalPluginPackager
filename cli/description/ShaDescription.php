<?php

namespace cli\description;

class ShaDescription implements DescriptionInterface{

	public string $owner;
	public string $repositoryName;
	public string $commitsha;
	public ?string $cachePath;

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

	public function getManifestPath() : string{
		$cachedir = $this->getCachePath();
		if($cachedir === null){
			throw new \LogicException("\$this->getCacheName() === null");
		}
		var_dump($cachedir);
		return "phar://".$cachedir."/".$this->getName()."-".$this->getGithubCommitsha()."/.poggit.yml";
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

	public function getCachePath() : ?string{
		return $this->cachePath;
	}

	public function setCachePath(?string $cachePath) : void{
		$this->cachePath = $cachePath;
	}

	public static function CheckFormat(string $require, string $version) : bool{
		return preg_match("/[a-zA-Z0-9]*\/[a-zA-Z0-9]*/u", $require)&&preg_match('/^[0-9a-f]{40}$/', $version);
	}

	public static function init(string $require, string $version) : static{
		$array = explode("/", $require);
		return new self($array[0], $array[1], $version);
	}
}