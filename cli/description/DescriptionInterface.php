<?php

namespace cli\description;

interface DescriptionInterface{
	public function getGithubRepoName() : string;

	public function getName() : string;

	public function getGithubCommitsha() : ?string;

	public function getGithubZipballurl() : string;

	public function getManifestPath() : string;

	public function getUrlVersion() : ?string;

	public function getVersion() : string;

	public function getCacheName() : string;

	public function getCachePath() : ?string;

	public function setCachePath(?string $cachePath);

	public static function CheckFormat(string $require, string $version) : bool;

	public static function init(string $require, string $version) : static;
}