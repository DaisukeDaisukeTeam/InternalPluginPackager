<?php

$dir = __DIR__.DIRECTORY_SEPARATOR."cli".DIRECTORY_SEPARATOR;

spl_autoload_register(function($class) use ($dir){
	var_dump($class);
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
				$this->getLogger()->info('usage: cli.php require multiworld@master');
				$this->getLogger()->info('usage: cli.php require or https://github.com/DaisukeDaisukeTeam/BuyLand');
				$this->getLogger()->info('usage: cli.php require or https://github.com/DaisukeDaisukeTeam/BuyLand/tree/test');
				return;
			}
			$require = $argv[2];
			$version = $argv[3];
			$plugins = $this->requirePlugin($require, $version);
			$this->installPlugins($require);
			return;
		}
	}

	/**
	 * @param string $plugins
	 * @phpstan-param array<string, string> $plugins
	 */
	public function installPlugins(string $plugins) : void{
		foreach($plugins as $require => $version){
			if(preg_match('/^http[s]?\:\/\/github\.com\/([^\/\n_]*)\/([^\/\n_]*)\/?(tree|blob|releases\/tag|releases|)?\/?([^\/\n_]*)?\/?/', $require, $m) !== false){

			}elseif(preg_match('/^[a-zA-Z0-9]*$/', $require) !== false){
				$description = $this->getApiDescription($require, $version);
				var_dump($description);
			}else{
				$this->getLogger()->error($require." is an invalid name. skipped.");
			}
		}
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
			$data = $result[0];
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

//		$repo_name = $data["repo_name"];
//		$project_name = $data["project_name"];
//		$build_commit = $data["build_commit"];
		return new ApiDescription($data);
	}

	public function requirePlugin(string $plugin, ?string $version = null) : array{
		$plugins = $this->readManifest();
		$plugins[$plugin] = $version ?? "*";
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
}

(new cli());
