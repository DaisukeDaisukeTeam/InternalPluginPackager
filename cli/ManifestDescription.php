<?php

namespace cli;

class ManifestDescription{
	/** @var array<string, mixed|mixed[]> $manifest */
	public array $manifest;

	/**
	 * @param array<string, mixed|mixed[]> $manifest
	 */
	public function __construct(array $manifest){
		$this->manifest = $manifest;
	}

	/**
	 * @return string[]
	 */
	public function getLibs() : array{
		return $this->manifest["libs"];
	}
}