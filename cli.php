<?php

use cli\description\ApiDescription;
use cli\description\BranchDescription;
use cli\description\DescriptionBase;
use cli\description\ShaDescription;
use cli\http;
use cli\LibraryEntry;
use cli\SimpleLogger;

ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);
ini_set('xdebug.var_display_max_depth', -1);

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
			$this->getLogger()->info('looking plugins...');
			$plugins = $this->requirePlugin($require, $version);
			$descriptions = $this->LookingPlugins($plugins);
			$this->getLogger()->info('downloading plugins...');
			$descriptions = $this->downloadZipball($descriptions);
			$this->getLogger()->info('scan libraries...');
			$descriptions = $this->analyzeManifests($descriptions);
			$this->getLogger()->info('download libraries...');
			$this->requirelibraries($descriptions);

			//$this->getHttp()->writeCache();

			return;
		}
	}

	/**
	 * @param DescriptionBase[] $descriptions
	 * @return DescriptionBase[]
	 */
	public function requirelibraries(array $descriptions) : array{
		foreach($descriptions as $description){
			$this->requireLibrary($description);
		}
		return $descriptions;
	}

	public function requireLibrary(DescriptionBase $description) : void{
		foreach($description->getLibraryEntries() as $libraryEntry){
			if(file_exists(getcwd()."library_downloader.php")){

			}
			$libraryEntry->get;
			shell_exec(PHP_BINARY.escapeshellarg(""));
		}
	}

	/**
	 * @param DescriptionBase[] $descriptions
	 * @return DescriptionBase[]
	 */
	public function analyzeManifests(array $descriptions) : array{
		foreach($descriptions as $description){
			$this->analyzeManifest($description);
		}
		return $descriptions;
	}

	public function analyzeManifest(DescriptionBase $description) : void{
		$name = $description->getName();
		$path = $description->getManifestPath();
		if(!file_exists($path)){
			throw new \RuntimeException("manifest file ".$path." not found.");
		}
		$manifest = yaml_parse(file_get_contents($path));
		$description->setProjectPath($manifest["path"] ?? "");
		foreach($manifest["projects"] as $projectName => $project){
			if($name !== $projectName){
				continue;
//					$result[$name] = new LibraryEntry($manifest);
//					break;
			}
			$libs = $project["libs"] ?? [];
			foreach($libs as $libraryName => $array){
				$library = $array["src"];
				$version = $array["version"] ?? "*";
				$branch = $array["branch"] ?? ":default";
				$description->addLibraryEnty(new LibraryEntry($library, $version, $branch));
			}
		}
	}

	/**
	 * @param DescriptionBase[] $descriptions
	 * @param string $dir
	 */
	public function unzipping(string $descriptions, string $dir) : void{
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("phar://".__DIR__."/MultiWorld-26b23030957967722e95.zip/MultiWorld-26b23030957967722e959dd0fc1b883ed63b0b99/")) as $path => $file){
			var_dump(substr(file_get_contents($path), 1000, 500));
		}
	}

	/**
	 * @param mixed[] $plugins
	 * @phpstan-param array<string, string> $plugins
	 * @return DescriptionBase[]
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

			if(preg_match('/^[a-zA-Z0-9]*$/', $require)){
				$this->getLogger()->info("looking ".$require);
				$descriptions[] = $this->getApiDescription($require, $version);
			}else{
				switch(true){
					case ShaDescription::CheckFormat($require, $version):
						$descriptions[] = ShaDescription::init($require, $version);
						break;
					default://branch
						$branches = $this->getHttp()->get("https://api.github.com/repos/".$require."/branches");
						foreach($branches as $index => $array){
							if($array["name"] === $version){
								$version = $array["commit"]["sha"];//commit sha
							}
						}
						$descriptions[] = ShaDescription::init($require, $version);
						break;
				}
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
	 * @param DescriptionBase[] $descriptions
	 * @return DescriptionBase[]
	 */
	public function downloadZipball(array $descriptions) : array{
		$concurrentDirectory = $this->dir."cache".DIRECTORY_SEPARATOR;
		$this->mkdir($concurrentDirectory);
		foreach($descriptions as $description){
			$cachefile = $description->getName()."-".$description->getCacheName().".zip";
			$cachefile = preg_replace("/[^a-zA-Z0-9.]/", "-", $cachefile);
			if(!is_string($cachefile)){
				var_dump($cachefile);
				throw new LogicException("cachefile is not string");
			}
			$cachefile = $concurrentDirectory.$cachefile;

			if(!file_exists($cachefile)){
				$this->getLogger()->info("downloading ".$description->getGithubRepoName());
				file_put_contents($cachefile, $this->getHttp()->getRawData($description->getGithubZipballurl()));
			}
			$description->setCachePath($cachefile);
		}
		return $descriptions;
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

			if($branch_version === "latest"){
				throw new \RuntimeException("latest release tag not supported.");
			}

			$branches = $this->getHttp()->get("https://api.github.com/repos/".$repo."/branches");
			if(!isset($branches[0]["name"])){
				throw new \RuntimeException("The response from Github is incorrect.");
			}
			$branches = array_flip(array_column($branches, "name"));

			if($branch_version === ""){
				$this->getLogger()->info("selected branch: ".$branch_version);
				$this->getLogger()->info("looking ".$owner."/".$repositoryName);
				$this->getLogger()->info("getting branch information...");
				$result = $this->getHttp()->get("/repos/".$repo);
				if(!isset($result["default_branch"])){
					throw new \RuntimeException("github api default_branch not found.");
				}

				if(!$this->hasOption("-D", "--no-dialog")){
					foreach($branches as $name => $index){
						$this->getLogger()->info("[".$name."]: ".$index);
					}
					$input = "";
					do{
						$input = 2;//$this->getLogger()->requestInput("branch [".$result["default_branch"]."/".$branches[$result["default_branch"]]."]: ");
						if(isset($branches[$input])){
							$branch_version = $input;
						}elseif(($search = array_search($input, $branches, true)) !== false){
							$branch_version = $search;
						}else{
							$branch_version = $result["default_branch"];
						}
					}while(false);
					$this->getLogger()->info("selected branch: ".$branch_version);
				}else{
					$branch_version = $result["default_branch"];
				}
			}

			/**
			 * @var DescriptionBase $class
			 */
			foreach([ShaDescription::class, BranchDescription::class] as $class){
				if($class::CheckFormat($repo, $branch_version)){
					$description = $class::init($repo, $branch_version);
					$plugins["require"][$description->getGithubRepoName()] = $description->getVersion();
					return $plugins;
				}
			}
		}elseif(preg_match('/^[a-zA-Z0-9]*$/', $plugin)){
			$plugins["require"][$plugin] = "*";
		}
		$this->getLogger()->info("updated plugins.json");
		//return $plugins;
		throw new LogicException("requirePlugin: unknown format.");
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

//	/**
//	 * @param LibraryEntry[] $manifestDescriptions
//	 */
//	public function requireLibrary(array $manifestDescriptions){
//		foreach($manifestDescriptions as $projectName => $manifestDescription){
//			foreach($manifestDescription->getLibs() as $libName => $array){
//				$array["lists"]
//			}
//		}
//	}
}

(new cli())->main($argv);
