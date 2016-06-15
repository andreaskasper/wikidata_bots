<?php

/**
 * @name Class wikidata
 * @author Andreas Kasper <andreas.kasper@goo1.de>
 * @license FreeFoodLicense, CC-by-nc-sa non military
 * @package ASICMS
 * @warning Absolute Entwicklungsalpha!!! Nur unter Aufsicht nutzen!
 * @lastchanges WebCache so weit es geht entfernt für die Lokale Version. Vielleicht schreibe ich aber noch eine extra Version dafür.
 * @version 0.1.160520 
 */

class wikidata {
	
	private static $node_names = array();
	private static $is_loggedin = false;
	private static $bot_RequestsPerMinute = 20;
	private static $bot_RequestPause = 5;
	public static $bot_maxlag = 5;
	private static $log_request_time = array();
	public static $callback_sleep = null;

	/**
	 *  @param int $ttl default: 10 Tage
	 */
	public static function node ($id, $ttl = 864000) {
		$db = new SQL(0);
		$row = $db->cmdrow(0, 'SELECT * FROM wikidata_nodes WHERE id = {0} LIMIT 0,1', array($id));
		if (!isset($row["id"]) OR !isset($row["json"]) OR $row["json"] == "null" OR time()-$row["dt_changed"] > $ttl) {
		$html = file_get_contents("http://wikidata.org/entity/Q".($id+0).".json"); 
			$a = @json_decode($html,true);
			$out = current($a["entities"]);
			$w = array();
			$w["id"] = $id;
			$w["json"] = json_encode($out);
			$w["dt_changed"] = time();
			$db->CreateUpdate(0, "wikidata_nodes", $w);
			return $out;
		}
		$out = json_decode($row["json"],true);;
		if (isset($out["entities"]["Q".$id])) $out = $out["entities"]["Q".$id];
		return $out;
	}
	
	public static function nodelive ($id) {
		if (substr($id, 0, 1) == "Q") $id = substr($id, 1, 999);
		$str = file_get_contents("http://www.wikidata.org/w/api.php?action=wbgetentities&format=json&maxlag=".self::$bot_maxlag."&ids=Q".$id);
		//echo($str);
		$json = json_decode($str,true);
		$out = $json["entities"]["Q".$id];
		return $out;
	}
	
	/*public static function node($id, $ttl = 86400) {
		$db = new SQL(0);
		$is_updated = false;
		if ($ttl < 0) { $a = @json_decode(file_get_contents("http://wikidata.org/entity/Q".($id+0).".json?t=".time()),true,"}"); $is_updated = true; }
		else {
			$row = $db->cmdrow(0, 'SELECT * FROM wikidata_nodes WHERE id = {0} LIMIT 0,1', array($id));
			//echo(count($row));
			if (true OR !isset($row["id"]) OR time()-$row["dt_changed"] > $ttl)  {  $is_updated = true; }
			else {$a = json_decode($row["json"],true); return $a["entities"]["Q".$id]; }		
		}
		if ($is_updated == true) $db->CreateUpdate(0, "wikidata_nodes", array("id" => $id, "json" => $html, "dt_changed" => time()));
		//echo($db->lastcmd);
		if (!isset($a["entities"]["Q".$id])) return null;
		return $a["entities"]["Q".$id];
	}*/
	
	public static function nodeFromDB($id) {
		$db = new SQL(0);
		$row = $db->cmdrow(0, 'SELECT * FROM wikidata_nodes WHERE id = {0} LIMIT 0,1', array($id));
		//print_r($db->lastcmd);
		if (!isset($row["id"])) return null;
		$a = json_decode($row["json"],true);
		//print_r($a);
		if (!isset($a["labels"])) return null;
		return $a;
	}
	
	
	
	public static function nodebyFreebaseMid($mid, $ttl = 86400) {
		$a = self::query('string[646:"'.$mid.'"]', $ttl);
		if (!isset($a["items"][0])) return null;
		return self::node($a["items"][0], $ttl);
	}
	
