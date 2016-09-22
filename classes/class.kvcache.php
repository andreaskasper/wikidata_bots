<?php


class kvcache {
	
	private static $data = null;

	public static function get($key) {
		if (self::$data == null) self::$data = unserialize(file_get_contents("kv.dbcache"));
		if (!isset(self::$data[$key])) return null;
		return self::$data[$key];
	}
	
	public static function set($key, $data) {
		if (self::$data == null) self::$data = unserialize(file_get_contents("kv.dbcache"));
		if (!isset(self::$data[$key]) OR self::$data[$key] != $data) {
			self::$data[$key] = $data;
			file_put_contents("kv.dbcache", serialize(self::$data));
		}
	}
	
	
	
	
}