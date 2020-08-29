<?php
namespace VIZ;

use DateTime;
use VIZ\Key;
use VIZ\JsonRPC;
use VIZ\Utils;

class Transaction{
	public $api;
	public $chain_id='2040effda178d4fffff5eab7a915d4019879f5205cc5392e4bcced2b6edda0cd';

	public $queue=false;
	public $queue_arr=array();

	public $private_keys_count=0;
	public $private_keys=array();
	public $signatures=array();

	function __construct($endpoint='',$private_key=''){
		$this->api=new JsonRPC($endpoint);
		$this->add_private_key($private_key);
	}
	function set_private_key($private_key){
		if($private_key){
			if(0==$this->private_keys_count){
				$this->private_keys[$this->private_keys_count]=new Key($private_key);
				$this->private_keys_count++;
			}
			else{
				$this->private_keys[$this->private_keys_count - 1]=new Key($private_key);
			}
		}
	}
	function add_private_key($private_key){
		if($private_key){
			$this->private_keys[$this->private_keys_count]=new Key($private_key);
			$this->private_keys_count++;
		}
	}
	function __call($name,$attr){
		$build_operation='build_'.$name;
		$operation=call_user_func_array(array($this,$build_operation),$attr);
		if($this->queue){
			$this->queue_arr[]=$operation;
		}
		else{
			$tx=$this->build($operation[0],$operation[1],1);
			return $tx;
		}
	}
	function execute($transaction_json){
		return $this->api->execute_method('broadcast_transaction',$transaction_json,false);
	}
	function build($operations_json,$operations_data,$operations_count){
		$dgp=$this->api->execute_method('get_dynamic_global_properties');
		if(!$dgp['head_block_number']){
			return false;
		}
		$tapos_block_num=$dgp['head_block_number'] - 5;
		$ref_block_num=($tapos_block_num) & 0xFFFF;
		$ref_block_num_bin=bin2hex(pack('S',$ref_block_num));

		$tapos_block=$tapos_block_num+1;
		$tapos_block_info=false;
		$api_count=0;
		while(!$tapos_block_info){
			$tapos_block_info=$this->api->execute_method('get_block_header',array($tapos_block));
			if(!$tapos_block_info){
				$api_count++;
				if($api_count>5){
					return false;
				}
			}
		}
		if(!isset($tapos_block_info['previous'])){
			return false;
		}
		$ref_block_prefix_bin=bin2hex(strrev(substr(hex2bin($tapos_block_info['previous']),4,4)));
		$ref_block_prefix=hexdec($ref_block_prefix_bin);
		$ref_block_prefix_bin_nice=bin2hex(strrev(hex2bin($ref_block_prefix_bin)));

		$tx_extension='00';

		$nonce=0;
		$error=true;
		while($error){
			$this->signatures=[];

			$expiration_time=time()+600+$nonce;//+10min+nonce
			$expiration_str=date('Y-m-d\TH:i:s',$expiration_time);

			$tx_tapos=$ref_block_num_bin.$ref_block_prefix_bin_nice;
			$raw_data=$this->chain_id.$tx_tapos.$this->encode_unixtime($expiration_time).$this->encode_uint8($operations_count).$operations_data.$tx_extension;

			$data=hex2bin($raw_data);

			$error=false;

			foreach($this->private_keys as $private_key){
				$signature=$private_key->sign($data);
				if(false!==$signature){
					$this->signatures[]=$signature;
				}
				else{
					$error=true;
				}
			}
			if($error){
				$nonce++;
			}
		}
		$tx_data=$tx_tapos.$this->encode_unixtime($expiration_time).$this->encode_uint8($operations_count).$operations_data.$tx_extension;
		$tx_id=substr(hash('sha256',hex2bin($tx_data)),0,40);

		$json='{"ref_block_num":'.$ref_block_num.',"ref_block_prefix":'.$ref_block_prefix.',"expiration":"'.$expiration_str.'","operations":[';
		$json.=$operations_json;
		$signatures_json_arr=[];
		foreach($this->signatures as $signature){
			$signatures_json_arr[]='"'.$signature.'"';
		}
		$json.='],"extensions":[],"signatures":['.implode(',',$signatures_json_arr).']}';

		return ['id'=>$tx_id,'data'=>$raw_data,'json'=>$json,'signatures'=>$this->signatures];
	}
	function add_signature($json,$data,$private_key=''){
		if(!$private_key){
			if($this->private_keys_count){
				$private_key=$this->private_keys[$this->private_keys_count - 1];
			}
			else{
				return false;
			}
		}
		else{
			$private_key=new Key($private_key);
		}
		$data_bin=hex2bin($data);
		$signature=$private_key->sign($data_bin);
		if($signature){
			$first=(false!==strrpos($json,',"signatures":[]'));
			$signatures_pos=strrpos($json,',"signatures":[');
			$signatures_pos=$signatures_pos+strlen(',"signatures":[');
			$left=substr($json,0,$signatures_pos);
			$right=substr($json,$signatures_pos);
			if($first){
				$json=$left.'"'.$signature.'"'.$right;
			}
			else{
				$json=$left.'"'.$signature.'",'.$right;
			}
			$tx_id=substr(hash('sha256',hex2bin(substr($data,64))),0,40);//cutoff chain_id
			return ['id'=>$tx_id,'data'=>$data,'json'=>$json,'signature'=>$signature];
		}
		else{
			return false;
		}
	}
	function build_award($initiator,$receiver,$energy,$custom_sequence=0,$memo='',$beneficiaries=[]){
		$json='["award",{';
		$json.='"initiator":"'.$initiator.'"';
		$json.=',"receiver":"'.$receiver.'"';
		$json.=',"energy":'.$energy;
		$json.=',"custom_sequence":'.$custom_sequence;
		$json.=',"memo":"'.$memo.'"';
		$json.=',"beneficiaries":[';
		$beneficiaries_arr=[];
		foreach($beneficiaries as $beneficiary_arr){
			$beneficiaries_arr[]='{"account":"'.$beneficiary_arr[0].'","weight":'.$beneficiary_arr[1].'}';
		}
		$json.=implode(',',$beneficiaries_arr);
		$json.=']';
		$json.='}]';
		$raw='2f';//operation number is 47
		$raw.=$this->encode_string($initiator);
		$raw.=$this->encode_string($receiver);
		$raw.=$this->encode_int($energy,2);
		$raw.=$this->encode_int($custom_sequence,8);
		$raw.=$this->encode_string($memo);
		$raw.=$this->encode_array($beneficiaries,[['string','int16']]);
		return [$json,$raw];
	}
	function build_create_invite($creator,$balance,$invite_key){
		$json='["create_invite",{';
		$json.='"creator":"'.$creator.'"';
		$json.=',"balance":"'.$balance.'"';
		$json.=',"invite_key":"'.$invite_key.'"';
		$json.='}]';
		$raw='2b';//operation number is 43
		$raw.=$this->encode_string($creator);
		$raw.=$this->encode_asset($balance);
		$raw.=$this->encode_public_key($invite_key);
		return [$json,$raw];
	}
	function build_transfer($from,$to,$amount,$memo){
		$json='["transfer",{';
		$json.='"from":"'.$from.'"';
		$json.=',"to":"'.$to.'"';
		$json.=',"amount":"'.$amount.'"';
		$json.=',"memo":"'.$memo.'"';
		$json.='}]';
		$raw='02';//operation number is 2
		$raw.=$this->encode_string($from);
		$raw.=$this->encode_string($to);
		$raw.=$this->encode_asset($amount);
		$raw.=$this->encode_string($memo);
		return [$json,$raw];
	}
	function build_transfer_to_vesting($from,$to,$amount){
		$json='["transfer_to_vesting",{';
		$json.='"from":"'.$from.'"';
		$json.=',"to":"'.$to.'"';
		$json.=',"amount":"'.$amount.'"';
		$json.='}]';
		$raw='03';//operation number is 3
		$raw.=$this->encode_string($from);
		$raw.=$this->encode_string($to);
		$raw.=$this->encode_asset($amount);
		return [$json,$raw];
	}
	function build_withdraw_vesting($account,$vesting_shares){
		$json='["withdraw_vesting",{';
		$json.='"account":"'.$account.'"';
		$json.=',"vesting_shares":"'.$vesting_shares.'"';
		$json.='}]';
		$raw='04';//operation number is 4
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_asset($vesting_shares);
		return [$json,$raw];
	}
	function start_queue(){
		$this->queue=true;
	}
	function end_queue(){
		$this->queue=false;
		$operations_json_arr=[];
		$operations_data_arr=[];
		$operations_count=0;
		foreach($this->queue_arr as $queue_item){
			$operations_json_arr[]=$queue_item[0];
			$operations_data_arr[]=$queue_item[1];
			$operations_count++;
		}
		$operations_json=implode(',',$operations_json_arr);
		$operations_data=implode('',$operations_data_arr);
		$this->queue_arr=[];
		$tx=$this->build($operations_json,$operations_data,$operations_count);
		return $tx;
	}
	function encode_asset($input){
		$input_arr=explode(' ',$input);
		$asset_str=$input_arr[1];
		$number_arr=explode('.',$input_arr[0]);
		$precision=strlen($number_arr[1]);

		$precision_hex=bin2hex(pack('C',$precision));

		$number=(int)implode('',$number_arr);
		$number_hex=bin2hex(pack('Q',$number));

		$asset_hex=bin2hex(pack('H*',bin2hex($asset_str)));
		$asset_hex=str_pad($asset_hex,14,'0');

		$result=$number_hex.$precision_hex.$asset_hex;
		return $result;
	}
	function encode_public_key($input){
		$public_key=new Key($input);
		return $public_key->hex;
	}
	function encode_string($input){
		$length=bin2hex(pack('C',strlen($input)));
		return $length.bin2hex(pack('H*',bin2hex($input)));
	}
	function encode_timestamp($input){
		$unixtime=DateTime::createFromFormat('Y-m-d\TH:i:s',$input)->format('U');
		return $this->encode_uint32($unixtime);
	}
	function encode_unixtime($input){
		return $this->encode_uint32($input);
	}
	function encode_bool($input){
		return bin2hex(pack('C',($input?1:0)));
	}
	function encode_int16($input){
		return bin2hex(pack('s',$input));
	}
	function encode_uint8($input){
		return bin2hex(pack('C',$input));
	}
	function encode_uint16($input){
		return bin2hex(pack('S',$input));
	}
	function encode_uint32($input){
		return bin2hex(pack('L',$input));
	}
	function encode_uint64($input){
		return bin2hex(pack('Q',$input));
	}
	function encode_int($input,$bytes){
		$result='';
		if($input){
			$result=dechex($input);
			if(strlen($result)%2!=0){
				$result='0'.$result;
			}
			$result=bin2hex(strrev(hex2bin($result)));
		}
		$result=str_pad($result,$bytes*2,'0');
		return $result;
	}
	function encode_array($array,$type,$structed=false){
		$result='';
		if(!$structed){
			$result.=bin2hex(pack('C',count($array)));
		}
		foreach($array as $num=>$item){
			$type_num=$num;
			if(!isset($type[$type_num])){//need set first item for array template (example: benefeciaries)
				$type_num=0;
			}
			if(!is_array($type)){
				$encode_type='encode_'.$type;
			}
			else{
				$encode_type='encode_'.(is_array($type[$type_num])?'array':$type[$type_num]);
			}
			if(is_array($type[$type_num])){
				$result.=$this->$encode_type($item,$type[$type_num],true);//nested structure
			}
			else{
				$result.=$this->$encode_type($item);
			}
		}
		return $result;
	}
}