<?php

class http{
	public const TYPE_SEARCH = "plugin";

	private array $cache = [];
	private string $cachefile;

	public function __construct(string $cachedir){
		$this->cachefile = $cachedir.DIRECTORY_SEPARATOR."cache.json";
		if(file_exists($this->cachefile)){
			$this->cache = json_decode(file_get_contents($this->cachefile), true, 512, JSON_THROW_ON_ERROR);
		}
	}

	public function writeCache() : void{
		file_put_contents($this->cachefile, json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
	}

	public function get(string $url, $data = false, $request = false, string $github_token = "testtoken") : array{
		if(strpos($url, "/") !== false){
			$url = str_replace("https://api.github.com", "", $url);
		}
		echo "\n";
		if($request !== false){
			var_dump($request.": ".$url);
		}else if($data !== false){
			var_dump("POST: ".$url);
		}else{
			var_dump("GET: ".$url);
		}

		$curl = curl_init("https://api.github.com".$url);

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // オレオレ証明書対策
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);// Locationヘッダを追跡

		//curl_setopt($curl, CURLOPT_CAINFO, '/path/to/cacert.pem');

		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Authorization: token '.$github_token,
		]);

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
		$test = json_decode($return, true);

		//var_dump($data);
		//var_dump($test);
		//sleep(1);
		if($statuscode >= 400){
			var_dump("StatusCode: ".$statuscode);
			throw new \RuntimeException("request failed: received status code \"".$statuscode."\"");
		}
		return $test;
	}

	public function search(string $plugin_name, bool $force = false) : array{
		if(!$force&&isset($this->cache[self::TYPE_SEARCH][$plugin_name])){
			return json_decode($this->cache[$plugin_name], true, 512, JSON_THROW_ON_ERROR);
		}
		$url = 'https://poggit.pmmp.io/releases.json?name='.$plugin_name;
		var_dump("GET: ".$url);
		$curl = curl_init("https://api.github.com".$url);

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
			throw new \RuntimeException("request failed: received status code \"".$statuscode."\"");
		}
		$this->cache[$plugin_name] = $return;
		return $test;
	}
}