<?php

namespace wikidata;

class Claim {

	private $_parentNode = null;
	private $_data = null;
	
	
	public function __construct(Array $params) {
		if (isset($params["data"])) {
			$this->_data = $params["data"];
		}
	}
	
	public function insertReference($jsonOrArray) {
		return \wikidata::insertreference($this->_data["id"], $jsonOrArray);
	}
	
	public function insertQualifier($property, $jsonOrArray) {
		return \wikidata::insertqualifier($this->_data["id"], $property, $jsonOrArray);
	}
	
	public function insertQualifierLink($property, $nodeIDn) {
		return \wikidata::insertqualifier($this->_data["id"], $property, '{"entity-type":"item","numeric-id":'.$nodeIDn.'}');
	}
	
	
	public function json() {
		return $this->_data;
	}
	
	
	
	
	
	
}