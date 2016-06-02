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
	echo("pruefe Q".$person_id.' '.wikidata::name($node["labels"]).' '.$i.'/'.count($personen["items"]).PHP_EOL);
	
	switch ($node["claims"]["P21"][0]["mainsnak"]["datavalue"]["value"]["numeric-id"]) {
		case 6581097:
			$geschlecht = "m"; break;
		case 6581072:
			$geschlecht = "w"; break;
		default:
			addToDOWikiLine("# {{Q|".$person_id."}} hat ein ungewöhnliches {{P|21}}");
	}
	
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
		if ($geschlecht == "m") setVater($row["mainsnak"]["datavalue"]["value"]["numeric-id"], $person_id);
		if ($geschlecht == "w") setMutter($row["mainsnak"]["datavalue"]["value"]["numeric-id"], $person_id);
	}

	
}
exit(1);


function setSohn($wid1, $wid2) {
	if (!is_child($wid1, $wid2)) {
		echo("Sohn: ".$wid1." ".$wid2.PHP_EOL);
		$d1 = wikidata::insertpropertylink($wid1, 40, $wid2);
		wikidata::insertquelleURL($d1["claim"]["id"], "https://www.wikidata.org/wiki/Q".$wid2);
		//addToDOWikiLine("# {{Q|".$wid1."}} ist der Sohn von {{Q|".$wid2."}}");
	}
	setCrossReferenz($wid1, $wid2);
}

function setTochter($wid1, $wid2) {
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