<?php

namespace cli\description;

class PharDescription extends ShaDescription{
	public function getGithubZipballurl() : string{
		return "https://github.com/".$this->getGithubRepoName()."/blob/".$this->getGithubCommitsha()."/".$this->getUrlPath()."?raw=true";
	}

	public function getCacheFile() : string{
		return $this->getName()."-".$this->getCacheName().".phar";
	}

	public function getRootPath() : string{
		$cachedir = $this->getCachePath();
		if($cachedir === null){
			throw new \LogicException("\$this->getCacheName() === null");
		}
		return "phar://".$cachedir.DIRECTORY_SEPARATOR.$this->getZipPath();
	}
}