	/*
	 * http://wdq.wmflabs.org/api_documentation.html
	 *
	 */
	public static function query($str, $ttl = 86400) {
		$url = "http://wdq.wmflabs.org/api?q=".urlencode($str);
		//echo($url);
		//return @json_decode(file_get_contents($url), true);
		return @json_decode(WebCache::get($url, $ttl, "}"),true);
	}
	
	public static function sparql($sql, $ttl = 86400) {
		$url = "https://query.wikidata.org/sparql?format=json&query=".urlencode($sql);
		return @json_decode(WebCache::get($url, $ttl, "}"),true);
	}
	
	public static function search($query, $ttl = 86400) {
		return json_decode(file_get_contents("http://www.wikidata.org/w/api.php?action=wbsearchentities&search=".urlencode($query)."&format=json&language=de&type=item&continue=0&maxlag=".self::$bot_maxlag), true);
		$resp = WebCache::getObject("http://www.wikidata.org/w/api.php?action=wbsearchentities&search=".urlencode($query)."&format=json&language=de&type=item&continue=0",$ttl);
		$data = json_decode($resp["data"], true);
		$data["from_cache"] = $resp["from_cache"];
		return $data;
	}
	
	public static function createnode($label, $description = null,$add_data = null) {
		self::login();
		$w = array();
		$w["labels"] = array("de" => array("language" => "de", "value" => $label),"en" => array("language" => "en", "value" => $label));
		if ($description != null) $w["descriptions"] = array("de" => array("language" => "de", "value" => $description));
		if ($add_data != null) $w = array_merge($w, $add_data);
		//$w["data"]["token"] = $w["token"];
		
		$w2 = array("action" => 'wbeditentity',"new" => "item");
		
		
		
		$w2["bot"] = 1;
		$w2["token"] = self::getEditToken();
		$w2["data"] =  json_encode($w);
		
		//print_r($w2);
		$data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=wbeditentity&new=item&maxlag=".self::$bot_maxlag."&format=json", $w2);
		
		/*
		
		//echo("http://www.wikidata.org/w/api.php?action=wbeditentity&data=".urlencode(json_encode($w))."&token=".self::getEditToken()."&format=jsonfm");
		//echo(self::getEditToken());
		$data = self::httpRequestJSON("http://www.wikidata.org/w/api.php?action=wbeditentity&new=item&&format=json", array("token" => self::getEditToken(),"data" => json_encode($w)));
		//$data = self::httpRequestJSON("http://www.wikidata.org/w/api.php?action=wbeditentity&data={%22labels%22:{%22de%22:%22".urlencode($label)."%22}}&token=".self::getEditToken()."&format=jsonfm");
		*/
		return $data;
	}
	
	public static function createnodedata($data = array()) {
		self::login();
		
		$w2 = array("action" => 'wbeditentity',"new" => "item");
		
		
		
		$w2["bot"] = 1;
		$w2["token"] = self::getEditToken();
		$w2["data"] =  json_encode($data);
		
		//print_r($w2);
		$data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=wbeditentity&new=item&maxlag=".self::$bot_maxlag."&format=json", $w2);
		
		/*
		
		//echo("http://www.wikidata.org/w/api.php?action=wbeditentity&data=".urlencode(json_encode($w))."&token=".self::getEditToken()."&format=jsonfm");
		//echo(self::getEditToken());
		$data = self::httpRequestJSON("http://www.wikidata.org/w/api.php?action=wbeditentity&new=item&&format=json", array("token" => self::getEditToken(),"data" => json_encode($w)));
		//$data = self::httpRequestJSON("http://www.wikidata.org/w/api.php?action=wbeditentity&data={%22labels%22:{%22de%22:%22".urlencode($label)."%22}}&token=".self::getEditToken()."&format=jsonfm");
		*/
		return $data;
	}
	
