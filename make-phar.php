<?php

$file_phar = "custompluginloader.phar";
if(file_exists($file_phar)){
	echo "Phar file already exists, overwriting...";
	echo PHP_EOL;
	Phar::unlinkArchive($file_phar);
}

$files = [];
$dir = getcwd().DIRECTORY_SEPARATOR;

$exclusions = ["github", ".gitignore", "composer.json", "composer.lock", "build", ".git", "vendor", "cache", ".idea", ".phar", "test"];

foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $path => $file){
	$bool = true;
	foreach($exclusions as $exclusion){
		if(strpos($path, $exclusion) !== false){
			$bool = false;
		}
	}

	if(!$bool){
		continue;
	}

	if($file->isFile() === false){
		continue;
	}
	$files[str_replace($dir, "", $path)] = $path;
}

//$files["plugin.yml"] = $dir."resources".DIRECTORY_SEPARATOR."plugin.yml";

echo "Compressing...".PHP_EOL;
$phar = new Phar($file_phar, 0);
$phar->startBuffering();
$phar->setSignatureAlgorithm(\Phar::SHA1);
$phar->buildFromIterator(new \ArrayIterator($files));
$phar->setStub('<?php include "phar://".__FILE__."/cli.php"; __HALT_COMPILER();');
$phar->compressFiles(Phar::GZ);
$phar->stopBuffering();
echo "end.".PHP_EOL;