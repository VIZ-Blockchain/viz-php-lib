<?php
namespace VIZ;

use BI\BigInteger;
use kornrunner\Keccak;

class Utils{
	static function prepare_voice_text_data($text,$reply=false,$share=false,$beneficiaries=false){
		$data=['t'=>$text];
		if($reply){
			$data['r']=$reply;
		}
		else{//share conflict with reply
			if($share){
				$data['s']=$share;
			}
		}
		if($beneficiaries){//json example: [{"account":"committee","weight":1000}]
			/* php example:
			[
				['account'=>'committee','weight'=>1000]
			]
			*/
			$data['b']=$beneficiaries;
		}
		return $data;
	}
	static function prepare_voice_text($previous,$text,$reply=false,$share=false,$beneficiaries=false){
		$object=[
			'p'=>(int)$previous,
			//'t'=>'t',//text as default type
			'd'=>Utils::prepare_voice_text_data($text,$reply,$share,$beneficiaries),
		];
		return $object;
	}
	static function voice_text($endpoint,$key,$account,$text,$reply=false,$share=false,$beneficiaries=false,$loop=false,$synchronous=false){
		if('object'==gettype($key)){
			if('VIZ\Key'==get_class($key)){
				$key=$key->encode();
			}
			else{
				return false;
			}
		}
		$tx=new Transaction($endpoint,$key);
		$previous=0;
		if(false!==$loop){
			$previous=$loop;
		}
		else{
			$account_data=$tx->api->execute_method('get_account',[$account,'V']);
			if(false!==$account_data){
				$previous=$account_data['custom_sequence_block_num'];
			}
			else{
				return false;
			}
		}
		$object=Utils::prepare_voice_text($previous,$text,$reply,$share,$beneficiaries);
		$tx_data=$tx->custom([],[$account],'V',json_encode($object));
		$tx_status=$tx->execute($tx_data['json'],$synchronous);
		if(!$synchronous){
			return (false!==$tx_status);
		}
		else{
			return ($tx_status['block_num']?$tx_status['block_num']:false);
		}
	}
	static function prepare_voice_publication_data($title,$markdown,$description,$image,$reply=false,$share=false,$beneficiaries=false){
		$data=[
			't'=>$title,
			'm'=>$markdown,
		];
		if($description){
			$data['d']=$description;
		}
		if($image){
			$data['i']=$image;
		}
		if($reply){
			$data['r']=$reply;
		}
		else{//share conflict with reply
			if($share){
				$data['s']=$share;
			}
		}
		if($beneficiaries){//json example: [{"account":"committee","weight":1000}]
			/* php example:
			[
				['account'=>'committee','weight'=>1000]
			]
			*/
			$data['b']=$beneficiaries;
		}
		return $data;
	}
	static function prepare_voice_publication($previous,$title,$markdown,$description,$image,$reply=false,$share=false,$beneficiaries=false){
		$object=[
			'p'=>(int)$previous,
			't'=>'p',//publication type
			'd'=>Utils::prepare_voice_publication_data($title,$markdown,$description,$image,$reply,$share,$beneficiaries),
		];
		return $object;
	}
	static function voice_publication($endpoint,$key,$account,$title,$markdown,$description,$image,$reply=false,$share=false,$beneficiaries=false,$loop=false,$synchronous=false){
		if('object'==gettype($key)){
			if('VIZ\Key'==get_class($key)){
				$key=$key->encode();
			}
			else{
				return false;
			}
		}
		$tx=new Transaction($endpoint,$key);
		$previous=0;
		if(false!==$loop){
			$previous=$loop;
		}
		else{
			$account_data=$tx->api->execute_method('get_account',[$account,'V']);
			if(false!==$account_data){
				$previous=$account_data['custom_sequence_block_num'];
			}
			else{
				return false;
			}
		}
		$object=Utils::prepare_voice_publication($previous,$title,$markdown,$description,$image,$reply,$share,$beneficiaries);
		$tx_data=$tx->custom([],[$account],'V',json_encode($object));
		$tx_status=$tx->execute($tx_data['json'],$synchronous);
		if(!$synchronous){
			return (false!==$tx_status);
		}
		else{
			return ($tx_status['block_num']?$tx_status['block_num']:false);
		}
	}
	static function prepare_voice_event($previous,$event){
		$object=[
			'p'=>(int)$previous,
			'e'=>$event,
		];
		return $object;
	}
	static function voice_event($endpoint,$key,$account,$event_type,$target_account=false,$target_block,$data_type=false,$data=false,$synchronous=false){
		if('object'==gettype($key)){
			if('VIZ\Key'==get_class($key)){
				$key=$key->encode();
			}
			else{
				return false;
			}
		}
		$tx=new Transaction($endpoint,$key);
		$previous=0;
		$account_data=$tx->api->execute_method('get_account',[$account,'VE']);
		if(false!==$account_data){
			$previous=$account_data['custom_sequence_block_num'];
		}
		else{
			return false;
		}

		$object=Utils::prepare_voice_event($previous,$event_type);
		$object['b']=(int)$target_block;
		if($target_account){
			if($target_account!=$account){
				$object['a']=$target_account;
			}
		}
		if('e'==$event_type){
			if((false===$data_type)||('t'===$data_type)){
				//$object['t']='t';//text as default type
				$object['d']=Utils::prepare_voice_text_data($data['text'],$data['reply'],$data['share'],$data['beneficiaries']);
			}
			if('p'===$data_type){
				$object['t']='p';//publication type
				$object['d']=Utils::prepare_voice_publication_data($data['title'],$data['markdown'],$data['description'],$data['image'],$data['reply'],$data['share'],$data['beneficiaries']);
			}
		}
		$tx_data=$tx->custom([],[$account],'VE',json_encode($object));
		$tx_status=$tx->execute($tx_data['json'],$synchronous);
		if(!$synchronous){
			return (false!==$tx_status);
		}
		else{
			return ($tx_status['block_num']?$tx_status['block_num']:false);
		}
	}
	// Base58 encoding/decoding functions - all credits go to https://github.com/stephen-hill/base58php
	// The MIT License (MIT) Copyright (c) 2014 Stephen Hill <stephen@gatekiller.co.uk>
	// Adapted for BI\BigInteger wrapper
	static function base58_encode($string,$alphabet='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'){
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
	static function base58_decode($base58,$alphabet='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'){
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
	static function aes_256_cbc_encrypt($data_bin,$key_bin,$iv=false){
		$preset_iv=true;
		if(false===$iv){
			$iv=random_bytes(openssl_cipher_iv_length('AES-256-CBC'));
			$preset_iv=false;
		}
		$encrypt=openssl_encrypt($data_bin,'AES-256-CBC',$key_bin,OPENSSL_RAW_DATA,$iv);
		if($encrypted=bin2hex($encrypt)){
			if($preset_iv){
				return $encrypted;
			}
			else{
				return [
					'iv'=>bin2hex($iv),
					'data'=>$encrypted,
				];
			}
		}
		else{
			return false;
		}
	}
	static function aes_256_cbc_decrypt($data_bin,$key_bin,$iv){
		if($decrypted=openssl_decrypt($data_bin,'AES-256-CBC',$key_bin,OPENSSL_RAW_DATA,$iv)){
			return $decrypted;
		}
		else{
			return false;
		}
	}
	//https://en.wikipedia.org/wiki/Variable-length_quantity
	static function vlq_create($data){
		$data_length=strlen($data);
		$digits=[];
		$bits=7;
		$c_bit=1<<7;
		do{
			$digit=($data_length%$c_bit);
			$data_length>>=$bits;
			$continue=($data_length>0);
			if($continue){
				$digit+=$c_bit;
			}
			$digits[]=$digit;
		}while($continue);
		$vlq='';
		foreach($digits as $digit){
			$vlq.=chr($digit);
		}
		return $vlq;
	}
	static function vlq_extract($data,$as_bytes=false){
		$digits=[];
		$bits=7;
		$num=0;
		do{
			$byte=$data[$num];
			$digit=ord($byte);
			if($as_bytes){
				$digits[]=$byte;
			}
			else{
				$digits[]=$digit;
			}
			$digit>>=$bits;
			$continue=($digit>0);
			if($continue){
				$num++;
			}
		}while($continue);
		return $digits;
	}
	static function vlq_calculate($digits,$as_bytes=false){
		$result=[];
		$current=0;
		$bits=7;
		$c_bit=1<<7;
		$shift=0;
		foreach($digits as $digit){
			if($as_bytes){
				$digit=ord($digit);
			}
			$current+=(($digit%$c_bit)<<$shift);
			if($digit<$c_bit){
				$result[]=$current;
				$current=0;
				$shift=0;
			} else {
				$shift += $bits;
			}
		}
		return $result[0];
	}
	static function privkey_hex_to_btc_wif($hex,$compressed=false){
		$privkey_hex='80'.$hex.($compressed?'01':'');
		$checksum=substr(hash('sha256',hash('sha256',hex2bin($privkey_hex),true),true),0,4);
		return Utils::base58_encode(hex2bin($privkey_hex).$checksum);
	}
	static function privkey_hex_to_ltc_wif($hex){
		$privkey_hex='b0'.$hex;
		$checksum=substr(hash('sha256',hash('sha256',hex2bin($privkey_hex),true),true),0,4);
		return Utils::base58_encode(hex2bin($privkey_hex).$checksum);
	}
	static function full_pubkey_hex_to_btc_address($hex,$network_id="\x00"){
		$pubkey_hash=$network_id.hash('ripemd160',hash('sha256',hex2bin($hex),true),true);
		$checksum=substr(hash('sha256',hash('sha256',$pubkey_hash,true),true),0,4);
		return Utils::base58_encode($pubkey_hash.$checksum);
	}
	static function full_pubkey_hex_to_ltc_address($hex,$network_id="\x30"){
		$pubkey_hash=$network_id.hash('ripemd160',hash('sha256',hex2bin($hex),true),true);
		$checksum=substr(hash('sha256',hash('sha256',$pubkey_hash,true),true),0,4);
		return Utils::base58_encode($pubkey_hash.$checksum);
	}
	static function full_pubkey_hex_to_eth_address($hex){
		return '0x'.substr(Keccak::hash(substr(hex2bin($hex),1),256),-40);
	}
	static function full_pubkey_hex_to_trx_address($hex){
		$prefix='41';
		$pubkey_hash=$prefix.substr(Keccak::hash(substr(hex2bin($hex),1),256),-40);
		$checksum=substr(hash('sha256',hash('sha256',hex2bin($pubkey_hash),true),true),0,4);
		return Utils::base58_encode(hex2bin($pubkey_hash).$checksum);
	}
}