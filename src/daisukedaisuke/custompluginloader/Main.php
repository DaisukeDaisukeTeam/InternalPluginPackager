<?php

namespace daisukedaisuke\custompluginloader;

use pocketmine\plugin\PluginBase;

class Main extends PluginBase{
	public function onEnable() : void{
		//$this->getServer()->getPluginManager()->registerInterface(new CustomPluginLoader($this->getServer()->getLoader()));
		$this->getLogger()->info("Registered phar1 plugin loader");
		$this->getServer()->getPluginManager()->loadPlugins($this->getFile()."resources".DIRECTORY_SEPARATOR."plugins".DIRECTORY_SEPARATOR);
		var_dump($this->getFile()."resources".DIRECTORY_SEPARATOR);
	}
}
