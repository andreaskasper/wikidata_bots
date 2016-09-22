<?php


class CLI {

	public static function echoc($txt, $color = "white") {
		switch ($color) {
			case "red":
				$a1 = "\033[31m"; $a2 = "\033[0m"; break;
			case "blue":
				$a1 = "\033[34m"; $a2 = "\033[0m"; break;
			case "cyan":
				$a1 = "\033[36m"; $a2 = "\033[0m"; break;
			case "yellow":
				$a1 = "\033[33m"; $a2 = "\033[0m"; break;
			case "lightred":
				$a1 = "\033[31m"; $a2 = "\033[0m"; break;
			case "gray":
				$a1 = "\033[30m"; $a2 = "\033[0m"; break;
			case "00f":
				$a1 = "\033[1;34m"; $a2 = "\033[0m"; break;
		
			default:
				$a1 = "";
				$a2 = "";
				break;
		}
		echo($a1);
		echo($txt);
		echo($a2);
	}
	
	
}


/**
 *  
 *  Black 0;30
Blue 0;34
Green 0;32
Cyan 0;36
Red 0;31
Purple 0;35
Brown 0;33
Light Gray 0;37 
Dark Gray 1;30
Light Blue 1;34
Light Green 1;32
Light Cyan 1;36
Light Red 1;31
Light Purple 1;35
Yellow 1;33
White 1;37
 *  
 *  
 */