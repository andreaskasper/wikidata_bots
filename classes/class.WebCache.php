<?php

/**
 * 
 * @author Andreas Kasper <djassi@users.sourceforge.net>
 * @category lucenzo
 * @copyright 2012 by Andreas Kasper
 * @name WebCache
 * @link http://www.plabsi.com Plabsi Weblabor
 * @license FastFoodLicense
 * @version 0.1.121031
 */

class WebCache {
	
	/**
	 * Zähler für die Webanfragen
	 * @static
	 * @var integer
	 */
	private static $WebRequestCounter = 0;
	
	public static $_log = array();

	/**
	 * Macht eine Webanfrage und gibt den Wert zurück. Wenn keine Daten geladen werden können, kommt NULL.
	 * @param string $url Webadresse
	 * @param integer $sec Cachelaufzeit in Sekunden
	 * @param string|mixed $needle Array oder String der Werte, die in der Antwort vorkommen müssen
	 * @return string|null Quellcode der Webseite oder NULL
	 * @static
	 */
	public static function get($url, $sec = 86400, $needle = "") {
		$local = sys_get_temp_dir()."/".substr(md5($url),0,2)."/".md5($url).".webcache";
		if (!file_exists(dirname($local))) mkdir(dirname($local),0777,true);
		if (file_exists($local) AND filemtime($local) > time()-$sec) return file_get_contents($local);
		
		self::$WebRequestCounter++;
		$start=microtime(true);
		$html = @file_get_contents($url);
		self::$_log[] = array("url" => $url, "time" => microtime(true)-$start);
		$j = true;
		if (is_string($needle) and ($needle != "")) $j = (strpos($html, $needle) !== FALSE);
		if (is_array($needle)) foreach ($needle as $a) if ($j AND (strpos($html, $a) === FALSE)) $j = false;;
		if ($j) { file_put_contents($local, $html); return $html; }
		return null;
	}
	
}