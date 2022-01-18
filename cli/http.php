<?php

namespace cli;

use cli\exception\HttpNotFoundException;

class http{
	public const TYPE_SEARCH = "plugin";

	private array $cache = [];
	private string $cachefile;
	private string $cachefile1;
	private string $cachefile2;
	private bool $cachechanged = false;
	private string|null|false $token;

	private array $runtimeGithubCache = [];

	public function __construct(string $cachedir){
		$this->cachefile = $cachedir.DIRECTORY_SEPARATOR."cache.json";
		if(file_exists($this->cachefile)){
			$this->cache = json_decode(file_get_contents($this->cachefile), true, 512, JSON_THROW_ON_ERROR);
		}

		$this->cachefile1 = $cachedir.DIRECTORY_SEPARATOR."github_cache.json";
		if(file_exists($this->cachefile1)){
			$this->runtimeGithubCache = json_decode(file_get_contents($this->cachefile1), true, 512, JSON_THROW_ON_ERROR);
		}

		$this->cachefile2 = $cachedir.DIRECTORY_SEPARATOR."VirionListCache.json";
	}

	public function initToken(?string $token = null) : void{
		$this->token = $token ?? trim(shell_exec("composer -n config --global github-oauth.github.com"));
	}

	public function writeCache() : void{
		if(!$this->cachechanged){
			return;
		}
		file_put_contents($this->cachefile, json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
		file_put_contents($this->cachefile1, json_encode($this->runtimeGithubCache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

		$this->cachechanged = false;
	}

	public function getRawData(string $url, $data = false, $request = false) : string{
		$url = str_replace("https://api.github.com", "", $url);
		if(!str_starts_with($url, "https://github.com/")){
			$url = "https://api.github.com".$url;
		}
		echo "\n";
		if($request !== false){
			var_dump($request.": ".$url);
		}else if($data !== false){
			var_dump("POST: ".$url);
		}else{
			var_dump("GET: ".$url);
		}

		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Authorization: token '.$this->token,
		]);

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // オレオレ証明書対策
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);// Locationヘッダを追跡

//		$cacertdir = \phar::running();
//		if($cacertdir === ""){
//			$cacertdir = getcwd();
//		}
//		curl_setopt($curl, CURLOPT_CAINFO, $cacertdir.'/cacert.pem');

		if($request !== false) curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request);
		if($data !== false){
			curl_setopt($curl, CURLOPT_POST, TRUE);
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		}

		curl_setopt($curl, CURLOPT_USERAGENT, "USER_AGENT");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$return = curl_exec($curl);

		$errno = curl_errno($curl);
		$error = curl_error($curl);
		if($errno !== CURLE_OK){
			throw new \RuntimeException($error, $errno);
		}

		$statuscode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		//var_dump("StatusCode: ".$statuscode);
		curl_close($curl);

		//var_dump($data);
		//var_dump($test);
		sleep(1);
		if($statuscode >= 400){
			var_dump("StatusCode: ".$statuscode);
			throw new HttpNotFoundException("request failed: received status code \"".$statuscode."\"");
		}
		return $return;
	}

	/**
	 * @throws \JsonException
	 */
	public function get(string $url, $data = false, $request = false) : array{
		if(!isset($this->runtimeGithubCache[$url])){
			$this->runtimeGithubCache[$url] = $this->getRawData($url, $data, $request);
		}
		//$this->writeCache();
		$this->cachechanged = true;
		return json_decode($this->runtimeGithubCache[$url], true, 512, JSON_THROW_ON_ERROR);
	}

	public function search(string $plugin_name, bool $force = false) : array{
		if(!$force&&isset($this->cache[self::TYPE_SEARCH][$plugin_name])){
			return json_decode($this->cache[self::TYPE_SEARCH][$plugin_name], true, 512, JSON_THROW_ON_ERROR);
		}
		//exit();
		$url = 'https://poggit.pmmp.io/releases.json?name='.$plugin_name;
		var_dump("GET: ".$url);
		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // オレオレ証明書対策
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);// Locationヘッダを追跡

		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);

		//curl_setopt($curl, CURLOPT_CAINFO, '/path/to/cacert.pem');

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$return = curl_exec($curl);

		$errno = curl_errno($curl);
		$error = curl_error($curl);
		if($errno !== CURLE_OK){
			throw new \RuntimeException($error, $errno);
		}

		$statuscode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		//var_dump("StatusCode: ".$statuscode);
		curl_close($curl);
		$test = json_decode($return, true);
		sleep(1);
		if($statuscode >= 400){
			var_dump("StatusCode: ".$statuscode);
			throw new HttpNotFoundException("request failed: received status code \"".$statuscode."\"");
		}
		$this->cache[self::TYPE_SEARCH][$plugin_name] = $return;
		$this->cachechanged = true;
		return $test;
	}

	public function downloadLibrary(string $path, string $owner, string $name, string $projectname, string $version, string $branch) : void{
		$url = "https://poggit.pmmp.io/v.dl/".urlencode($owner)."/".urlencode($name)."/".urlencode($projectname)."/".urlencode(trim($version))."?branch=".urlencode(trim($branch));
		var_dump("GET: ".$url);
		$curl = curl_init($url);

		var_dump("disabled codes");
		exit();

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // オレオレ証明書対策
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);// Locationヘッダを追跡

		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);

		//curl_setopt($curl, CURLOPT_CAINFO, '/path/to/cacert.pem');

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$return = curl_exec($curl);

		$errno = curl_errno($curl);
		$error = curl_error($curl);
		if($errno !== CURLE_OK){
			throw new \RuntimeException($error, $errno);
		}

		file_put_contents($path, $return);

		$statuscode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		//var_dump("StatusCode: ".$statuscode);
		curl_close($curl);
		sleep(1);
		if($statuscode >= 400){
			var_dump("StatusCode: ".$statuscode);
			throw new HttpNotFoundException("request failed: received status code \"".$statuscode."\"");
		}
	}

	public function getPoggitPopularVirionList() : void{
		$url = "https://poggit.pmmp.io/v?top=10000";
		var_dump("GET: ".$url);
		var_dump("disabled codes");

//		$curl = curl_init($url);
//
//		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // オレオレ証明書対策
//		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);// Locationヘッダを追跡
//
//		curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36");
//
//		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
//		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
//
//		//curl_setopt($curl, CURLOPT_CAINFO, '/path/to/cacert.pem');
//
//		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//		$return = curl_exec($curl);
//
//		$errno = curl_errno($curl);
//		$error = curl_error($curl);
//		if($errno !== CURLE_OK){
//			throw new \RuntimeException($error, $errno);
//		}
//
//		$statuscode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
//		//var_dump("StatusCode: ".$statuscode);
//		curl_close($curl);
//		sleep(1);
//
//		preg_match_all('/<li>\s*?<h3>\s*?.*?<a href="\/ci\/(.*?\/.*?\/.*?)">(.*?)<\/a>.*?https:\/\/github\.com\/(.*?)\'.*?<p class="remark">(.*?), Used by/usm', $return, $m);
//		unset($m[0]);
//		file_put_contents($this->cachefile2, json_encode(array_values($m),JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
//
//		if($statuscode >= 400){
//			var_dump("StatusCode: ".$statuscode);
//			throw new HttpNotFoundException("request failed: received status code \"".$statuscode."\"");
//		}
	}
}