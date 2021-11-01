<?php

namespace cli\interface;

interface DescriptionInterface{
	public function getGithubRepoName() : string;

	public function getName() : string;

	public function getGithubCommitsha() : ?string;

	public function getGithubZipballurl() : string;

	public function getManifestContentsUrl() : string;

	public function getUrlVersion() : ?string;

	public function getCacheName() : string;
}