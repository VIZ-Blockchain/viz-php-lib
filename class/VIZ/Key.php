<?php
namespace VIZ;

use VIZ\Utils;
use Elliptic\EC;

class Key{
	public $ec;
	public $private=false;
	public $bin='';
	public $hex='';
	function __construct($data='',$private=true){
		$this->ec=new EC('secp256k1');
		$this->private=$private;
		if($data){
			if(!$this->import_wif($data)){
				if(!$this->import_public($data)){
					if(preg_match('/^[0-9a-f]+$/i',$data)){
						$this->import_hex($data);
					}
					else{
						$this->import_bin($data);
					}
				}
			}
		}
		else{
			$this->import_hex('0000000000000000000000000000000000000000000000000000000000000000');
		}
	}
	function get_shared_key($public_key_encoded){
		if(!$this->private){
			return false;
		}
		$public_key=new Key($public_key_encoded,false);

		$ec_public_key=$this->ec->keyFromPublic($public_key->hex,'hex',true);
		$ec_private_key=$this->ec->keyFromPrivate($this->hex,'hex',true);

		$ec_shared_point=$ec_private_key->derive($ec_public_key->pub);
		$shared_key=new Key($ec_shared_point->toString(16),false);

		return $shared_key;
	}
	function random_str($length,$keyspace='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ:#@^&*()!?.,;"[]{}'){
		$pieces=[];
		$max=mb_strlen($keyspace,'8bit')-1;
		for($i=0;$i<$length;++$i){
			$pieces[]=$keyspace[random_int(0,$max)];
		}
		return implode('',$pieces);
	}
	function gen($seed='',$salt=true){
		if(true===$salt){
			$salt=$this->random_str(40);
		}
		$seed=$seed.$salt;
		$hex_key=hash('sha256',$seed);
		$this->import_hex($hex_key);
		$this->private=true;
		$wif=$this->encode();
		$public_key=$this->get_public_key();
		$encoded=$public_key->encode();
		return [$seed,$wif,$encoded,$public_key];
	}
	function gen_pair($seed='',$salt=''){
		if(!$salt){
			$salt=$this->random_str(40);
		}
		$seed=$salt.$seed;
		$hex_key=hash('sha256',$seed);
		$this->import_hex($hex_key);
		$this->private=true;
		$wif=$this->encode();
		$public_key=$this->get_public_key();
		$encoded=$public_key->encode();
		return [$wif,$encoded,$public_key];
	}
	function import_hex($hex){
		$this->bin=hex2bin($hex);
		$this->hex=$hex;
	}
	function import_bin($bin){
		$this->bin=$bin;
		$this->hex=bin2hex($bin);
	}
	function import_wif($wif){
		$wif_decoded=Utils::base58_decode($wif);
		if(false===$wif_decoded){
			return false;
		}
		$wif_checksum=substr($wif_decoded,-4);
		$wif_decoded_clear=substr($wif_decoded,0,-4);

		$checksum=hash('sha256',$wif_decoded_clear);
		$checksum=hash('sha256',hex2bin($checksum));
		$checksum=substr($checksum,0,8);
		if($checksum!=bin2hex($wif_checksum)){
			return false;
		}
		$check_version=substr($wif_decoded_clear,0,1);
		if('80'!=bin2hex($check_version)){
			return false;
		}
		$wif_decoded_clear=substr($wif_decoded_clear,1);
		$this->bin=$wif_decoded_clear;
		$this->hex=bin2hex($wif_decoded_clear);
		$this->private=true;
		return true;
	}
	function import_public($key){
		$clear_key=substr($key,3);
		$key_decoded=Utils::base58_decode($clear_key);
		if(false===$key_decoded){
			return false;
		}
		$key_checksum=substr($key_decoded,-4);
		$key_decoded_clear=substr($key_decoded,0,-4);
		$checksum=hash('ripemd160',$key_decoded_clear);
		$checksum=substr($checksum,0,8);
		if($checksum!=bin2hex($key_checksum)){
			return false;
		}
		$this->bin=$key_decoded_clear;
		$this->hex=bin2hex($key_decoded_clear);
		$this->private=false;
		return true;
	}
	function to_public(){
		$private_key=$this->ec->keyFromPrivate($this->hex,'hex',true);
		$this->hex=$private_key->getPublic(true,'hex');
		$this->bin=hex2bin($this->hex);
		$this->private=false;
		return true;
	}
	function get_public_key(){
		$copy=new Key($this->bin);
		$private_key=$copy->ec->keyFromPrivate($copy->hex,'hex',true);
		$copy->hex=$private_key->getPublic(true,'hex');
		$copy->bin=hex2bin($this->hex);
		$copy->private=false;
		return $copy;
	}
	function encode($prefix='VIZ'){
		if($this->private){//return wif
			$key='80'.$this->hex;
			$checksum=hash('sha256',hex2bin($key));
			$checksum=hash('sha256',hex2bin($checksum));
			$key=$key.substr($checksum,0,8);
			return Utils::base58_encode(hex2bin($key));
		}
		else{//return public key
			$key=$this->hex;
			$checksum=hash('ripemd160',hex2bin($key));
			$key=$key.substr($checksum,0,8);
			return $prefix.(Utils::base58_encode(hex2bin($key)));
		}
	}
	function sign($data){
		if(!$this->private){
			return false;
		}
		$data_hash=hash('sha256',$data);
		$private_key=$this->ec->keyFromPrivate($this->hex,'hex',true);
		$signature=$private_key->sign($data_hash,'hex',['canonical'=>true]);
		$signature_compact=$signature->toCompact('hex');
		return $signature_compact;
	}
	function verify($data,$signature){
		$data_hash=hash('sha256',$data);
		if($this->private){
			$public_copy=$this->get_public_key();
			$public_key=$this->ec->keyFromPublic($public_copy->hex,'hex',true);
		}
		else{
			$public_key=$this->ec->keyFromPublic($this->hex,'hex',true);
		}
		return $public_key->verify($data_hash,$signature);
	}
	function recover_public_key($data,$signature){
		$data_hash=hash('sha256',$data);
		$signature_recovery=hexdec(substr($signature,0,2)) - 27 - 4;
		$signature_recovery;
		$q=null;
		try{
			$q=$this->ec->recoverPubKey($data_hash,$signature,$signature_recovery,'hex');
		}
		catch(\Exception $e){
			return false;
		}
		$ec_key=$this->ec->keyFromPublic($q);
		$ec_public=$ec_key->getPublic(true,'hex');
		$public_key=new Key($ec_public,false);
		return $public_key->encode();
	}
}