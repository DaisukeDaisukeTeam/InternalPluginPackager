<?php

namespace cli\description;

class BranchDescription implements DescriptionInterface{

	public string $owner;
	public string $repositoryName;
	public string $version;

	/**
	 * @param string $owner
	 * @param string $repositoryName
	 * @param ?string $version
	 */
	public function __construct(string $owner, string $repositoryName, ?string $version = null){
		$this->owner = $owner;
		$this->repositoryName = $repositoryName;
		$this->version = $version;
	}


	public function getGithubRepoName() : string{
		return $this->owner.'/'.$this->repositoryName;
	}

	public function getName() : string{
		return $this->repositoryName;
	}

	public function getGithubCommitsha() : ?string{
		return null;
	}

	public function getGithubZipballurl() : string{
		return "/repos/".$this->getGithubRepoName()."/zipball/".$this->getUrlVersion();
	}

	public function getManifestContents() : string{
		//return "/repos/".$this->getGithubRepoName()."/contents/poggit.yml?ref=".$this->getUrlVersion();
	}

	public function getUrlVersion() : ?string{
		return $this->getVersion();
	}

	public function getVersion() : string{
		return $this->version;
	}

	public function getCacheName() : string{
		return $this->getVersion();
	}

	public static function CheckFormat(string $require, string $version) : bool{
		return preg_match("/[a-zA-Z0-9]*\/[a-zA-Z0-9]*/u", $require)&&preg_match("/[a-zA-Z0-9]*/", $version);
	}

	public static function init(string $require, string $version) : static{
		$array = explode("/", $require);
		return new self($array[0], $array[1], $version);
	}

}