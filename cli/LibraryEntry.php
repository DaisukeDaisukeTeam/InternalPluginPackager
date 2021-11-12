<?php

namespace cli;

class LibraryEntry{
	protected string $name;
	protected string $library;
	protected string $version;
	protected string $branch;
	protected string $pharPath;

	/**
	 * @param string $name
	 * @param string $library
	 * @param string $version
	 * @param string $branch
	 */
	public function __construct(string $name, string $library, string $version, string $branch){
		$this->name = $name;
		$this->library = $library;
		$this->version = $version;
		$this->branch = $branch;
	}

	/**
	 * @return string
	 */
	public function getLibrary() : string{
		return $this->library;
	}

	public function getVersion() : string{
		return $this->version;
	}

//	public function setVersion(string $version) : void{
//		$this->version = $version;
//	}

	public function getBranch() : string{
		return $this->branch;
	}

	public function getPharName() : string{
		return $this->library."_v1".strstr($this->getVersion(), ".", true).".phar";
	}

	public function getName() : string{
		return $this->name;
	}

	public function setPharPath(string $path){
		$this->pharPath = $path;
	}

	public function getPharPath() : string{
		return $this->pharPath;
	}

	public function getCacheName() : string{
		return trim($this->getLibrary())."/".trim($this->getVersion())."/".trim($this->getBranch());
	}
}