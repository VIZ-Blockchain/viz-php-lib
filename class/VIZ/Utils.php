<?php
namespace VIZ;

use BI\BigInteger;

class Utils{
	// Base58 encoding/decoding functions - all credits go to https://github.com/stephen-hill/base58php
	// The MIT License (MIT) Copyright (c) 2014 Stephen Hill <stephen@gatekiller.co.uk>
	// Adapted for BI\BigInteger wrapper
	function base58_encode($string){
		$alphabet='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
		$base=strlen($alphabet);
		// Type validation
		if(is_string($string) === false){
			return false;
		}
		// If the string is empty, then the encoded string is obviously empty
		if(strlen($string) === 0){
			return '';
		}
		// Now we need to convert the byte array into an arbitrary-precision decimal
		// We basically do this by performing a base256 to base10 conversion
		$hex=unpack('H*',$string);
		$hex=reset($hex);
		$decimal=new BigInteger($hex,16);
		// This loop now performs base 10 to base 58 conversion
		// The remainder or modulo on each loop becomes a base 58 character
		$output='';
		while($decimal->cmp($base) >= 0){
			list($decimal,$mod)=$decimal->divQR($base);
			$output.=$alphabet[$mod->toNumber()];
		}
		// If there's still a remainder, append it
		if($decimal->cmp(0) > 0){
			$output.=$alphabet[$decimal->toNumber()];
		}
		// Now we need to reverse the encoded data
		$output=strrev($output);
		// Now we need to add leading zeros
		$bytes=str_split($string);
		foreach($bytes as $byte){
			if($byte === "\x00"){
				$output=$alphabet[0].$output;
				continue;
			}
			break;
		}
		return (string)$output;
	}
	function base58_decode($base58){
		$alphabet='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
		$base=strlen($alphabet);
		// Type Validation
		if(is_string($base58) === false){
			return false;
		}
		// If the string is empty, then the decoded string is obviously empty
		if(strlen($base58) === 0){
			return '';
		}
		$indexes=array_flip(str_split($alphabet));
		$chars=str_split($base58);
		// Check for invalid characters in the supplied base58 string
		foreach($chars as $char){
			if(isset($indexes[$char]) === false){
				return false;
			}
		}
		// Convert from base58 to base10
		$decimal=new BigInteger($indexes[$chars[0]],10);
		for($i=1, $l=count($chars); $i < $l; $i++){
			$decimal=$decimal->mul($base);
			$decimal=$decimal->add($indexes[$chars[$i]]);
		}
		// Convert from base10 to base256 (8-bit byte array)
		$output='';
		while($decimal->cmp(0) > 0){
			list($decimal,$byte)=$decimal->divQR(256);
			$output=pack('C',$byte->toNumber()).$output;
		}
		// Now we need to add leading zeros
		foreach($chars as $char){
			if($indexes[$char] === 0){
				$output="\x00".$output;
				continue;
			}
			break;
		}
		return $output;
	}
}