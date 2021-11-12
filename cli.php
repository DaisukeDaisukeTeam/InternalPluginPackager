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
if(Phar::running() !== ""){
	$dir = Phar::running().DIRECTORY_SEPARATOR;
}
spl_autoload_register(function($class) use ($dir){
	$file = str_replace("\\", "/", $class).'.php';
	require $file;
});

class cli{
	public SimpleLogger $logger;
	public http $http;
	public string $dir;
	public string $sourcedir;

	/** @var string[] */
	private array $option = [];
	/** @var array<string, string> $libraries libraries index cache */
	private array $libraries = [];
	private bool $changeedLibraries = false;
	private string $output;

	/**
	 * @throws JsonException
	 */
	public function __construct(){
		$this->dir = getcwd().DIRECTORY_SEPARATOR;
		$this->sourcedir = __DIR__.DIRECTORY_SEPARATOR;
		if(Phar::running() !== ""){
			$this->sourcedir = Phar::running().DIRECTORY_SEPARATOR;
		}

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
			$this->option = self::getopt("nt:o:", ["no-dialog", "token:", "output:"], $argv);
			if(count($argv) < 3){
				$this->getLogger()->info('usage: cli.php require multiworld');
				$this->getLogger()->info('usage: cli.php require https://github.com/DaisukeDaisukeTeam/BuyLand');
				$this->getLogger()->info('usage: cli.php require https://github.com/DaisukeDaisukeTeam/BuyLand/tree/test');
				return;
			}

			$this->output = $this->getOption("o", "output") ?? $this->dir."output.phar";

			$this->getHttp()->initToken($this->getOption("t", "token"));

			$require = $argv[2];
			$version = $argv[3] ?? null;
			$this->getLogger()->info('looking plugins...');
			$plugins = $this->requirePlugin($require, $version);
			$descriptions = $this->LookingPlugins($plugins);
			$this->getLogger()->info('downloading plugins...');
			$descriptions = $this->downloadZipball($descriptions);
			$this->getLogger()->info('scan libraries...');
			$descriptions = $this->analyzeManifests($descriptions);
			//$this->getLogger()->info('download libraries...');

			$cache = $this->getCacheDir()."libraries".DIRECTORY_SEPARATOR."index.json";
			if(file_exists($cache)){
				$this->libraries = json_decode(file_get_contents($cache), true, 512, JSON_THROW_ON_ERROR);
			}
			$this->requirelibraries($descriptions);
			if($this->changeedLibraries){
				file_put_contents($cache, json_encode($this->libraries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
			}
			$this->makePhars($descriptions);
			$this->getLogger()->info('packaging plugin');
			$this->packagingPhars($descriptions);


			//$this->getHttp()->writeCache();

			return;
		}
	}

	/**
	 * @param DescriptionBase[] $descriptions
	 */
	protected function packagingPhars(array $descriptions) : void{
		if(file_exists($this->output)){
			//echo "Phar file already exists, overwriting...";
			//echo PHP_EOL;
			Phar::unlinkArchive($this->output);
		}
		$files = [];
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->sourcedir."src")) as $path => $file){
			if($file->isFile() === false) continue;
			$files[str_replace(__DIR__, "", $path)] = $path;
		}

		$cachedir = $this->getCacheDir();

		$files["plugin.yml"] = $this->sourcedir."resources/plugin.yml";
		foreach($descriptions as $description){
			$name = $description->getName();
			$basePath = $description->getPharPath();
			$resourceDir = "resources/plugins/".strtolower($description->getName());
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath)) as $path => $file){
//				$tmp = $cachedir."tmp".DIRECTORY_SEPARATOR.$name.str_replace($basePath, "", $path);
//				$this->mkdir(dirname($tmp));
//				copy($path, $tmp);
				$files[$resourceDir.str_replace($basePath, "", $path)] = $path;
			}
		}

		$phar = new Phar($this->output, 0);
		$phar->startBuffering();
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->buildFromIterator(new \ArrayIterator($files));
		$phar->setStub('<?php echo "build by custom builder v1.0";__HALT_COMPILER();');
		//$phar->compressFiles(Phar::GZ);
		$phar->stopBuffering();

	}

	/**
	 * @param DescriptionBase[] $descriptions
	 */
	protected function makePhars(array $descriptions){
		foreach($descriptions as $description){
			$this->makePhar($description);
		}
	}

	protected function makePhar(DescriptionBase $description) : void{
		$this->getLogger()->info("making ".$description->getName().".phar");

		$file_phar = $this->getCacheDir()."phar".DIRECTORY_SEPARATOR.$description->getName().".phar";
		$this->mkdir(dirname($file_phar));
		$description->setPharPath("phar://".$file_phar);
		if(file_exists($file_phar)){
			//echo "Phar file already exists, overwriting...";
			//echo PHP_EOL;
			Phar::unlinkArchive($file_phar);
		}

		$files = [];
		$dir = $description->getRootPath();

		$exclusions = ["github", ".gitignore", "composer.json", "composer.lock", "build", ".git"];

		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $path => $file){
			foreach($exclusions as $exclusion) if(str_contains($path, $exclusion)) continue 2;
			if($file->isFile() === false) continue;
			$files[str_replace($dir, "", $path)] = $path;
		}
		$phar = new Phar($file_phar, 0);
		$phar->startBuffering();
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->buildFromIterator(new \ArrayIterator($files));
		$phar->setStub('<?php echo "build by custom builder v1.0";__HALT_COMPILER();');
		//$phar->compressFiles(Phar::GZ);
		$phar->stopBuffering();

		$main = $description->getMain();
		$array = explode("\\", $main);
		unset($array[array_key_last($array)]);
		$namespace = implode("\\", $array);
		switch($description->getType()){
//			case DescriptionBase::TYPE_NORMAL:
//				break;
			case DescriptionBase::TYPE_LIBRARY:
				foreach($description->getLibraryEntries() as $libraryEntry){
					$this->getLogger()->info("> install library ".$libraryEntry->getName());
					shell_exec(PHP_BINARY." ".escapeshellarg($libraryEntry->getPharPath())." ".escapeshellarg($file_phar)." ".escapeshellarg($namespace."\\"));
				}
				break;
		}
	}

	/**
	 * @param DescriptionBase[] $descriptions
	 * @return DescriptionBase[]
	 */
	public function requirelibraries(array $descriptions) : array{
		foreach($descriptions as $description){
			if($description->getType() === DescriptionBase::TYPE_NORMAL){
				continue;
			}
			$this->requireLibrary($description);
		}
		return $descriptions;
	}

	public function requireLibrary(DescriptionBase $description) : void{
		if(count($description->getLibraryEntries()) === 0){
			return;
		}

		$escape_name = preg_replace("/[^a-zA-Z0-9]/", "-", $description->getName());
		$cachedir = $this->getCacheDir()."libraries".DIRECTORY_SEPARATOR;//.$escape_name.DIRECTORY_SEPARATOR;
		$this->mkdir($cachedir);
		$tmpphar = $cachedir."tmp.phar";


		$foundCache = true;
		$this->getLogger()->info("scanning ".$description->getName()." libraries cache");
		foreach($description->getLibraryEntries() as $libraryEntry){
			if(isset($this->libraries[$libraryEntry->getCacheName()])){
				$phardir = $cachedir.$this->libraries[$libraryEntry->getCacheName()];
				if(file_exists($phardir)){
					$this->getLogger()->info("found ".$description->getName()." library cache.");
					$libraryEntry->setPharPath($phardir);
					continue;
				}
			}
			$this->getLogger()->info("downloading ".$libraryEntry->getName()." library...");

			if(file_exists($tmpphar)){
				unlink($tmpphar);
			}

			$tmpphar = $cachedir."test.phar";

			$array = explode("/", trim($libraryEntry->getLibrary()));
			$this->getHttp()->downloadLibrary($tmpphar, $array[0], $array[1], $array[2], $libraryEntry->getVersion(), $libraryEntry->getBranch());

//			if(!copy(
//				"https://poggit.pmmp.io/v.dl/".urlencode($array[0])."/".urlencode($array[0])."/".urlencode($array[0])."/".urlencode(trim($libraryEntry->getVersion()))."?branch=".urlencode(trim($libraryEntry->getBranch())),
//				$tmpphar
//			)){
//				throw new RuntimeException("library downloader: copy falled.");
//			}

			if(!file_exists($tmpphar)){
				throw new RuntimeException("library downloader: ".$tmpphar.": no such file or directory.");
			}

			$manifest = yaml_parse(file_get_contents("phar://".$tmpphar.DIRECTORY_SEPARATOR."virion.yml"));
			$actualVersion = $manifest["version"];
			$target = $libraryEntry->getName()."_v".strstr($actualVersion, ".", true).".phar";
			rename($tmpphar, $cachedir.$target);
			$libraryEntry->setPharPath($cachedir.$target);
			$this->libraries[$libraryEntry->getCacheName()] = $target;
			$this->changeedLibraries = true;
			$foundCache = false;
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
		$pluginManifestPath = $description->getPluginManifestPath();

		if(!file_exists($pluginManifestPath)){
			throw new \RuntimeException("plugin.yml(".$pluginManifestPath.") not found.");
		}
		$pluginManifest = yaml_parse(file_get_contents($description->getPluginManifestPath()));
		$description->setMain($pluginManifest["main"]);

		if(!file_exists($path)){
			$description->setType(DescriptionBase::TYPE_NORMAL);//github
			return;
			//throw new \RuntimeException("manifest file ".$path." not found.");
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
				$description->addLibraryEnty(new LibraryEntry($libraryName, $library, $version, $branch));
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
		$concurrentDirectory = $this->getCacheDir();
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
		if(!mkdir($targetDirectory, 0755, true)&&!is_dir($targetDirectory)){
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

				if(!$this->hasOption("n", "no-dialog")){
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

	/**
	 * @param string[] $value
	 */
	public function getOption(string ...$value) : ?string{
		foreach($value as $item){
			if(isset($this->option[$item])){
				return $this->option[$item];
			}
		}
		return null;
	}

	public function getCacheDir() : string{
		return $this->dir."cache".DIRECTORY_SEPARATOR;
	}

	/**
	 * @param string $short_options
	 * @param list<string> $long_options
	 * @param list<string> $argv
	 * @return array<string, string>
	 */
	public static function getopt(string $short_options, array $long_options = [], array $argv = []) : array{
		$result = [];
		for($i = 1, $iMax = count($argv) - 1; $i <= $iMax; $i++){
			$next_value = null;
			$value = $argv[$i];
			if(isset($argv[$i + 1])&&!str_starts_with($argv[$i + 1], "-")){
				$next_value = $argv[$i + 1];
			}

			if(str_starts_with($value, "--")){
				$target = substr($value, 2);
				if(isset($result[$target])){
					continue;
				}
				foreach($long_options as $long_option){
					if(str_starts_with($long_option, $target)){
						$operator = substr(strstr($long_option, ":"), 0, 2);
						if($operator === "::"){
							if($next_value === null){
								$result[$target] = false;
								continue;
							}
							$result[$target] = $next_value;
							++$i;
						}elseif($operator !== ""&&$operator[0] === ":"){
							if($next_value === null){
								continue;
							}
							$result[$target] = $next_value;
							++$i;
						}else{
							$result[$target] = false;
						}
					}
				}
				continue;
			}

			if(str_starts_with($value, "-")){
				if(isset($result[$value[1]])){
					continue;
				}
				if(($str = strstr($short_options, $value[1])) !== false){
					if(substr($str, 1, 2) === "::"){
						if($next_value !== null){
							$result[$value[1]] = $next_value;
							++$i;
						}else{
							$result[$value[1]] = false;
						}
					}elseif(isset($str[1])&&$str[1] === ":"){
						if($next_value === null){
							continue;
						}
						$result[$value[1]] = $next_value;
					}else{
						$result[$value[1]] = false;
					}
				}
			}
		}
		return $result;
	}
}

(new cli())->main($argv);