	public static function insertproperty($node, $property , $jsonORarray, $comment = null) {
		if (substr($node,0,1) == "Q") $node = substr($node,1,999); 
		if (substr($property,0,1) == "P") $property = substr($property,1,999); 
		if (is_array($jsonORarray)) $jsonORarray = json_encode($jsonORarray);
		self::login();
		
		$w = array("action" => 'wbcreateclaim',
'entity' => 'Q'.$node.'',
'property' => 'P'.$property.'',
'snaktype' => 'value',
'value' => $jsonORarray,
'bot' => 1,
"token" => self::getEditToken());
		if ($comment != null) $w["summary"] = $comment;
		$data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=wbcreateclaim&format=json&maxlag=".self::$bot_maxlag, $w);
		return $data;
	}
	
	public static function insertqualifier($claim, $property , $jsonORarray) {
		if (substr($property,0,1) == "P") $property = substr($property,1,999); 
		if (is_array($jsonORarray)) $jsonORarray = json_encode($jsonORarray);
		if (is_array($jsonORarray)) $jsonORarray = json_encode($jsonORarray);
		self::login();
		
		$w = array("action" => 'wbsetqualifier',
'claim' => $claim,
'property' => 'P'.$property.'',
'snaktype' => 'value',
'value' => $jsonORarray,
'bot' => 1,
"token" => self::getEditToken());
		$data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=wbsetqualifier&format=json&maxlag=".self::$bot_maxlag, $w);
		return $data;
	}
	
	public static function insertpropertylink($node, $property , $linkid) {
		if (substr($linkid,0,1) == "Q") $linkid = substr($linkid,1,999); 
		return self::insertproperty($node, $property, '{"entity-type":"item","numeric-id":'.$linkid.'}');
	}
	
	public static function insertpropertyvalue($node, $property , $value, $header = array()) {
		if (substr($node,0,1) == "Q") $node = substr($node,1,999); 
		if (substr($property,0,1) == "P") $property = substr($property,1,999); 
		self::login();
		
		$w = array("action" => 'wbcreateclaim',
'entity' => 'Q'.$node.'',
'property' => 'P'.$property.'',
'snaktype' => 'value',
'value' => $value,
'bot' => 1,
"token" => self::getEditToken());
		$data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=wbcreateclaim&format=json&maxlag=".self::$bot_maxlag, $w);
		return $data;
	}
	
	/*
	 * $propertyID 
	 *
	 */
	public static function insertreference($propertyID, $jsonORarray) {
		self::login();
		
		if (!isset($propertyID) OR trim($propertyID) == "") { new Exception("Ungültige Property-ID"); return null; }
		if (is_array($jsonORarray)) $jsonORarray = json_encode($jsonORarray);
		
		
		$w = array("action" => 'wbsetreference',
"format" => "json",
"statement" => $propertyID,
"snaks" => $jsonORarray,
'bot' => 1,
"token" => self::getEditToken());
	//print_r($w);
		$data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=wbcreateclaim&format=json&maxlag=".self::$bot_maxlag, $w);
		return $data;
	}
	
