<?php
namespace VIZ;

use DateTime;
use DateTimeZone;
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
			$this->import_hex('000000000000000000000000000000000000000000000000000000000000000000');
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
		return hash('sha512',hex2bin($ec_shared_point->toString(16)),false);
	}
	function encode_memo($public_key_encoded,$memo){
		if(!$this->private){
			return false;
		}
		$shared_key=hex2bin($this->get_shared_key($public_key_encoded));

		$current_public_key=$this->get_public_key();
		$from=$current_public_key->hex;

		$public_key=new Key($public_key_encoded,false);
		$to=$public_key->hex;

		//js compability encrypted_memo https://github.com/VIZ-Blockchain/viz-js-lib/blob/master/src/auth/serializer/src/operations.js#L83
		$result='';
		$result.=hex2bin($from);
		$result.=hex2bin($to);

		$nonce_bin=random_bytes(8);
		$encryption_key=hash('sha512',$nonce_bin.$shared_key,true);
		$checksum=hash('sha256',$encryption_key,true);
		$checksum_bin=substr($checksum,0,4);

		$result.=$nonce_bin;
		$result.=$checksum_bin;

		$key=substr($encryption_key,0,32);
		$iv=substr($encryption_key,32,16);

		$memo_vlq=Utils::vlq_create($memo);

		$encrypted=Utils::aes_256_cbc_encrypt($memo_vlq.$memo,$key,$iv);
		if(false===$encrypted){
			return false;
		}
		else{
			$encrypted_bin=hex2bin($encrypted);
			$encrypted_vlq=Utils::vlq_create($encrypted_bin);
			$result.=$encrypted_vlq.$encrypted_bin;
		}
		$crypted=Utils::base58_encode($result);
		return $crypted;
	}
	function decode_memo($memo){
		$js_data=Utils::base58_decode($memo);
		if(false===$js_data){
			return false;
		}
		$offset=0;
		$len=33;
		$from_bin=substr($js_data,$offset,$len);
		$from_hex=bin2hex($from_bin);
		$from_key=new Key($from_hex,false);
		$from=$from_key->encode();
		$offset+=$len;

		$to_bin=substr($js_data,$offset,$len);
		$to_hex=bin2hex($to_bin);
		$to_key=new Key($to_hex,false);
		$to=$to_key->encode();
		$offset+=$len;

		$shared_key=false;
		$current_public_key=$this->get_public_key();
		if($current_public_key->encode()==$from){
			$shared_key=hex2bin($this->get_shared_key($to));
		}
		else{
			$shared_key=hex2bin($this->get_shared_key($from));
		}

		$len=8;
		$nonce_bin=substr($js_data,$offset,$len);
		$nonce=unpack('Q',$nonce_bin)[1];//uint64
		$nonce_hex=bin2hex($nonce_bin);
		$offset+=$len;

		$len=4;
		$check_bin=substr($js_data,$offset,$len);
		$check=unpack('L',$check_bin)[1];//uint32
		$check_hex=bin2hex($check_bin);
		$offset+=$len;

		$encrypted_bin=substr($js_data,$offset);

		$data_length=strlen($encrypted_bin);
		$vstring_length_bytes=1;
		$vstring_data_length=$data_length-$vstring_length_bytes;
		$devider=128;
		$vstring_128=$vstring_data_length/$devider;
		while($vstring_128>=1){
			$vstring_length_bytes++;
			$devider=$devider*128;
			$vstring_data_length=$data_length-$vstring_length_bytes;
			$vstring_128=$vstring_data_length/$devider;
		}
		$encrypted_bin=substr($encrypted_bin,$vstring_length_bytes);

		$encrypted_hex=bin2hex($encrypted_bin);

		$encryption_key=hash('sha512',$nonce_bin.$shared_key,true);
		$key=substr($encryption_key,0,32);
		$iv=substr($encryption_key,32,16);

		$checksum=hash('sha256',$encryption_key,true);
		$checksum=substr($checksum,0,4);
		$checksum=unpack('L',$checksum)[1];

		if($checksum==$check){
			$decoded=Utils::aes_256_cbc_decrypt($encrypted_bin,$key,$iv);
			if(false===$decoded){
				return false;
			}
			else{
				$data_length=strlen($decoded);
				$vstring_length_bytes=1;
				$vstring_data_length=$data_length-$vstring_length_bytes;
				$devider=128;
				$vstring_128=$vstring_data_length/$devider;
				while($vstring_128>=1){
					$vstring_length_bytes++;
					$devider=$devider*128;
					$vstring_data_length=$data_length-$vstring_length_bytes;
					$vstring_128=$vstring_data_length/$devider;
				}
				$decoded=substr($decoded,$vstring_length_bytes);
				return $decoded;
			}
		}
		else{
			return false;
		}
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
		//compact/compressed
		$copy->hex=$private_key->getPublic(true,'hex');
		$copy->bin=hex2bin($this->hex);
		$copy->private=false;
		return $copy;
	}
	function get_public_key_hex(){
		$private_key=$this->ec->keyFromPrivate($this->hex,'hex',true);
		//compact/compressed (x03/x02 + x)
		return $private_key->getPublic(true,'hex');
	}
	function get_full_public_key_hex(){
		$private_key=$this->ec->keyFromPrivate($this->hex,'hex',true);
		//full representation (uncompressed: x04 + x + y)
		return $private_key->getPublic(false,'hex');
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
	function auth($account,$domain,$action='auth',$authority='regular'){
		$nonce=1;
		$time=time()-(new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime());
		$data=false;
		$signature=false;
		while(!$signature){
			$data=$domain.':'.$action.':'.$account.':'.$authority.':'.$time.':'.$nonce;
			$signature=$this->sign($data);
			if(false===$signature){
				$nonce++;
			}
		}
		return [$data,$signature];
	}
}