<?php

namespace daisukedaisuke\custompluginloader;

use pocketmine\plugin\PluginBase;

class Main extends PluginBase{
	public function onEnable() : void{
		$this->getServer()->getPluginManager()->loadPlugins($this->getFile()."resources".DIRECTORY_SEPARATOR."plugins".DIRECTORY_SEPARATOR);
	}
}
