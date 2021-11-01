<?php

namespace daisukedaisuke\custompluginloader;


use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginLoader;

class CustomPluginLoader implements PluginLoader{

	/** @var \DynamicClassLoader */
	private $loader;

	public function __construct(\DynamicClassLoader $loader){
		$this->loader = $loader;
	}

	public function canLoadPlugin(string $path) : bool{
		$ext = ".phar1";
		var_dump($path, is_file($path) and substr($path, -strlen($ext)) === $ext);
		return is_file($path) and substr($path, -strlen($ext)) === $ext;
	}

	/**
	 * Loads the plugin contained in $file
	 */
	public function loadPlugin(string $file) : void{
		$description = $this->getPluginDescription($file);
		if($description !== null){
			$this->loader->addPath($description->getSrcNamespacePrefix(), "$file/src");
		}
	}

	/**
	 * Gets the PluginDescription from the file
	 */
	public function getPluginDescription(string $file) : ?PluginDescription{
		if(\Phar::running(true) === ""){
			$file = "phar://".$file;
		}
		var_dump($file."/plugin.yml", file_exists($file."/plugin.yml"));
		if(file_exists($file."/plugin.yml")){
			return new PluginDescription(file_get_contents($file."/plugin.yml"));
		}

		return null;
	}

	public function getAccessProtocol() : string{
		var_dump(\Phar::running(true) === "" ? "" : "phar://");
		return \Phar::running(true) === "" ? "phar://" : "";
	}
}