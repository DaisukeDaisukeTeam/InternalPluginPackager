<?php

namespace cli\description;

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

	abstract public static function CheckFormat(string $require, string $version) : bool;

	abstract public static function init(string $require, string $version) : static;

	public const TYPE_NORMAL = 0;//github
	public const TYPE_LIBRARY = 1;

	/** @var LibraryEntry[] $library */
	protected array $library = [];
	protected string $main;
	protected string $pharPath;

	public function getMain() : string{
		return $this->main;
	}

	public function setMain(string $main) : void{
		$this->main = $main;
	}

	protected string $projectPath;
	protected int $type = self::TYPE_LIBRARY;

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

	public function getType() : int{
		return $this->type;
	}

	public function setType(int $type) : void{
		$this->type = $type;
	}

	public function getPharPath() : string{
		return $this->pharPath;
	}

	public function setPharPath(string $pharPath) : void{
		$this->pharPath = $pharPath;
	}
}