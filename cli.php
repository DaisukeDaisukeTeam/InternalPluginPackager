<?php

use cli\ApiDescription;
use cli\http;
use cli\interface\DescriptionInterface;
use cli\SimpleLogger;
use cli\UrlDescription;

$dir = __DIR__.DIRECTORY_SEPARATOR;

spl_autoload_register(function($class) use ($dir){
	include_once $dir.$class.'.php';
});

class cli{
	public SimpleLogger $logger;
	public http $http;
	public string $dir;

	/**
	 * @throws JsonException
	 */
	public function __construct(){
		$this->dir = getcwd().DIRECTORY_SEPARATOR;

		$this->logger = new SimpleLogger();
		$this->http = new http($this->dir);
	}

	public function main(array $argv) : void{
		if(count($argv) === 1){
			$this->getLogger()->info("usage: cli.php install|require");
			return;
		}

		if($argv[1] === "install"){

		}

		if($argv[1] === "require"){
			if(count($argv) < 3){
				$this->getLogger()->info('usage: cli.php require multiworld');
				$this->getLogger()->info('usage: cli.php require https://github.com/DaisukeDaisukeTeam/BuyLand');
				$this->getLogger()->info('usage: cli.php require https://github.com/DaisukeDaisukeTeam/BuyLand/tree/test');
				return;
			}
			$require = $argv[2];
			$version = $argv[3] ?? null;
			$plugins = $this->requirePlugin($require, $version);
			$descriptions = $this->LookingPlugins($plugins);
			$this->downloadZipball($descriptions);
			var_dump($descriptions);
			return;
		}
	}

	/**
	 * @param mixed[] $plugins
	 * @phpstan-param array<string, string> $plugins
	 * @return DescriptionInterface[]
	 */
	public function LookingPlugins(array $plugins) : array{
		$descriptions = [];
		foreach(($plugins["require"] ?? []) as $require => $version){
			/*
			https://github.com/DaisukeDaisukeTeam/BuyLand/
			https://github.com/pmmp/DevTools/blob/1.14/src/FolderPluginLoader/FolderPluginLoader.php
			https://github.com/pmmp/DevTools/tree/3d0f277182f7dda1bf617b3c55b1df40f625eccd/src
			https://github.com/DaisukeDaisukeTeam/StaffMode/tree/add-license-1
			https://github.com/pmmp/DevTools/releases/tag/1.0.0
			https://github.com/pmmp/PocketMine-MP/releases/latest/download/PocketMine-MP.phar
			multiworld
			*/
			if(preg_match('/^http[s]?\:\/\/github\.com\/([^\/\n_]*)\/([^\/\n_]*)\/?(tree|blob|releases\/tag|releases)?\/?([^\/\n_]*)?\/?/', $require, $m)){
				[$match, $owner, $repositoryName, $identifier, $branch_version] = $m;

				if($branch_version === ""){
					$this->getLogger()->info("looking ".$owner."/".$repositoryName." latest");
				}else{
					$this->getLogger()->info("looking ".$owner."/".$repositoryName." ".$branch_version);
				}

				if($branch_version === ""){
					$this->getLogger()->info("search default branch...");
					$result = $this->getHttp()->get("/repos/CzechPMDevs/MultiWorld");
					if(!isset($result["default_branch"])){
						throw new \RuntimeException("github api default_branch not found.");
					}
					$branch_version = $result["default_branch"];
					$this->getLogger()->info("selected branch: ".$branch_version);
				}

				if($branch_version === "latest"){
					throw new \RuntimeException("latest not supported.");
				}

				if(preg_match('/^[0-9a-f]{40}$/', $branch_version)){
					$descriptions[] = new UrlDescription($owner, $repositoryName, null, $branch_version);
				}else{
					$descriptions[] = new UrlDescription($owner, $repositoryName, $branch_version);
				}

				/*else{
					$branch = $this->getHttp()->get("/repo/".$owner."/".$repositoryName."/git/refs/heads/".$branch_version);
					if(!isset($branch["object"]["sha"])){
						throw new \RuntimeException("github api request falled.");
					}
					$descriptions[] = new UrlDescription($owner, $repositoryName, $branch["object"]["sha"]);
				}*/

				/*elseif($identifier === "releases/tag"||$identifier === "releases"){
//					if($branch_version === "latest"){
//
//					}else{
//						//tags
//						$this->getHttp()->get("/repo/".$owner."/".$repositoryName."/git/refs/tags/".);
//					}

				}*/
			}elseif(preg_match('/^[a-zA-Z0-9]*$/', $require)){
				$this->getLogger()->info("looking ".$require);
				$descriptions[] = $this->getApiDescription($require, $version);
			}else{
				$this->getLogger()->error($require." is an invalid name. skipped.");
			}
		}
		return $descriptions;
	}

	public function getApiDescription(string $require, string $version) : ?ApiDescription{
		$result = $this->http->search($require);
		$this->http->writeCache();
		if(count($result) === 0){
			$this->getLogger()->error("plugin 「".$require."」not found.");
			return null;
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
			$this->logger->error("「".$require."」version 「".$version."」not found.");
			if(count($similars) !== 0){
				$this->logger->error("suggested version: ".implode(" ", $similars));
			}
			return null;
		}


		return new ApiDescription($data);
	}

	/**
	 * @param DescriptionInterface[] $descriptions
	 */
	public function downloadZipball(array $descriptions) : void{
		$concurrentDirectory = $this->dir."cache".DIRECTORY_SEPARATOR;
		if(!is_dir($concurrentDirectory)){
			if(!mkdir($concurrentDirectory, 755)&&!is_dir($concurrentDirectory)){
				throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
			}
		}
		foreach($descriptions as $description){
			$cachedir = $concurrentDirectory.$description->getName()."_".$description->getCacheName().".zip";
			if(!file_exists($cachedir)){
				$this->getLogger()->info("downloading ".$description->getGithubRepoName());
				file_put_contents($cachedir, $this->getHttp()->getRawData($description->getGithubZipballurl()));
			}
		}

	}

	public function requirePlugin(string $plugin, ?string $version = null) : array{
		$plugins = $this->readManifest();
		$plugins["require"][$plugin] = $version ?? "*";
		$this->getLogger()->info("updated plugins.json");
		return $plugins;
	}

	public function readManifest() : array{
		$plugins = [];
		if(file_exists($this->dir."plugins.json")){
			$plugins = json_decode(file_get_contents($this->dir."plugins.json"), true, 512, JSON_THROW_ON_ERROR);
		}
		if(!isset($plugins["require"])){
			$plugins["require"] = [];
		}
		return $plugins;
	}

	public function readcache() : void{
		if(file_exists($this->dir."plugins_cache.json")){
			$cache = json_decode(file_get_contents($this->dir."plugins_cache.json"), true, 512, JSON_THROW_ON_ERROR);
		}

	}


	/**
	 * @return SimpleLogger
	 */
	public function getLogger() : SimpleLogger{
		return $this->logger;
	}

	/**
	 * @return http
	 */
	public function getHttp() : http{
		return $this->http;
	}
}

(new cli())->main($argv);
