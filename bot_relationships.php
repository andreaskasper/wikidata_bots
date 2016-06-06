<?php

require_once("app.config.php");


file_put_contents("todo.wiki",PHP_EOL);

//$g = wikidata::LastChangedNodes();
//print_r($g);

$personen = wikidata::query('CLAIM[21] AND (CLAIM[22] OR CLAIM[25] OR CLAIM[40])');
$i = 0;
foreach ($personen["items"] as $person_id) {
	$i++;
	
	$node = wikidata::nodelive($person_id);
	echo("pruefe Q".$person_id.' '.wikidata::name($node["labels"]).' '.number_format(100*$i/count($personen["items"]),2,",",".")."%".PHP_EOL);
	if (!checkP31($node)) continue;
	
	switch ($node["claims"]["P21"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"]) {
		case 6581097:
		case 44148: //männliches Geschlecht (Tier)
			$geschlecht = "m"; break;
		case 6581072:
			$geschlecht = "w"; break;
		default:
			addToDOWikiLine("# {{Q|".$person_id.'}} hat mit "{{Q|'.$node["claims"]["P21"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"].'}}" ein ungewöhnliches {{P|21}}');
	}
	
	if ($i < 25000) continue;
	
	//Vater
	if (isset($node["claims"]["P22"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"]) AND !isset($node["claims"]["P22"][0]["qualifiers"])) {
		if ($geschlecht == "m") setSohn($node["claims"]["P22"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"], $person_id);
		if ($geschlecht == "w") setTochter($node["claims"]["P22"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"], $person_id);
	}
	
	//Mutter
	if (isset($node["claims"]["P25"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"]) AND !isset($node["claims"]["P25"][0]["qualifiers"])) {
		if ($geschlecht == "m") setSohn($node["claims"]["P25"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"], $person_id);
		if ($geschlecht == "w") setTochter($node["claims"]["P25"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"], $person_id);
	}
	
	//Kinder
	if (isset($node["claims"]["P40"][0]))
	foreach ($node["claims"]["P40"] as $row) {
		if (isset($row["qualifiers"])) continue;
		if (!isset($row["mainsnak"]["datavalue"]["value"]["numeric-id"])) continue;
		if ($geschlecht == "m") setVater($row["mainsnak"]["datavalue"]["value"]["numeric-id"], $person_id);
		if ($geschlecht == "w") setMutter($row["mainsnak"]["datavalue"]["value"]["numeric-id"], $person_id);
	}

	
}
exit(1);


function setSohn($wid1, $wid2) {
	if (!checkP31(wikidata::nodelive($wid1))) return false;
	if (!is_child($wid1, $wid2)) {
		echo("Sohn: ".$wid1." ".$wid2.PHP_EOL);
		$d1 = wikidata::insertpropertylink($wid1, 40, $wid2);
		wikidata::insertquelleURL($d1["claim"]["id"], "https://www.wikidata.org/wiki/Q".$wid2);
		//addToDOWikiLine("# {{Q|".$wid1."}} ist der Sohn von {{Q|".$wid2."}}");
	}
	setCrossReferenz($wid1, $wid2);
}

function setTochter($wid1, $wid2) {
	if (!checkP31(wikidata::nodelive($wid1))) return false;
	if (!is_child($wid1, $wid2)) {
		echo("Tochter: ".$wid1." ".$wid2.PHP_EOL);
		$d1 = wikidata::insertpropertylink($wid1, 40, $wid2);
		wikidata::insertquelleURL($d1["claim"]["id"], "https://www.wikidata.org/wiki/Q".$wid2);
		//addToDOWikiLine("# {{Q|".$wid1."}} ist die Tochter von {{Q|".$wid2."}}");
	}
	setCrossReferenz($wid1, $wid2);
}

function setVater($wid1, $wid2) {
	$node = wikidata::nodelive($wid1);
	if (!checkP31($node)) return false;
	if (!isset($node["claims"]["P22"][0])) {
		echo("Vater: ".$wid1." ".$wid2.PHP_EOL);
		$d1 = wikidata::insertpropertylink($wid1, 22, $wid2);
		wikidata::insertquelleURL($d1["claim"]["id"], "https://www.wikidata.org/wiki/Q".$wid2);
		//addToDOWikiLine("# {{Q|".$wid1."}} ist der Vater von {{Q|".$wid2."}}");
	}
	setCrossReferenz($wid1, $wid2);
}

function setMutter($wid1, $wid2) {
	$node = wikidata::nodelive($wid1);
	if (!checkP31($node)) return false;
	if (!isset($node["claims"]["P25"][0])) {
		echo("Mutter: ".$wid1." ".$wid2.PHP_EOL);
		$d1 = wikidata::insertpropertylink($wid1, 25, $wid2);
		wikidata::insertquelleURL($d1["claim"]["id"], "https://www.wikidata.org/wiki/Q".$wid2);
		//addToDOWikiLine("# {{Q|".$wid1."}} ist die Mutter von {{Q|".$wid2."}}");
	}
	setCrossReferenz($wid1, $wid2);
}

function setCrossReferenz($wid1, $wid2) {
	//TODO
	return null; 
	$node1 = wikidata::nodelive($wid1);
	$node2 = wikidata::nodelive($wid2);
	if (!checkP31($node1)) return false;
	if (!checkP31($node2)) return false;
	$root = findNodeLink($node2, $wid1);
	if (!isset($root["references"])) return;
	echo("prüfe Kreuzreferent zwischen Q".$wid1." und Q".$wid2.PHP_EOL);
	print_r($root); exit(1);

}

function findNodeLink($node, $wid) {
	if (isset($node["claims"]["P22"])) foreach ($node["claims"]["P22"] as $row) if ($row["mainsnak"]["datavalue"]["value"]["numeric-id"] == $wid) return $row;
	if (isset($node["claims"]["P25"])) foreach ($node["claims"]["P25"] as $row) if ($row["mainsnak"]["datavalue"]["value"]["numeric-id"] == $wid) return $row;
	if (isset($node["claims"]["P40"])) foreach ($node["claims"]["P40"] as $row) if ($row["mainsnak"]["datavalue"]["value"]["numeric-id"] == $wid) return $row;
	return null;
}


function is_child($wid1, $wid2) {
	$node = wikidata::nodelive($wid1);
	if (!isset($node["claims"]["P40"])) return false;
	foreach ($node["claims"]["P40"] as $row) {
		if ($row["mainsnak"]["datavalue"]["value"]["numeric-id"] == $wid2) return true;
	}
	return false;
}

function checkP31($node) {
	$out = false;
	foreach ($node["claims"]["P31"] as $row) {
		$p = $row["mainsnak"]["datavalue"]["value"]["numeric-id"];
		switch ($p) {
			case 5: //Mensch
			case 15632617: //fiktiver Mensch
			case 4271324: //Sagenfigur
			case 20643955: //Menschliche Bibelfigur
			case 178885: //Gottheit
			case 95074: //fiktive Figur
			case 3247351: //fiktive Ente
			case 22989102: //griechische Gottheit
			case 17624054: //fiktive Gottheit
			case 22988604: //person der griechischen Mythologie
			case 10855242: //Rennpferd
			case 15966903: //legendäre Figur
				$out = true; break;
			default:
				//die("Neue Q".$p." ".wikidata::nodename($p));
		}
	}
	
	if (!$out) {
		foreach ($node["claims"]["P31"] as $row) {
			$p = $row["mainsnak"]["datavalue"]["value"]["numeric-id"];
				if (subcheckP31(wikidata::nodelive($p))) { $out = true; break; }
			}
		
	}
	
	if (!$out) addToDOWikiLine("# ungewöhnliches {{P|31}} in {{Q|".$node["id"]."}} um mit Relation-Pairs zu arbeiten.");
	return $out;
}

function subcheckP31($node) {
	echo("Subcheck P31 fuer ".$node["id"].PHP_EOL);
	$out = false;
	if (!isset($node["claims"]["P279"][0])) return $out;
	foreach ($node["claims"]["P279"] as $row) {
		$p = $row["mainsnak"]["datavalue"]["value"]["numeric-id"];
		switch ($p) {
			case 5: //Mensch
			case 15632617: //fiktiver Mensch
			case 4271324: //Sagenfigur
			case 20643955: //Menschliche Bibelfigur
			case 178885: //Gottheit
			case 95074: //fiktive Figur
			case 3247351: //fiktive Ente
			case 22989102: //griechische Gottheit
			case 17624054: //fiktive Gottheit
			case 22988604: //person der griechischen Mythologie
			case 10855242: //Rennpferd
			case 15966903: //legendäre Figur
			case 3658341: //literarische Figur
				$out = true; break;
			default:
				//die("Neue Q".$p." ".wikidata::nodename($p));
		}
	}
	
	if (!$out) {
		foreach ($node["claims"]["P279"] as $row) {
			$p = $row["mainsnak"]["datavalue"]["value"]["numeric-id"];
			if (subcheckP31(wikidata::nodelive($p))) { $out = true; break;}
		}
	}

	return $out;
}


function CLIPause($sec = 60) {
		for ($i = $sec; $i >= 0; $i--) {
		//echo("\033[0;35mPause: ".$i."\033[0m ".format_bytes(memory_get_usage())." ".format_bytes(memory_get_peak_usage())."  \r");
		echo("\033[0;35mPause: ".$i."\033[0m   \r");
		sleep(1);
	}
	echo("                                             \r");
}

function addToDOWikiLine($txt, $single = true) {
	$str = file_get_contents("todo.wiki");
	if (strpos($str,$txt) !== FALSE) return;
	file_put_contents("todo.wiki",$txt.PHP_EOL,FILE_APPEND);
	
}