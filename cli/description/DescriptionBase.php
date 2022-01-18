<?php

namespace cli\description;

use cli\http;
use cli\LibraryEntry;

abstract class DescriptionBase{
	abstract public function getGithubRepoName() : string;

	abstract public function getName() : string;

	abstract public function getGithubCommitsha() : ?string;

	abstract public function getGithubZipballurl() : string;

	abstract public function getPluginManifestPath() : string;

	abstract public function getManifestPath() : string;

	abstract public function getRootPath() : string;

	abstract public function getUrlVersion() : ?string;

	abstract public function getVersion() : string;

	abstract public function getCacheName() : string;

	abstract public function getCachePath() : ?string;

	abstract public function setCachePath(?string $cachePath);

	abstract public static function isValidFormat(string $require, string $version) : bool;

	/**
	 * @param string $require
	 * @param string $version
	 * @return DescriptionBase
	 */
	abstract public static function init(http $http, string $require, string $version);

	/** @var LibraryEntry[] $library */
	protected array $library = [];
	protected string $main;
	protected string $pharPath;
	protected string $zipPath = "";
	protected string $urlPath = "";
	protected ?string $zipInternalPath = null;

	public function getMain() : string{
		return $this->main;
	}

	public function setMain(string $main) : void{
		$this->main = $main;
	}

	protected string $projectPath;
	protected string $type = DescriptionType::TYPE_LIBRARY;

	public function addLibraryEnty(LibraryEntry $library) : void{
		$this->library[] = $library;
	}

	/**
	 * @return LibraryEntry[]
	 */
	public function getLibraryEntries() : array{
		return $this->library;
	}

	public function getProjectPath() : string{
		return $this->projectPath;
	}

	public function setProjectPath(string $projectPath) : void{
		$this->projectPath = $projectPath;
	}

	public function getType() : string{
		return $this->type;
	}

	public function setType(string $type) : void{
		$this->type = $type;
	}

	public function getPharPath() : string{
		return $this->pharPath;
	}

	public function setPharPath(string $pharPath) : void{
		$this->pharPath = $pharPath;
	}

	public function getZipPath() : string{
		return $this->zipPath;
	}

	public function setZipPath(string $path) : void{
		if(trim($path) === ""){
			return;
		}
		$this->zipPath = $path;
		if($this->zipPath[-1] !== "/"||$this->zipPath[-1] !== "\\"){
			$this->zipPath .= DIRECTORY_SEPARATOR;
		}
	}

	public function getCacheFile() : string{
		return $this->getName()."-".$this->getCacheName().".zip";
	}

	public function getUrlPath() : string{
		return $this->urlPath;
	}

	public function setUrlPath(string $urlPath) : void{
		$this->urlPath = $urlPath;
	}

	public function setZipInternalPath(string $zipInternalPath) : void{
		$this->zipInternalPath = $zipInternalPath;
	}

	public function getZipInternalPath() : string{
		if(isset($this->zipInternalPath)){
			return $this->zipInternalPath;
		}
		return $this->getName()."-".$this->getGithubCommitsha();
	}
}