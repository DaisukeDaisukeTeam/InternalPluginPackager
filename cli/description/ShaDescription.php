<?php

namespace cli\description;

use cli\exception\CatchableException;
use cli\http;

class ShaDescription extends DescriptionBase{
	protected string $owner;
	protected string $repositoryName;
	protected string $commitsha;
	protected ?string $cachePath;

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

	public function getPluginManifestPath() : string{
		return $this->getRootPath()."plugin.yml";
	}

	public function getManifestPath() : string{
		return $this->getRootPath().".poggit.yml";
	}

	public function getRootPath() : string{
		$cachedir = $this->getCachePath();
		if($cachedir === null){
			throw new \LogicException("\$this->getCacheName() === null");
		}
		$path = "phar://".$cachedir.DIRECTORY_SEPARATOR.$this->getZipInternalPath().DIRECTORY_SEPARATOR.$this->getZipPath();
		if(!isset($this->zipInternalPath)&&!is_dir($path)){
			$this->setZipInternalPath(scandir("phar://".$cachedir));
			return $this->getRootPath();
		}
		return $path;
	}

	public function getUrlVersion() : ?string{
		return null;
	}

	public function getVersion() : string{
		return $this->getGithubCommitsha();
	}

	public function getCacheName() : string{
		return substr($this->getGithubCommitsha(), 0, 10);
	}

	public function getCachePath() : ?string{
		return $this->cachePath;
	}

	public function setCachePath(?string $cachePath) : void{
		$this->cachePath = $cachePath;
	}

	public static function isValidFormat(string $require, string $version) : bool{
		return preg_match("/[a-zA-Z0-9]*\/[a-zA-Z0-9]*/u", $require);
	}

	public static function init(http $http, string $require, string $version){
		$version = self::convertsha($http, $require, $version);
		$array = explode("/", $require);
		return new static($array[0], $array[1], $version);
	}

	public static function convertsha(http $http, string $require, string $version) : string{
		if(preg_match('/^[0-9a-f]{40}$/', $version)){
			return $version;
		}
		$branches = $http->get("https://api.github.com/repos/".$require."/branches");
		$http->writeCache();
		$found = false;
		foreach($branches as $index => $array){
			if($array["name"] === $version){
				$version = $array["commit"]["sha"];//commit sha
				$found = true;
			}
		}
		if($found === false){
			$suggestion = implode(PHP_EOL, array_column($branches, "name"));
			throw new CatchableException("branch ".$version." not found.\nsuggestion\n".$suggestion);
		}
		return $version;
	}
}
