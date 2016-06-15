<?php

class wikidataNode {
	
	private $_data = null;
	private $_id = null;
	private $_live = false;

	public function __construct($nodeID = null) {
		if ($nodeID != null AND !preg_match("@^Q[0-9]+$@", $nodeID)) trigger_error("Ungültige Node-ID", E_USER_ERROR);
		if ($nodeID != null) $this->_id = $nodeID;
	}
	
	public static function newfromdata($json) {
		$instance = new wikidataNode();
		$instance->_loadJSON($json);
	}
	
	public function name($lang = null) {
		$this->_loadnode();
		return wikidata::name($this->_data["labels"]);
	}
	
	public function id() {
		$this->_loadnode();
		return $this->_data["id"];
	}
	
	public function idn() {
		return substr($this->id(),1,999)+0;
	}
	
	public function livedata($value = null) {
		if ($value != null) $this->_live = $value;
		else return $this->_live;
	}
	
	public function claims() {
		$this->_loadnode();
		return $this->_data["claims"];
	}
	
	public function claim($propertyID) {
		$this->_loadnode();
		if (is_numeric($propertyID)) $okay = true;
		elseif(preg_match("@^P(?P<id>[0-9]+)$@",$propertyID, $m)) $propertyID = $m["id"];
		else throw new Exception("Ungültiges Property-ID-Format");
		$out = array();
		foreach ($this->_data["claims"]["P".$propertyID] as $row) {
			$out[] = new wikidataClaim($row, $this);
		}
		return $out;
	}
	
	public function hasclaimlink($property = null, $wikidataID = null) {
		$this->_loadnode();
		if ($wikidataID != null AND preg_match("@^Q[0-9]+$@", $wikidataID)) $wikidataID = substr($wikidataID, 1, 999)+0;
		if ($property == null AND $wikidataIDNr == null) return null;
		if ($wikidataID == null) return isset($this->_data["claims"][$property][0]);
		if ($property != null) {
			if (isset($this->_data["claims"][$property])) 
				foreach ($this->_data["claims"][$property] as $row) 
					if (isset($row["mainsnak"]["datavalue"]["value"]["numeric-id"]) AND $row["mainsnak"]["datavalue"]["value"]["numeric-id"] == $wikidataID) return true;
			return false;
		} else {
			foreach ($this->_data["claims"] as $row1) foreach ($row1 as $row) if (isset($row["mainsnak"]["datavalue"]["value"]["numeric-id"]) AND $row["mainsnak"]["datavalue"]["value"]["numeric-id"] == $wikidataID) return true;
			return false;
		}
	}
	
	public function insertclaim($propertyID, $jsonORarray, $comment = null) {
		$this->livedata(true);
		$this->_loadnode();
		$a = wikidata::insertproperty($this->id(), $propertyID, $jsonORarray, $comment);
		if (isset($a["error"])) { print_r($jsonORarray); print_r($a); throw new Exception("Fehler beim eintragen"); }
		if ($a["success"] == 1) return new wikidataClaim($a["claim"], $this);
		return null;
	}
	
	public function insertclaimlink($propertyID, $wikidataID, $comment = null) {
		if (substr($wikidataID,0,1) == "Q") $wikidataID = substr($wikidataID,1,999); 
		return $this->insertclaim($propertyID, '{"entity-type":"item","numeric-id":'.$wikidataID.'}');
	}
	
	public function insertclaimyear($propertyID, $year, $comment = null) {
		return $this->insertclaim($propertyID, '{"time":"+'.$year.'-00-00T00:00:00Z","timezone":0,"before":0,"after":0,"precision":9,"calendarmodel":"http://www.wikidata.org/entity/Q1985727"}');
	}
	
	public function insertclaimstring($propertyID, $txt, $comment = null) {
		return $this->insertclaim($propertyID, '"'.$txt.'"');
	}
	
	public function setlabel($txt, $lang = "de", $comment = null) {
		if ($txt == null)  {trigger_error("Text ist null.", E_USER_WARNING); return;}
		wikidata::login();
		
		$d1 = array("labels" => array( $lang => array("language" => $lang, "value" => $txt)));
		
		$w = array("action" => 'wbeditentity',
			"format" => "json",
			"id" => $this->id(), 
			"data" => json_encode($d1),
			"baserevid" => $this->lastrevid(),
			'bot' => 1,
			"token" => wikidata::getEditToken());
		if ($comment != null) $w["summary"] = $comment;
		//print_r($w);
		$data = wikidata::httpRequestJSON("https://www.wikidata.org/w/api.php?action=wbeditentity&format=json&maxlag=".wikidata::$bot_maxlag, $w);
		if (isset($data["error"])) { print_r($w); print_r($data); trigger_error("Fehler in WD-Anfrage", E_USER_ERROR); }
		$this->_refreshnode();
		return $data;
	}
	
	public function setdescription($txt, $lang = "de", $comment = null) {
		if ($txt == null) { trigger_error("Text ist null.", E_USER_WARNING); return;}
		if (strpos($txt, " Dick ") !== FALSE) { trigger_error("Die Node-Beschreibung enthält 'Dick' (Trolling Word)", E_USER_NOTICE); return; }
		wikidata::login();
		
		$d1 = array("descriptions" => array( $lang => array("language" => $lang, "value" => $txt)));
		
		$w = array("action" => 'wbeditentity',
			"format" => "json",
			"id" => $this->id(), 
			"data" => json_encode($d1),
			"baserevid" => $this->lastrevid(),
			'bot' => 1,
			"token" => wikidata::getEditToken());
		if ($comment != null) $w["summary"] = $comment;
		//print_r($w);
		$data = wikidata::httpRequestJSON("https://www.wikidata.org/w/api.php?action=wbeditentity&format=json&maxlag=".wikidata::$bot_maxlag, $w);
		if (isset($data["error"])) { print_r($w); print_r($data); trigger_error("Fehler in WD-Anfrage", E_USER_ERROR); }
		$this->_refreshnode();
		return $data;
	}
	
	public function lastrevid() {
		$this->_loadnode();
		return $this->_data["lastrevid"];
	}
	
	public function json() {
		$this->_loadnode();
		return $this->_data;
	}
	
	protected function _loadJSON($json) {
		if (is_array($json)) $json = json_decode($json,true);
		$this->_data = $json;
	}
	
	private function _loadnode() {
		if ($this->_data != null) return;
		if ($this->_live) $this->_refreshnode();
		else {
			$str = WebCache::get("http://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=".$this->_id, 86400);
			$json = json_decode($str,true);
			$this->_data = $json["entities"][$this->_id];
			
			
		}
	}
	
	private function _refreshnode() {
		$str = file_get_contents("http://www.wikidata.org/w/api.php?action=wbgetentities&format=json&maxlag=".wikidata::$bot_maxlag."&ids=".$this->_id);
		$json = json_decode($str,true);
		$this->_data = $json["entities"][$this->_id];
	}
}