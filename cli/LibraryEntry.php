<?php

namespace cli;

class LibraryEntry{
	protected string $library;
	protected string $version;
	protected string $branch;

	/**
	 * @param string $library
	 * @param string $version
	 * @param string $branch
	 */
	public function __construct(string $library, string $version, string $branch){
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

	/**
	 * @return string
	 */
	public function getVersion() : string{
		return $this->version;
	}

	/**
	 * @return string
	 */
	public function getBranch() : string{
		return $this->branch;
	}
}