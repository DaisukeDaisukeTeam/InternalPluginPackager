<?php

use cli\description\ApiDescription;
use cli\description\DescriptionBase;
use cli\description\DescriptionType;
use cli\description\PharDescription;
use cli\description\ShaDescription;
use cli\exception\CatchableException;
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
	public const VERSION = "0.0.1";

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

	public function echoVersion() : void{
		$this->getLogger()->info("version v".self::VERSION);
	}

	public function main(array $argv) : void{
		$opt = getopt("v", ["version"]);
		if(isset($opt["version"])||isset($opt["v"])){
			$this->echoVersion();
			return;
		}
		$this->option = self::getopt("nt:o:", ["no-manifest", "no-dialog", "token:", "output:"], $argv, $parameter, ["version", "install", "require"], true, $unknownOptions);//["install", "require", "version"]
		if(count($parameter) === 0){
			$this->getLogger()->info("usage: cli.php install|require|version");
			$this->getLogger()->info("[-v|--version]");
			return;
		}

		if($parameter[0] === "version"){
			$this->echoVersion();
			return;
		}

		if($parameter[0] === "install"){
			$this->output = $this->getOption("o", "output") ?? $this->dir."output.phar";

			$this->getHttp()->initToken($this->getOption("t", "token"));

			$plugins = $this->readManifest();
			if(count($plugins["require"] ?? []) === 0){
				$this->getLogger()->info("nothing to install.");
				return;
			}
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
				file_put_contents($cache, json_encode($this->libraries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
			}
			$this->makePhars($descriptions);
			$this->getLogger()->info('packaging plugins');
			$this->packagingPhars($descriptions);

		}

		if($parameter[0] === "require"){
			if(count($parameter) < 2){
				$this->getLogger()->info('usage: cli.php require multiworld');
				$this->getLogger()->info('usage: cli.php require https://github.com/DaisukeDaisukeTeam/BuyLand');
				$this->getLogger()->info('usage: cli.php require https://github.com/DaisukeDaisukeTeam/BuyLand/tree/test');
				$this->getLogger()->info("[-n|--no-dialog] [-t|token] [-o|output] [--no-manifest]");
				return;
			}

			$this->output = $this->getOption("o", "output") ?? $this->dir."output.phar";

			$this->getHttp()->initToken($this->getOption("t", "token"));

			$require = $parameter[1];
			$version = $parameter[2] ?? null;
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
				file_put_contents($cache, json_encode($this->libraries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
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
			Phar::unlinkArchive($this->output);
		}
		$files = [];
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->sourcedir."src")) as $path => $file){
			if($file->isFile() === false) continue;
			$files[str_replace(__DIR__, "", $path)] = $path;
		}

		$files["plugin.yml"] = $this->sourcedir."resources/plugin.yml";
		foreach($descriptions as $description){
			$name = $description->getName();
			$basePath = $description->getPharPath();
			$resourceDir = "resources/plugins/".strtolower($description->getName());
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath)) as $path => $file){
				$files[$resourceDir.str_replace($basePath, "", $path)] = $path;
			}
		}

		$phar = new Phar($this->output, 0);
		$phar->startBuffering();
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->buildFromIterator(new \ArrayIterator($files));
		$phar->setStub('<?php echo "build by custom builder v'.self::VERSION.'";__HALT_COMPILER();');
		//$phar->compressFiles(Phar::GZ);
		$phar->stopBuffering();
		unset($phar);
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
		$description->setPharPath("phar://".$file_phar);

		$file_phar_tmp = $this->getCacheDir()."phar".DIRECTORY_SEPARATOR."tmp_".$description->getName().".phar";

		$this->mkdir(dirname($file_phar_tmp));

		if(file_exists($file_phar_tmp)){
			Phar::unlinkArchive($file_phar_tmp);
		}

		$files = [];
		$dir = $description->getRootPath();

		$exclusions = ["github", ".gitignore", "composer.json", "composer.lock", "build", ".git"];

		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $path => $file){
			foreach($exclusions as $exclusion) if(str_contains($path, $exclusion)) continue 2;
			if($file->isFile() === false) continue;
			$files[str_replace($dir, "", $path)] = $path;
		}
		$phar = new Phar($file_phar_tmp, 0);
		$phar->startBuffering();
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->buildFromIterator(new \ArrayIterator($files));
		$phar->setStub('<?php echo "build by custom builder v'.self::VERSION.'";__HALT_COMPILER();');
		//$phar->compressFiles(Phar::GZ);
		$phar->stopBuffering();
		unset($phar);

		$main = $description->getMain();
		$array = explode("\\", $main);
		unset($array[array_key_last($array)]);
		$namespace = implode("\\", $array);
		switch($description->getType()){
//			case DescriptionType::TYPE_NORMAL:
//				break;
			case DescriptionType::TYPE_LIBRARY:
				foreach($description->getLibraryEntries() as $libraryEntry){
					$this->getLogger()->info("> install library ".$libraryEntry->getName());
					shell_exec(PHP_BINARY." ".escapeshellarg($libraryEntry->getPharPath())." ".escapeshellarg($file_phar_tmp)." ".escapeshellarg($namespace."\\"));
				}
				break;
		}
		//hack: Destroy the memory cache of the phar.
		rename($file_phar_tmp, $file_phar);
	}

	/**
	 * @param DescriptionBase[] $descriptions
	 * @return DescriptionBase[]
	 */
	public function requirelibraries(array $descriptions) : array{
		foreach($descriptions as $description){
			if($description->getType() === DescriptionType::TYPE_NORMAL){
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
					$this->getLogger()->info("found ".$libraryEntry->getName()." library cache.");
					$libraryEntry->setPharPath($phardir);
					continue;
				}
			}
			$this->getLogger()->info("downloading ".$libraryEntry->getName()." library...");

			if(file_exists($tmpphar)){
				unlink($tmpphar);
			}

			$tmpphar = $cachedir."tmp.phar";

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
			$escape_version = preg_replace("/[^a-zA-Z0-9]/", "_", $actualVersion);
			$target = $libraryEntry->getName()."_v".$escape_version.".phar";
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
			$description->setType(DescriptionType::TYPE_NORMAL);//github
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

				$libraryName = explode("/", strtr($library, ["\\" => "/"]))[1];

				$description->addLibraryEnty(new LibraryEntry($libraryName, $library, $version, $branch));
			}
		}
	}

	/**
	 * @param mixed[] $plugins
	 * @phpstan-param array<string, string> $plugins
	 * @return DescriptionBase[]
	 */
	public function LookingPlugins(array $plugins) : array{
		$repository = [];
		foreach(($plugins["repository"] ?? []) as $key => $array){
			$index = $array["src"] ?? $array["name"] ?? null;
			if($index === null){
				throw new CatchableException("repository \"".$key."\" must Define src or name value.");
			}
			$repository[$index] = $array;
		}

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

			$this->getLogger()->info("looking ".$require);
			$type = null;
			$zippath = "";
			$urlpath = "";
			if(isset($repository[$require])){
				$require = $repository[$require]["src"];
				$zippath = $repository[$require]["path"] ?? "";
				$urlpath = $repository[$require]["url_path"] ?? "";
				$type = $repository[$require]["type"] ?? null;
			}

			if($type === null){
				if(preg_match('/^[a-zA-Z0-9]*$/', $require)){
					$type = DescriptionType::TYPE_LIBRARY;
				}else{
					$type = DescriptionType::TYPE_NORMAL;
				}
			}
			/**
			 * @var class-string<DescriptionBase>[] $types
			 */
			$types = [
				DescriptionType::TYPE_LIBRARY => ApiDescription::class,
				DescriptionType::TYPE_NORMAL => ShaDescription::class,
				DescriptionType::TYPE_PHAR => PharDescription::class,
			];

			if(!isset($types[$type])){
				throw new CatchableException($require." is invalid format.");
			}

			/** @var DescriptionBase $description */
			$description = $types[$type]::init($this->getHttp(), $require, $version);
			$description->setZipPath($zippath);
			$description->setUrlPath($urlpath);
			$descriptions[] = $description;
		}
		return $descriptions;
	}

	/**
	 * @param DescriptionBase[] $descriptions
	 * @return DescriptionBase[]
	 */
	public function downloadZipball(array $descriptions) : array{
		$concurrentDirectory = $this->getCacheDir();
		$this->mkdir($concurrentDirectory);
		foreach($descriptions as $description){
			$cachefile = $description->getCacheFile();
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
					do{
						$input = $this->getLogger()->requestInput("branch [".$result["default_branch"]."/".$branches[$result["default_branch"]]."]: ");
						if(isset($branches[$input])){
							$branch_version = $input;
						}elseif(($search = array_search($input, $branches, true)) !== false){
							$branch_version = $search;
						}else{
							$branch_version = $result["default_branch"];
						}
					}while(false);
				}else{
					$branch_version = $result["default_branch"];
				}
				$this->getLogger()->info("selected branch: ".$branch_version);
			}

			/**
			 * @var DescriptionBase $class
			 */
			foreach([ShaDescription::class] as $class){
				if($class::isValidFormat($repo, $branch_version)){
					$description = $class::init($this->getHttp(), $repo, $branch_version);
					$plugins["require"][$description->getGithubRepoName()] = $description->getVersion();
					$this->saveManifest($plugins);
					return $plugins;
				}
			}
		}elseif(preg_match('/^[a-zA-Z0-9]*$/', $plugin)){
			$plugins["require"][$plugin] = "*";
			$this->saveManifest($plugins);
			return $plugins;
		}
		$this->getLogger()->info("updated plugins.json");
		//return $plugins;
		throw new LogicException("requirePlugin: \"".$plugin."\" is an unknown format.");
	}

	/**
	 * @param array<string, string> $plugins
	 */
	public function saveManifest(array $plugins) : void{
		if($this->hasOption("no-manifest")){
			return;
		}
		file_put_contents($this->dir.DIRECTORY_SEPARATOR."plugins.json", json_encode($plugins, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}

	public function readManifest() : array{
		if($this->hasOption("no-manifest")){
			return ["require" => []];
		}
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
	 * @param list<string>|null $parameter
	 * @param list<string> $after
	 * @param bool $notallowUnknownOptions
	 * @param list<string>|null $unknownOptions
	 * @return array<string, string>
	 * @see getopt() native getopt function.
	 */
	public static function getopt(string $short_options, array $long_options = [], array $argv = [], ?array &$parameter = null, array $after = [], bool $notallowUnknownOptions = false, array &$unknownOptions = null) : array{
		$unknownOptions = [];
		$result = [];
		for($i = 1, $iMax = count($argv) - 1; $i <= $iMax; $i++){
			$next_value = null;
			$value = $argv[$i];
			if(isset($argv[$i + 1])&&!str_starts_with($argv[$i + 1], "-")){
				$next_value = $argv[$i + 1];
			}

//			if($ignoreScriptOption === true){
//				if(str_starts_with($value, "-")){
//					unset($argv[$i]);
//					continue;
//				}
//				$ignoreScriptOption = false;
//			}

			if($after !== null){
				foreach($after as $item){
					if($item === $value){
						$after = null;
						continue 2;
					}
				}
				unset($argv[$i]);
				continue;
			}

			if(str_starts_with($value, "--")){
				$found = false;
				$target = substr($value, 2);
				foreach($long_options as $long_option){
					//var_dump([$target, $long_option], str_starts_with($target, $long_option));
					if(str_starts_with($target, strstr($long_option, ":", true) ?: $long_option)){
						$found = true;
						$operator = substr(strstr($long_option, ":"), 0, 2);
						if($operator === "::"){
							if($next_value === null){
								$result[$target][] = false;
								unset($argv[$i]);
								continue;
							}
							$result[$target][] = $next_value;
							unset($argv[$i], $argv[$i + 1]);
							++$i;
						}elseif($operator !== ""&&$operator[0] === ":"){
							if($next_value === null){
								continue;
							}
							$result[$target][] = $next_value;
							unset($argv[$i], $argv[$i + 1]);
							++$i;
						}else{
							$result[$target][] = false;
							unset($argv[$i]);
						}
					}
				}
				//unknown Options
				if(!$found){
					if($next_value === null){
						$unknownOptions["--".$target][] = false;
					}else{
						$unknownOptions["--".$target][] = $next_value;
					}
				}
				continue;
			}

			if(str_starts_with($value, "-")){
				if(($str = strstr($short_options, $value[1])) !== false){
					if(substr($str, 1, 2) === "::"){
						if(strlen($value) >= 3){
							$result[$value[1]][] = substr($value, 2);
							unset($argv[$i]);
							continue;
						}
						if($next_value !== null){
							$result[$value[1]][] = $next_value;
							unset($argv[$i], $argv[$i + 1]);
							++$i;
						}else{
							$result[$value[1]][] = false;
							unset($argv[$i]);
						}
					}elseif(isset($str[1])&&$str[1] === ":"){
						if(strlen($value) >= 3){
							$result[$value[1]][] = substr($value, 2);
							unset($argv[$i]);
							continue;
						}
						if($next_value === null){
							continue;
						}
						$result[$value[1]][] = $next_value;
						unset($argv[$i], $argv[$i + 1]);
						++$i;
					}else{
						$result[$value[1]][] = false;
						unset($argv[$i]);
					}
				}else{
					//unknown Options
					if(strlen($value) >= 3){
						$unknownOptions[$value[1]][] = substr($value, 2);
						continue;
					}
					if($next_value === null){
						$unknownOptions["-".$value[1]][] = false;
					}else{
						$unknownOptions["-".$value[1]][] = $next_value;
					}
				}
				continue;
			}
		}
		foreach($unknownOptions as $key => $item){
			if(count($item) === 1){
				$unknownOptions[$key] = $item[0];
			}
		}
		foreach($result as $key => $item){
			if(count($item) === 1){
				$result[$key] = $item[0];
			}
		}
		unset($argv[0]);
		$parameter = array_values($argv);

		if($notallowUnknownOptions === true&&count($unknownOptions) !== 0){
			foreach($unknownOptions as $name => $item){
				throw new CatchableException("final: The \"".$name."\" option does not exist.");
			}
		}

		return $result;
	}
}

//var_dump($opt = getopt("v::", ["version::"]));
////
//var_dump($argv);
//var_dump(cli::getopt("ot:a:v::n", ["no-dialog", "token:", "output:"], $argv, $option,false, $unknownOptions));
//var_dump([$unknownOptions,$option]);
try{
	(new cli())->main($argv);
}catch(CatchableException $exception){
	echo "[".get_class($exception)."]".PHP_EOL;
	echo $exception->getMessage().PHP_EOL;
}
