<?php

namespace cli\description;

class ApiDescription implements DescriptionInterface{
	public array $data;

	public function __construct(array $data){
		$this->data = $data;
	}

	public function getGithubRepoName() : string{
		return $this->data["repo_name"];
	}

	public function getName() : string{
		return $this->data["project_name"];
	}

	public function getGithubCommitsha() : ?string{
		return $this->data["build_commit"];
	}

	public function getGithubZipballurl() : string{
		//https://api.github.com/repos/{repo_name}/{project_name}/zipball/{tag_name}
		return "/repos/".$this->getGithubRepoName()."/zipball/".$this->getGithubCommitsha();
	}

	public function getManifestContentsUrl() : string{
		///repos/{owner}/{repo}/contents/{path}
		return "/repos/".$this->getGithubRepoName()."/contents/poggit.yml";
	}

	public function getUrlVersion() : ?string{
		return null;
	}

	public function getCacheName() : string{
		return substr($this->getGithubCommitsha(), 0, 10);
	}

	public static function CheckFormat(string $require, string $version) : bool{
		// TODO: Implement CheckFormat() method.
	}

	public static function init(string $require, string $version) : static{
		// TODO: Implement init() method.
	}
}