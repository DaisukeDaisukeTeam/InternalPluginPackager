<?php

namespace cli\description;

use cli\exception\CatchableException;
use cli\http;

class ApiDescription extends DescriptionBase{
	public array $data;
	public ?string $cachePath;

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

	public function getPluginManifestPath() : string{
		return $this->getRootPath()."/plugin.yml";
	}

	public function getManifestPath() : string{
		///repos/{owner}/{repo}/contents/{path}
		return "/repos/".$this->getGithubRepoName()."/contents/poggit.yml";
	}

	public function getRootPath() : string{
		$cachedir = $this->getCachePath();
		if($cachedir === null){
			throw new \LogicException("\$this->getCacheName() === null");
		}
		$path = "phar://".$cachedir.DIRECTORY_SEPARATOR.$this->getZipInternalPath().DIRECTORY_SEPARATOR.$this->getZipPath();
		if(!isset($this->zipInternalPath)&&!is_dir($path)){
			$result = scandir("phar://".$cachedir);
			if(count($result) !== 1){
				throw new \RuntimeException("count(\$result) !== 1");
			}
			$this->setZipInternalPath($result[0]);
			return $this->getRootPath();
		}
		return $path;
	}

	public function getUrlVersion() : ?string{
		return null;
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
		// TODO: Implement CheckFormat() method.
	}

	public static function init(http $http, string $require, string $version) : static{
		$result = $http->search($require);
		$http->writeCache();
		if(count($result) === 0){
			throw new CatchableException("plugin 「".$require."」not found.");
		}
		$data = null;
		$similars = [];
		if($version === "*"||$version === "latest"){
			/*$id = -1;
			while(isset($result[++$id]["state_name"])&&$result[$id]["state_name"] !== "Approved");
			if(isset($result[$id])){
				$data = $result[$id];
			}*/
			$data = $result[0] ?? null;
		}else{
			foreach($result as $array){
				if(strtolower($array["version"]) === strtolower($version)){
					$data = $array;
					break;
				}

				if(str_starts_with(strtolower($array["version"]), strtolower($version))){
					$similars[] = $array["version"];
				}
			}
		}

		if($data === null){
			$suggestion = "";
			if(count($similars) !== 0){
				$suggestion = "\nsuggested version: ".implode(" ", $similars);
			}
			throw new CatchableException("「".$require."」version 「".$version."」not found.".$suggestion);
		}

		return new self($data);
	}

	public function getVersion() : string{
		// TODO: Implement getVersion() method.
	}
}