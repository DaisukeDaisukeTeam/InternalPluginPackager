<?php

namespace cli;

use cli\interface\DescriptionInterface;

class UrlDescription implements DescriptionInterface{
	public string $owner;
	public string $repositoryName;
	public ?string $commitsha;
	public ?string $version;

	/**
	 * @param string $owner
	 * @param string $repositoryName
	 * @param string $version
	 * @param ?string $commitsha
	 */
	public function __construct(string $owner, string $repositoryName, ?string $version, ?string $commitsha = null){
		$this->owner = $owner;
		$this->repositoryName = $repositoryName;
		$this->version = $version;
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
		//https://api.github.com/repos/{repo_name}/{project_name}/zipball/{tag_name}
		if($this->getUrlVersion() === null&&$this->getGithubCommitsha() !== null){
			return "https://github.com/".$this->getGithubRepoName()."/archive/".$this->getGithubCommitsha().".zip";
		}
		return "/repos/".$this->getGithubRepoName()."/zipball/".$this->getUrlVersion();
	}

	public function getManifestContentsUrl() : string{
		///repos/{owner}/{repo}/contents/{path}
		return "/repos/".$this->getGithubRepoName()."/contents/poggit.yml";
	}

	public function getUrlVersion() : ?string{
		return $this->version;
	}

	public function getCacheName() : string{
		return $this->getUrlVersion() ?? substr($this->getGithubCommitsha(), 0, 10);
	}

//	protected function getBranchVersion() : string{
//		return $this->branch_version;
//	}
}