	public static function insertquelleURL($propertyID, $url, $header = array()) {
		return self::insertreference($propertyID, array("p854" => array(array("snaktype" => "value", "property" => "p854", "datatype" => "url", "datavalue" => array("type" => "string", "value" => $url)))));
	}
	
	
	public static function qencode($id) {
		return self::convbase($id,"0123456789", "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
	}

	public static function qdecode($id) {
		return self::convbase($id,"0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz","0123456789");
	}

	public static function getWikidataServices($user = null, $pass = null) {
		$api = new MediawikiApi( "http://www.wikidata.org/w/api.php" );
		if ($user == null) $user = $_ENV["wikidata"]["user"];
		if ($pass == null) $pass = $_ENV["wikidata"]["pass"];
		$api->login( new ApiUser( $user, $pass));
		$services = new WikibaseFactory( $api );
	}

	public static function name($array) {
		if (isset($array["de"]["value"])) return $array["de"]["value"];
		if (isset($array["en"]["value"])) return $array["en"]["value"];
		if (count($array) > 0)
		foreach ($array as $row) return $row["value"];
		return "";
	}
	
	public static function date2str($array) {
		$v = strtotime($array["time"]);
		$month_names_short = array("Jan","Feb","Mär","Apr","Mai","Jun","Jul","Aug","Sep","Okt","Nov","Dez");
		$month_names = array("Januar","Februar","März","April","Mai","Juni","Juli","August","September","Oktober","November","Dezember");
		preg_match ("@(?P<year>[\+\-][0-9]+)-(?P<month>[0-1][0-9])-(?P<day>[0-3][0-9])@", $array["time"], $m);
		switch ($array["precision"]) {
			case "7": return ($m["year"]/100+1).".Jahrhundert";
			case "9": return $m["year"]+0;
			case "10": return $month_names[$m["month"]-1]." ".($m["year"]+0);
			case "11": return $m["day"].".".$m["month"].".".($m["year"]+0);
			default: return $array["time"]."(".$array["precision"].")";
		}
		return null;
	}
	
	public static function date2strtotime($array) {
		$v = strtotime($array["time"]);
		preg_match ("@(?P<year>[\+\-][0-9]+)-(?P<month>[0-1][0-9])-(?P<day>[0-3][0-9])@", $array["time"], $m);
		switch ($array["precision"]) {
			case "9": return strtotime(($m["year"]+0)."-07-01");
			case "11": return strtotime($m["day"].".".$m["month"].".".($m["year"]+0));
			default: return $array["time"]."(".$array["precision"].")";
		}
		return null;
	}
	
	public static function date2year($array) {
		$v = strtotime($array["time"]);
		preg_match ("@(?P<year>[\+\-][0-9]+)-(?P<month>[0-1][0-9])-(?P<day>[0-3][0-9])@", $array["time"], $m);
		switch ($array["precision"]) {
			case "7": return ($m["year"]+0); //Jahrhundert
			case "9": return ($m["year"]+0);
			case "10": return ($m["year"]+0);
			case "11": return ($m["year"]+0);
			default: return $array["time"]."(".$array["precision"].")";
		}
		return null;
	}
	
	public static function nodename($id, $ttl = 8640000) {
		if (substr($id,0,1) == "Q") $id = substr($id,1,9999);
		if (isset(self::$node_names[$id])) return self::$node_names[$id];
		$a = self::nodelive($id);
		//print_r($a);
		$b = self::name($a["labels"]);
		self::$node_names[$id] = $b;
		return $b;
	}
	
	public static function nodenameFromDB($id) {
		if (substr($id,0,1) == "Q") $id = substr($id,1,9999);
		if (isset(self::$node_names[$id])) return self::$node_names[$id];
		$a = self::nodeFromDB($id);
		if (!isset($a["labels"])) return "�";
		$b = self::name($a["labels"]);
		self::$node_names[$id] = $b;
		return $b;
	}
	
	/*zusätzliche Hilfsfunk*/
	
	public static function convbase($numberInput, $fromBaseInput, $toBaseInput) {
		if ($fromBaseInput==$toBaseInput) return $numberInput;
		$fromBase = str_split($fromBaseInput,1);
		$toBase = str_split($toBaseInput,1);
		$number = str_split($numberInput,1);
		$fromLen=strlen($fromBaseInput);
		$toLen=strlen($toBaseInput);
		$numberLen=strlen($numberInput);
		$retval='';
		if ($toBaseInput == '0123456789') {
			$retval=0;
			for ($i = 1;$i <= $numberLen; $i++)
				$retval = bcadd($retval, bcmul(array_search($number[$i-1], $fromBase),bcpow($fromLen,$numberLen-$i)));
			return $retval;
		}
		if ($fromBaseInput != '0123456789') $base10=self::convbase($numberInput, $fromBaseInput, '0123456789');
		else $base10 = $numberInput;
		if ($base10<strlen($toBaseInput)) return $toBase[$base10];
		while($base10 != '0') {
			$retval = $toBase[bcmod($base10,$toLen)].$retval;
			$base10 = bcdiv($base10,$toLen,0);
		}
		return $retval;
	}
	
	public static function login($user = null, $pass = null, $token = "") {
		if (self::$is_loggedin) return true;
		if ($user == null) $user = $_ENV["wikidata"]["user"];
		if ($pass == null) $pass = $_ENV["wikidata"]["pass"];
        $params = array("action" => "login", "lgname" => $user, "lgpassword" => $pass);
        if (!empty($token)) {
            $params["lgtoken"] = $token;
        }

        $data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=login&format=json&maxlag=".self::$bot_maxlag, $params);
		$token = $data["login"]["token"];
		$params["lgtoken"] = $token;
		//print_r($params); exit(1);
		$data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=login&format=json&maxlag=".self::$bot_maxlag, $params);
		self::$is_loggedin = true;
	}
	
	public static function getEditToken() {
		$data = self::httpRequestJSON("https://www.wikidata.org/w/api.php?action=query&meta=tokens&type=csrf&format=json&maxlag=".self::$bot_maxlag);
		return $data["query"]["tokens"]["csrftoken"];
	}
	
	public static function httpRequest($url, $post="") {
        global $settings;
		
		if (count(self::$log_request_time) >= self::$bot_RequestsPerMinute) {
			$a = array_shift(self::$log_request_time);
			if (time()-60 < $a) { //wir müssen warten, sonst ist wikidata böse
				trigger_error("Wartezeit: ".($a-(time()-60))."sec" ,E_USER_NOTICE);
				/*if (self::$callback_sleep != null) call_user_func(self::$callback_sleep, $a-(time()-60)); else*/ sleep($a-(time()-60));
			}
		}
		
		
		if (self::$bot_RequestPause > 0) /*if (self::$callback_sleep != null) call_user_func(self::$callback_sleep, self::$bot_RequestPause); else*/ sleep(self::$bot_RequestPause);
		

        $ch = curl_init();
        //Change the user agent below suitably
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
        curl_setopt($ch, CURLOPT_URL, ($url));
        curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //SSL Prüfung ausschalten...
        curl_setopt ($ch, CURLOPT_COOKIEFILE, __DIR__."/wikidata_cookies.tmp");
        curl_setopt ($ch, CURLOPT_COOKIEJAR, __DIR__."/wikidata_cookies.tmp");
        if (!empty($post)) curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($post));
        //UNCOMMENT TO DEBUG TO output.tmp
        //curl_setopt($ch, CURLOPT_VERBOSE, true); // Display communication with server
        //$fp = fopen("output.tmp", "w");
        //curl_setopt($ch, CURLOPT_STDERR, $fp); // Display communication with server
        
        $xml = curl_exec($ch);
        
        if (!$xml) {
                throw new Exception("Error getting data from server ($url ".var_export($post,true)."): " . curl_error($ch));
        }

        curl_close($ch);
        //print_r($xml);
        return $xml;
}

	
	public static function httpRequestJSON($url, $post="") {
        return json_decode(self::httpRequest($url, $post),true);
}
	
	public static function ClearNodeCache($id = 0) {
		$db = new SQL(0);
		$db->cmd(0, 'DELETE FROM wikidata_nodes WHERE id = "{0}" LIMIT 1', true, array($id));
	}

	/**
	  * Gibt die letzten geänderten Nodes aus.
	  */
	public static function LastChangedNodes() {
		$str = file_get_contents("https://www.wikidata.org/w/index.php?title=Special:RecentChanges&limit=100");
		preg_match_all("@Q([0-9]+)@", $str, $m);
		$out = array();
		foreach ($m[0] as $a) if (!in_array($a, $out)) $out[] = $a;
		return $out;
	}	

}