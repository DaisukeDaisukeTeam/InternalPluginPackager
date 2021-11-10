<?php

namespace cli\description;

interface DescriptionInterface{
	public function getGithubRepoName() : string;

	public function getName() : string;

	public function getGithubCommitsha() : ?string;

	public function getGithubZipballurl() : string;

	public function getManifestContentsUrl() : string;

	public function getUrlVersion() : ?string;

	public function getCacheName() : string;

	public static function CheckFormat(string $require, string $version) : bool;

	public static function init(string $require, string $version) : static;
}