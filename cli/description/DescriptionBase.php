<?php

namespace cli\description;

use cli\LibraryEntry;

abstract class DescriptionBase{
	abstract public function getGithubRepoName() : string;

	abstract public function getName() : string;

	abstract public function getGithubCommitsha() : ?string;

	abstract public function getGithubZipballurl() : string;

	abstract public function getManifestPath() : string;

	abstract public function getUrlVersion() : ?string;

	abstract public function getVersion() : string;

	abstract public function getCacheName() : string;

	abstract public function getCachePath() : ?string;

	abstract public function setCachePath(?string $cachePath);

	abstract public static function CheckFormat(string $require, string $version) : bool;

	abstract public static function init(string $require, string $version) : static;

	/** @var LibraryEntry[] $library */
	protected array $library = [];

	protected string $projectPath;


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
}