<?php

use cli\description\ApiDescription;
use cli\description\DescriptionInterface;
use cli\description\UrlDescription;
use cli\http;
use cli\SimpleLogger;

$dir = __DIR__.DIRECTORY_SEPARATOR;

spl_autoload_register(function($class) use ($dir){
	include_once $dir.$class.'.php';
});

class cli{
	public SimpleLogger $logger;
	public http $http;
	public string $dir;
	/** @var string[] */
	private array $option = [];

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

			if(isset($argv[4])){
				$this->option = array_flip(array_slice($argv, 4));
			}

			$require = $argv[2];
			$version = $argv[3] ?? null;
			$plugins = $this->requirePlugin($require, $version);
			$descriptions = $this->LookingPlugins($plugins);
			exit();
			$caches = $this->downloadZipball($descriptions);
			$caches;
			var_dump($descriptions);
			return;
		}
	}

	/**
	 * @param list<string> $caches
	 * @param string $dir
	 */
	public function unzipping(string $caches, string $dir) : void{

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
			if(preg_match('/^[0-9a-f]{40}$/', $branch_version)){
				$descriptions[] = new UrlDescription($owner, $repositoryName, null, $branch_version);
			}else{
				$descriptions[] = new UrlDescription($owner, $repositoryName, $branch_version);
			}
			if(preg_match('/^[a-zA-Z0-9]*$/', $require)){
				$this->getLogger()->info("looking ".$require);
				$descriptions[] = $this->getApiDescription($require, $version);
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
	 * @return list<string>
	 */
	public function downloadZipball(array $descriptions) : array{
		$caches = [];
		$concurrentDirectory = $this->dir."cache".DIRECTORY_SEPARATOR;
		$this->mkdir($concurrentDirectory);
		foreach($descriptions as $description){
			$cachefile = $concurrentDirectory.$description->getName()."_".$description->getCacheName().".zip";
			if(!file_exists($cachefile)){
				$this->getLogger()->info("downloading ".$description->getGithubRepoName());
				file_put_contents($cachefile, $this->getHttp()->getRawData($description->getGithubZipballurl()));
			}
			$caches[] = $cachefile;
		}
		return $caches;
	}

	private function mkdir($targetDirectory) : void{
		if(is_dir($targetDirectory)){
			return;
		}
		if(!mkdir($targetDirectory, 755)&&!is_dir($targetDirectory)){
			throw new \RuntimeException(sprintf('Directory "%s" was not created', $targetDirectory));
		}
	}

	public function requirePlugin(string $plugin, ?string $version = null) : array{
		$plugins = $this->readManifest();

		if($this->validateGithubUrl($plugin, $m)){
			[$match, $owner, $repositoryName, $identifier, $branch_version] = $m;

			$repo = $owner."/".$repositoryName;

			if($branch_version === ""){
				$this->getLogger()->info("looking ".$owner."/".$repositoryName);
			}else{
				$this->getLogger()->info("looking ".$owner."/".$repositoryName." ".$branch_version);
			}

			$branches = $this->getHttp()->get("https://api.github.com/repos/".$repo."/branches");
			$branches = array_flip(array_column($branches, "name"));

			if($branch_version === ""){
				$this->getLogger()->info("getting branch information...");
				$result = $this->getHttp()->get("/repos/".$repo);
				if(!isset($result["default_branch"])){
					throw new \RuntimeException("github api default_branch not found.");
				}

				if(!$this->hasOption("-D", "--no-dialog")){
					if(!isset($branches[0]["name"])){
						throw new \RuntimeException("The response from Github is incorrect.");
					}

					foreach($branches as $index => $name){
						$this->getLogger()->info($index.") ".$name);
					}
					$input = "";
					do{
						$input = $this->getLogger()->requestInput("branch [".$result["default_branch"]."]: ");
						if(!isset($branches[$input])){
							$this->getLogger()->info("branch ".$input." is not found.");
						}
					}while(false);

					$branch_version = $input;
				}else{
					$branch_version = $result["default_branch"];
					$this->getLogger()->info("selected branch: ".$branch_version);
				}
				$plugins["require"][$owner."/".$repositoryName] = $branch_version;
				return $plugins;
			}

			if($branch_version === "latest"){
				throw new \RuntimeException("latest release tag not supported.");
			}

			if(preg_match('/^[0-9a-f]{40}$/', $branch_version)){
				$descriptions[] = new UrlDescription($owner, $repositoryName, null, $branch_version);
				$plugins["require"][$owner."/".$repositoryName] = $branch_version;
				return $plugins;
			}
			if(isset($branches[""])){

			}
			$plugins["require"][$owner."/".$repositoryName] = $branch_version;
			return $plugins;
		}elseif(preg_match('/^[a-zA-Z0-9]*$/', $plugin)){
			$this->getLogger()->info("looking ".$plugin);
			$plugins["require"][$plugin] = "*";
			//$descriptions[] = $this->getApiDescription($require, $version);
		}else{
			$this->getLogger()->error($plugin." is an invalid name. skipped.");
		}
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

	public function validateGithubUrl(string $url, array &$match = null) : bool{
		return preg_match('/^http[s]?\:\/\/github\.com\/([^\/\n_]*)\/([^\/\n_]*)\/?(tree|blob|releases\/tag|releases)?\/?([^\/\n_]*)?\/?/', $url, $match);
	}

	/**
	 * @param string[] $value
	 */
	public function hasOption(string ...$value) : bool{
		foreach($value as $item){
			if(isset($this->option[$item])){
				return true;
			}
		}
		return false;
	}
}

(new cli())->main($argv);
