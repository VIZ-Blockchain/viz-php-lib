<?php
namespace VIZ;

use DateTime;
use DateTimeZone;
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
	function execute($transaction_json,$synchronous=false){
		if($synchronous){
			return $this->api->execute_method('broadcast_transaction_synchronous',$transaction_json,false);
		}
		else{
			return $this->api->execute_method('broadcast_transaction',$transaction_json,false);
		}
	}
	function build($operations_json,$operations_data,$operations_count){
		$dgp=$this->api->execute_method('get_dynamic_global_properties');
		if(!$this->api->return_only_result){
			$dgp=$dgp['result'];
		}
		if(!$dgp['head_block_number']){
			return false;
		}
		$need_tapos_block=true;
		$ref_block_num='';
		$ref_block_num_bin='';
		$ref_block_prefix='';
		$ref_block_prefix_bin_nice='';
		if(isset($dgp['last_irreversible_block_ref_num'])){
			if(0!=$dgp['last_irreversible_block_ref_num']){
				$need_tapos_block=false;
				$ref_block_num=$dgp['last_irreversible_block_ref_num'];
				$ref_block_num_bin=bin2hex(pack('S',$ref_block_num));
				$ref_block_prefix=$dgp['last_irreversible_block_ref_prefix'];
				$ref_block_prefix_bin_nice=bin2hex(strrev(hex2bin(str_pad(dechex($ref_block_prefix),8,'0',STR_PAD_LEFT))));
			}
		}

		if($need_tapos_block){
			//old way: tapos block is 5 blocks behind the head block
			//$tapos_block_num=$dgp['head_block_number'] - 5;
			//best way: stick to the irreversible block
			$tapos_block_num=$dgp['last_irreversible_block_num'];
			$ref_block_num=($tapos_block_num) & 0xFFFF;
			$ref_block_num_bin=bin2hex(pack('S',$ref_block_num));

			$tapos_block=$tapos_block_num+1;
			$tapos_block_info=false;
			$api_count=0;
			while(!$tapos_block_info){
				$tapos_block_info=$this->api->execute_method('get_block_header',array($tapos_block));
				if(!$this->api->return_only_result){
					$tapos_block_info=$tapos_block_info['result'];
				}
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
		}

		$tx_extension='00';

		$nonce=0;
		$error=true;
		while($error){
			$this->signatures=[];

			$expiration_time=time()+600+$nonce;//+10min+nonce
			$expiration_str=date('Y-m-d\TH:i:s',$expiration_time-(new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime()));

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
	function build_account_create($fee,$delegation,$creator,$new_account_name,$master,$active,$regular,$memo_key='VIZ1111111111111111111111111111111114T1Anm',$json_metadata='',$referrer=''){
		$json='["account_create",{';
		$json.='"fee":"'.$fee.'"';
		$json.=',"delegation":"'.$delegation.'"';
		$json.=',"creator":"'.$creator.'"';
		$json.=',"new_account_name":"'.$new_account_name.'"';
		$json.=',"master":{';
		$master_str_arr=[];
		$master_arr=[];
		if(is_string($master)){
			$master_public_key=$master;
			$master=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$master_public_key,1]],
			];
		}
		if(is_array($master)){
			if(!isset($master['weight_threshold'])){
				$master['weight_threshold']=1;
			}
			$master_arr['weight_threshold']=$master['weight_threshold'];
			$master_arr['account_auths']=[];
			foreach($master['account_auths'] as $accounts_auth_arr){
				$master_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$master_arr['key_auths']=[];
			foreach($master['key_auths'] as $key_auths_arr){
				$master_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($master_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$master_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$master_str_arr);
		}
		$json.='}';
		$json.=',"active":{';
		$active_str_arr=[];
		$active_arr=[];
		if(is_string($active)){
			$active_public_key=$active;
			$active=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$active_public_key,1]],
			];
		}
		if(!isset($active['weight_threshold'])){
			$active['weight_threshold']=1;
		}
		if(is_array($active)){
			$active_arr['weight_threshold']=$active['weight_threshold'];
			$active_arr['account_auths']=[];
			foreach($active['account_auths'] as $accounts_auth_arr){
				$active_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$active_arr['key_auths']=[];
			foreach($active['key_auths'] as $key_auths_arr){
				$active_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($active_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$active_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$active_str_arr);
		}
		$json.='}';
		$json.=',"regular":{';
		$regular_str_arr=[];
		$regular_arr=[];
		if(is_string($regular)){
			$regular_public_key=$regular;
			$regular=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$regular_public_key,1]],
			];
		}
		if(!isset($regular['weight_threshold'])){
			$regular['weight_threshold']=1;
		}
		if(is_array($regular)){
			$regular_arr['weight_threshold']=$regular['weight_threshold'];
			$regular_arr['account_auths']=[];
			foreach($regular['account_auths'] as $accounts_auth_arr){
				$regular_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$regular_arr['key_auths']=[];
			foreach($regular['key_auths'] as $key_auths_arr){
				$regular_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($regular_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$regular_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$regular_str_arr);
		}
		$json.='}';
		$json.=',"memo_key":"'.$memo_key.'"';
		$json.=',"json_metadata":"'.$json_metadata.'"';
		$json.=',"referrer":"'.$referrer.'"';
		$json.='}]';
		$raw='14';//operation number is 20
		$raw.=$this->encode_asset($fee);
		$raw.=$this->encode_asset($delegation);
		$raw.=$this->encode_string($creator);
		$raw.=$this->encode_string($new_account_name);

		$raw.=$this->encode_uint32($master['weight_threshold']);
		$raw.=$this->encode_array($master['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($master['key_auths'],[['public_key','uint16']]);

		$raw.=$this->encode_uint32($active['weight_threshold']);
		$raw.=$this->encode_array($active['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($active['key_auths'],[['public_key','uint16']]);

		$raw.=$this->encode_uint32($regular['weight_threshold']);
		$raw.=$this->encode_array($regular['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($regular['key_auths'],[['public_key','uint16']]);

		$raw.=$this->encode_public_key($memo_key);
		$raw.=$this->encode_string($json_metadata);
		$raw.=$this->encode_string($referrer);
		$raw.='00';//op extension
		return [$json,$raw];
	}
	function build_account_update($account,$master,$active,$regular,$memo_key='VIZ1111111111111111111111111111111114T1Anm',$json_metadata=''){
		$json='["account_update",{';
		$json.='"account":"'.$account.'"';
		$json.=',"master":{';
		$master_str_arr=[];
		$master_arr=[];
		if(is_string($master)){
			$master_public_key=$master;
			$master=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$master_public_key,1]],
			];
		}
		if(is_array($master)){
			if(!isset($master['weight_threshold'])){
				$master['weight_threshold']=1;
			}
			$master_arr['weight_threshold']=$master['weight_threshold'];
			$master_arr['account_auths']=[];
			foreach($master['account_auths'] as $accounts_auth_arr){
				$master_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$master_arr['key_auths']=[];
			foreach($master['key_auths'] as $key_auths_arr){
				$master_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($master_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$master_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$master_str_arr);
		}
		$json.='}';
		$json.=',"active":{';
		$active_str_arr=[];
		$active_arr=[];
		if(is_string($active)){
			$active_public_key=$active;
			$active=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$active_public_key,1]],
			];
		}
		if(!isset($active['weight_threshold'])){
			$active['weight_threshold']=1;
		}
		if(is_array($active)){
			$active_arr['weight_threshold']=$active['weight_threshold'];
			$active_arr['account_auths']=[];
			foreach($active['account_auths'] as $accounts_auth_arr){
				$active_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$active_arr['key_auths']=[];
			foreach($active['key_auths'] as $key_auths_arr){
				$active_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($active_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$active_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$active_str_arr);
		}
		$json.='}';
		$json.=',"regular":{';
		$regular_str_arr=[];
		$regular_arr=[];
		if(is_string($regular)){
			$regular_public_key=$regular;
			$regular=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$regular_public_key,1]],
			];
		}
		if(!isset($regular['weight_threshold'])){
			$regular['weight_threshold']=1;
		}
		if(is_array($regular)){
			$regular_arr['weight_threshold']=$regular['weight_threshold'];
			$regular_arr['account_auths']=[];
			foreach($regular['account_auths'] as $accounts_auth_arr){
				$regular_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$regular_arr['key_auths']=[];
			foreach($regular['key_auths'] as $key_auths_arr){
				$regular_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($regular_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$regular_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$regular_str_arr);
		}
		$json.='}';
		$json.=',"memo_key":"'.$memo_key.'"';
		$json.=',"json_metadata":"'.$json_metadata.'"';
		$json.='}]';

		$raw='05';//operation number is 5
		$raw.=$this->encode_string($account);

		$raw.=$this->encode_uint32($master['weight_threshold']);
		$raw.=$this->encode_array($master['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($master['key_auths'],[['public_key','uint16']]);

		$raw.=$this->encode_uint32($active['weight_threshold']);
		$raw.=$this->encode_array($active['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($active['key_auths'],[['public_key','uint16']]);

		$raw.=$this->encode_uint32($regular['weight_threshold']);
		$raw.=$this->encode_array($regular['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($regular['key_auths'],[['public_key','uint16']]);

		$raw.=$this->encode_public_key($memo_key);
		$raw.=$this->encode_string($json_metadata);
		return [$json,$raw];
	}
	function build_request_account_recovery($recovery_account,$account_to_recover,$new_master){
		$json='["request_account_recovery",{';
		$json.='"recovery_account":"'.$recovery_account.'"';
		$json.=',"account_to_recover":"'.$account_to_recover.'"';
		$json.=',"new_master_authority":{';
		$master_str_arr=[];
		$master_arr=[];
		if(is_string($new_master)){
			$master_public_key=$new_master;
			$new_master=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$master_public_key,1]],
			];
		}
		if(is_array($new_master)){
			if(!isset($new_master['weight_threshold'])){
				$new_master['weight_threshold']=1;
			}
			$master_arr['weight_threshold']=$new_master['weight_threshold'];
			$master_arr['account_auths']=[];
			foreach($new_master['account_auths'] as $accounts_auth_arr){
				$master_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$master_arr['key_auths']=[];
			foreach($new_master['key_auths'] as $key_auths_arr){
				$master_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($master_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$master_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$master_str_arr);
		}
		$json.='}';
		$json.='}]';

		$raw='0C';//operation number is 12
		$raw.=$this->encode_string($recovery_account);
		$raw.=$this->encode_string($account_to_recover);

		$raw.=$this->encode_uint32($new_master['weight_threshold']);
		$raw.=$this->encode_array($new_master['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($new_master['key_auths'],[['public_key','uint16']]);

		$raw.='00';//op extension
		return [$json,$raw];
	}
	function build_recover_account($account_to_recover,$new_master,$recent_master){
		$json='["recover_account",{';
		$json.='"account_to_recover":"'.$account_to_recover.'"';
		$json.=',"new_master":{';
		$master_str_arr=[];
		$master_arr=[];
		if(is_string($new_master)){
			$master_public_key=$new_master;
			$new_master=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$master_public_key,1]],
			];
		}
		if(is_array($new_master)){
			if(!isset($new_master['weight_threshold'])){
				$new_master['weight_threshold']=1;
			}
			$master_arr['weight_threshold']=$new_master['weight_threshold'];
			$master_arr['account_auths']=[];
			foreach($new_master['account_auths'] as $accounts_auth_arr){
				$master_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$master_arr['key_auths']=[];
			foreach($new_master['key_auths'] as $key_auths_arr){
				$master_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($master_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$master_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$master_str_arr);
		}
		$json.='}';
		$json.=',"recent_master_authority":{';
		$master_str_arr=[];
		$master_arr=[];
		if(is_string($recent_master)){
			$master_public_key=$recent_master;
			$recent_master=[
				'weight_threshold'=>1,
				'account_auths'=>[],
				'key_auths'=>[[$master_public_key,1]],
			];
		}
		if(is_array($recent_master)){
			if(!isset($recent_master['weight_threshold'])){
				$recent_master['weight_threshold']=1;
			}
			$master_arr['weight_threshold']=$recent_master['weight_threshold'];
			$master_arr['account_auths']=[];
			foreach($recent_master['account_auths'] as $accounts_auth_arr){
				$master_arr['account_auths'][]='["'.$accounts_auth_arr[0].'",'.$accounts_auth_arr[1].']';
			}
			$master_arr['key_auths']=[];
			foreach($recent_master['key_auths'] as $key_auths_arr){
				$master_arr['key_auths'][]='["'.$key_auths_arr[0].'",'.$key_auths_arr[1].']';
			}
			foreach($master_arr as $k=>$v){
				$v_str='';
				if(is_array($v)){
					if(count($v)){
						$v_str='['.implode(',',$v).']';
					}
					else{
						$v_str='[]';
					}
				}
				else{
					$v_str=$v;
				}
				$master_str_arr[]='"'.$k.'":'.$v_str;
			}
			$json.=implode(',',$master_str_arr);
		}
		$json.='}';
		$json.='}]';

		$raw='0D';//operation number is 13
		$raw.=$this->encode_string($account_to_recover);

		$raw.=$this->encode_uint32($new_master['weight_threshold']);
		$raw.=$this->encode_array($new_master['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($new_master['key_auths'],[['public_key','uint16']]);

		$raw.=$this->encode_uint32($recent_master['weight_threshold']);
		$raw.=$this->encode_array($recent_master['account_auths'],[['string','uint16']]);
		$raw.=$this->encode_array($recent_master['key_auths'],[['public_key','uint16']]);

		$raw.='00';//op extension
		return [$json,$raw];
	}
	function build_account_metadata($account,$json_metadata=''){
		$json='["account_metadata",{';
		$json.='"account":"'.$account.'"';
		$json.=',"json_metadata":"'.$json_metadata.'"';
		$json.='}]';

		$raw='15';//operation number is 21
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($json_metadata);
		return [$json,$raw];
	}
	function build_account_witness_vote($account,$witness,$approve=true){
		$json='["account_witness_vote",{';
		$json.='"account":"'.$account.'"';
		$json.=',"witness":"'.$witness.'"';
		$json.=',"approve":'.($approve?'true':'false').'';
		$json.='}]';

		$raw='07';//operation number is 7
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($witness);
		$raw.=$this->encode_bool($approve);
		return [$json,$raw];
	}
	function build_change_recovery_account($account_to_recover,$new_recovery_account){
		$json='["change_recovery_account",{';
		$json.='"account_to_recover":"'.$account_to_recover.'"';
		$json.=',"new_recovery_account":"'.$new_recovery_account.'"';
		$json.='}]';

		$raw='0E';//operation number is 14
		$raw.=$this->encode_string($account_to_recover);
		$raw.=$this->encode_string($new_recovery_account);
		return [$json,$raw];
	}
	function build_account_witness_proxy($account,$proxy){
		$json='["account_witness_proxy",{';
		$json.='"account":"'.$account.'"';
		$json.=',"proxy":"'.$proxy.'"';
		$json.='}]';

		$raw='08';//operation number is 8
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($proxy);
		return [$json,$raw];
	}
	function build_set_withdraw_vesting_route($from_account,$to_account,$percent,$auto_vest=true){
		$json='["set_withdraw_vesting_route",{';
		$json.='"from_account":"'.$from_account.'"';
		$json.=',"to_account":"'.$to_account.'"';
		$json.=',"percent":'.$percent.'';
		$json.=',"auto_vest":'.($auto_vest?'true':'false').'';
		$json.='}]';

		$raw='0B';//operation number is 11
		$raw.=$this->encode_string($from_account);
		$raw.=$this->encode_string($to_account);
		$raw.=$this->encode_int($percent,2);
		$raw.=$this->encode_bool($auto_vest);
		return [$json,$raw];
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
	function build_fixed_award($initiator,$receiver,$reward_amount,$max_energy,$custom_sequence=0,$memo='',$beneficiaries=[]){
		$json='["fixed_award",{';
		$json.='"initiator":"'.$initiator.'"';
		$json.=',"receiver":"'.$receiver.'"';
		$json.=',"reward_amount":"'.$reward_amount.'"';
		$json.=',"max_energy":'.$max_energy;
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
		$raw='3c';//operation number is 60
		$raw.=$this->encode_string($initiator);
		$raw.=$this->encode_string($receiver);
		$raw.=$this->encode_asset($reward_amount);
		$raw.=$this->encode_int($max_energy,2);
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
	function build_escrow_transfer($from,$to,$token_amount,$escrow_id,$agent,$fee,$json_metadata,$ratification_deadline,$escrow_expiration){
		$json='["escrow_transfer",{';
		$json.='"from":"'.$from.'"';
		$json.=',"to":"'.$to.'"';
		$json.=',"token_amount":"'.$token_amount.'"';
		$json.=',"escrow_id":'.$escrow_id.'';
		$json.=',"agent":"'.$agent.'"';
		$json.=',"fee":"'.$fee.'"';
		$json.=',"json_metadata":"'.$json_metadata.'"';
		$json.=',"ratification_deadline":"'.$ratification_deadline.'"';
		$json.=',"escrow_expiration":"'.$escrow_expiration.'"';
		$json.='}]';
		$raw='0F';//operation number is 15
		$raw.=$this->encode_string($from);
		$raw.=$this->encode_string($to);
		$raw.=$this->encode_asset($token_amount);
		$raw.=$this->encode_uint32($escrow_id);
		$raw.=$this->encode_string($agent);
		$raw.=$this->encode_asset($fee);
		$raw.=$this->encode_string($json_metadata);
		$raw.=$this->encode_timestamp($ratification_deadline);
		$raw.=$this->encode_timestamp($escrow_expiration);
		return [$json,$raw];
	}
	function build_escrow_dispute($from,$to,$agent,$who,$escrow_id){
		$json='["escrow_dispute",{';
		$json.='"from":"'.$from.'"';
		$json.=',"to":"'.$to.'"';
		$json.=',"agent":"'.$agent.'"';
		$json.=',"who":"'.$who.'"';
		$json.=',"escrow_id":'.$escrow_id.'';
		$json.='}]';
		$raw='10';//operation number is 16
		$raw.=$this->encode_string($from);
		$raw.=$this->encode_string($to);
		$raw.=$this->encode_string($agent);
		$raw.=$this->encode_string($who);
		$raw.=$this->encode_uint32($escrow_id);
		return [$json,$raw];
	}
	function build_escrow_release($from,$to,$agent,$who,$receiver,$escrow_id,$token_amount){
		$json='["escrow_release",{';
		$json.='"from":"'.$from.'"';
		$json.=',"to":"'.$to.'"';
		$json.=',"agent":"'.$agent.'"';
		$json.=',"who":"'.$who.'"';
		$json.=',"receiver":"'.$receiver.'"';
		$json.=',"escrow_id":'.$escrow_id.'';
		$json.=',"token_amount":"'.$token_amount.'"';
		$json.='}]';
		$raw='11';//operation number is 17
		$raw.=$this->encode_string($from);
		$raw.=$this->encode_string($to);
		$raw.=$this->encode_string($agent);
		$raw.=$this->encode_string($who);
		$raw.=$this->encode_string($receiver);
		$raw.=$this->encode_uint32($escrow_id);
		$raw.=$this->encode_asset($token_amount);
		return [$json,$raw];
	}
	function build_escrow_approve($from,$to,$agent,$who,$escrow_id,$approve){
		$json='["escrow_approve",{';
		$json.='"from":"'.$from.'"';
		$json.=',"to":"'.$to.'"';
		$json.=',"agent":"'.$agent.'"';
		$json.=',"who":"'.$who.'"';
		$json.=',"escrow_id":'.$escrow_id.'';
		$json.=',"approve":'.($approve?'true':'false').'';
		$json.='}]';
		$raw='12';//operation number is 18
		$raw.=$this->encode_string($from);
		$raw.=$this->encode_string($to);
		$raw.=$this->encode_string($agent);
		$raw.=$this->encode_string($who);
		$raw.=$this->encode_uint32($escrow_id);
		$raw.=$this->encode_bool($approve);
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
	function build_delegate_vesting_shares($delegator,$delegatee,$vesting_shares){
		$json='["delegate_vesting_shares",{';
		$json.='"delegator":"'.$delegator.'"';
		$json.=',"delegatee":"'.$delegatee.'"';
		$json.=',"vesting_shares":"'.$vesting_shares.'"';
		$json.='}]';
		$raw='13';//operation number is 19
		$raw.=$this->encode_string($delegator);
		$raw.=$this->encode_string($delegatee);
		$raw.=$this->encode_asset($vesting_shares);
		return [$json,$raw];
	}
	function build_committee_worker_create_request($creator,$url,$worker,$required_amount_min,$required_amount_max,$duration){
		$json='["committee_worker_create_request",{';
		$json.='"creator":"'.$creator.'"';
		$json.=',"url":"'.$url.'"';
		$json.=',"worker":"'.$worker.'"';
		$json.=',"required_amount_min":"'.$required_amount_min.'"';
		$json.=',"required_amount_max":"'.$required_amount_max.'"';
		$json.=',"duration":'.$duration.'';
		$json.='}]';
		$raw='23';//operation number is 35
		$raw.=$this->encode_string($creator);
		$raw.=$this->encode_string($url);
		$raw.=$this->encode_string($worker);
		$raw.=$this->encode_asset($required_amount_min);
		$raw.=$this->encode_asset($required_amount_max);
		$raw.=$this->encode_uint32($duration);
		return [$json,$raw];
	}
	function build_committee_worker_cancel_request($creator,$request_id){
		$json='["committee_worker_cancel_request",{';
		$json.='"creator":"'.$creator.'"';
		$json.=',"request_id":'.$request_id.'';
		$json.='}]';
		$raw='24';//operation number is 36
		$raw.=$this->encode_string($creator);
		$raw.=$this->encode_uint32($request_id);
		return [$json,$raw];
	}
	function build_committee_vote_request($creator,$request_id,$vote_percent){
		$json='["committee_vote_request",{';
		$json.='"creator":"'.$creator.'"';
		$json.=',"request_id":'.$request_id.'';
		$json.=',"vote_percent":'.$vote_percent.'';
		$json.='}]';
		$raw='25';//operation number is 37
		$raw.=$this->encode_string($creator);
		$raw.=$this->encode_uint32($request_id);
		$raw.=$this->encode_int16($vote_percent);
		return [$json,$raw];
	}
	function build_claim_invite_balance($initiator,$receiver,$invite_secret){
		$json='["claim_invite_balance",{';
		$json.='"initiator":"'.$initiator.'"';
		$json.=',"receiver":"'.$receiver.'"';
		$json.=',"invite_secret":"'.$invite_secret.'"';
		$json.='}]';
		$raw='2C';//operation number is 44
		$raw.=$this->encode_string($initiator);
		$raw.=$this->encode_string($receiver);
		$raw.=$this->encode_string($invite_secret);
		return [$json,$raw];
	}
	function build_invite_registration($initiator,$new_account_name,$invite_secret,$new_account_key){
		$json='["invite_registration",{';
		$json.='"initiator":"'.$initiator.'"';
		$json.=',"new_account_name":"'.$new_account_name.'"';
		$json.=',"invite_secret":"'.$invite_secret.'"';
		$json.=',"new_account_key":"'.$new_account_key.'"';
		$json.='}]';
		$raw='2D';//operation number is 45
		$raw.=$this->encode_string($initiator);
		$raw.=$this->encode_string($new_account_name);
		$raw.=$this->encode_string($invite_secret);
		$raw.=$this->encode_public_key($new_account_key);
		return [$json,$raw];
	}
	function build_use_invite_balance($initiator,$receiver,$invite_secret){
		$json='["use_invite_balance",{';
		$json.='"initiator":"'.$initiator.'"';
		$json.=',"receiver":"'.$receiver.'"';
		$json.=',"invite_secret":"'.$invite_secret.'"';
		$json.='}]';
		$raw='3A';//operation number is 58
		$raw.=$this->encode_string($initiator);
		$raw.=$this->encode_string($receiver);
		$raw.=$this->encode_string($invite_secret);
		return [$json,$raw];
	}
	function build_chain_properties_update($owner,$props){
		$default_props=[
			'account_creation_fee'=>'1.000 VIZ',
			'maximum_block_size'=>65536,
			'create_account_delegation_ratio'=>10,
			'create_account_delegation_time'=>2592000,
			'min_delegation'=>'1.000 VIZ',
			'min_curation_percent'=>0,
			'max_curation_percent'=>10000,
			'bandwidth_reserve_percent'=>0,
			'bandwidth_reserve_below'=>'0.000000 SHARES',
			'flag_energy_additional_cost'=>0,
			'vote_accounting_min_rshares'=>100000,
			'committee_request_approve_min_percent'=>1000,
		];
		$props_types=[
			'account_creation_fee'=>'asset',
			'maximum_block_size'=>'uint32',
			'create_account_delegation_ratio'=>'uint32',
			'create_account_delegation_time'=>'uint32',
			'min_delegation'=>'asset',
			'min_curation_percent'=>'int16',
			'max_curation_percent'=>'int16',
			'bandwidth_reserve_percent'=>'int16',
			'bandwidth_reserve_below'=>'asset',
			'flag_energy_additional_cost'=>'int16',
			'vote_accounting_min_rshares'=>'uint32',
			'committee_request_approve_min_percent'=>'int16',
		];
		$json='["chain_properties_update",{';
		$json.='"owner":"'.$owner.'"';
		$new_props=$default_props;
		foreach($props as $prop=>$value){
			$new_props[$prop]=$value;
		}
		$json.=',"props":'.json_encode($new_props).'';
		$json.='}]';
		$raw='19';//operation number is 25
		$raw.=$this->encode_string($owner);
		$raw.=$this->encode_array($new_props,$props_types,true);
		return [$json,$raw];
	}
	function build_versioned_chain_properties_update($owner,$props,$version=4){
		// $version selects the chain_properties variant: 4 = HF13 base, 5 = HF14 (Onix)
		// with the Prediction Market governance parameters appended (see below).
		$default_props=[
			'account_creation_fee'=>'1.000 VIZ',
			'maximum_block_size'=>65536,
			'create_account_delegation_ratio'=>10,
			'create_account_delegation_time'=>2592000,
			'min_delegation'=>'1.000 VIZ',
			'min_curation_percent'=>0,
			'max_curation_percent'=>10000,
			'bandwidth_reserve_percent'=>0,
			'bandwidth_reserve_below'=>'0.000000 SHARES',
			'flag_energy_additional_cost'=>0,
			'vote_accounting_min_rshares'=>100000,
			'committee_request_approve_min_percent'=>1000,
			'inflation_validator_percent'=>2000,
			'inflation_ratio_committee_vs_reward_fund'=>5000,
			'inflation_recalc_period'=>806400,
			'data_operations_cost_additional_bandwidth'=>10000,
			'validator_miss_penalty_percent'=>100,
			'validator_miss_penalty_duration'=>86400,
			'create_invite_min_balance'=>'10.000 VIZ',
			'committee_create_request_fee'=>'100.000 VIZ',
			'create_paid_subscription_fee'=>'100.000 VIZ',
			'account_on_sale_fee'=>'10.000 VIZ',
			'subaccount_on_sale_fee'=>'100.000 VIZ',
			'validator_declaration_fee'=>'10.000 VIZ',
			'withdraw_intervals'=>28,
			'distribution_epoch_length'=>28800,
		];
		$props_types=[
			'account_creation_fee'=>'asset',
			'maximum_block_size'=>'uint32',
			'create_account_delegation_ratio'=>'uint32',
			'create_account_delegation_time'=>'uint32',
			'min_delegation'=>'asset',
			'min_curation_percent'=>'int16',
			'max_curation_percent'=>'int16',
			'bandwidth_reserve_percent'=>'int16',
			'bandwidth_reserve_below'=>'asset',
			'flag_energy_additional_cost'=>'int16',
			'vote_accounting_min_rshares'=>'uint32',
			'committee_request_approve_min_percent'=>'int16',
			'inflation_validator_percent'=>'int16',
			'inflation_ratio_committee_vs_reward_fund'=>'int16',
			'inflation_recalc_period'=>'uint32',
			'data_operations_cost_additional_bandwidth'=>'uint32',
			'validator_miss_penalty_percent'=>'int16',
			'validator_miss_penalty_duration'=>'uint32',
			'create_invite_min_balance'=>'asset',
			'committee_create_request_fee'=>'asset',
			'create_paid_subscription_fee'=>'asset',
			'account_on_sale_fee'=>'asset',
			'subaccount_on_sale_fee'=>'asset',
			'validator_declaration_fee'=>'asset',
			'withdraw_intervals'=>'uint16',
			'distribution_epoch_length'=>'uint32',
		];
		if($version>=5){
			// HF14 (Onix) Prediction Market governance parameters (chain_properties_pm,
			// variant index 5). Field order and types mirror the C++
			// FC_REFLECT_DERIVED(graphene::protocol::chain_properties_pm) exactly — the
			// binary layout is consensus-critical, do not reorder.
			$default_props=array_merge($default_props,[
				'pm_oracle_registration_fee'=>'10.000 VIZ',
				'pm_min_oracle_insurance'=>'5000.000 VIZ',
				'pm_market_creation_fee'=>'5.000 VIZ',
				'pm_min_liquidity'=>'100.000 VIZ',
				'pm_max_outcomes'=>10,
				'pm_max_market_duration'=>31536000,
				'pm_max_oracle_fee_percent'=>500,
				'pm_oracle_accept_window_sec'=>3600,
				'pm_listing_min_coverage_percent'=>250,
				'pm_betting_min_coverage_percent'=>150,
				'pm_default_time_penalty_percent'=>50,
				'pm_max_time_penalty'=>1000000,
				'pm_dispute_fee'=>'1000.000 VIZ',
				'pm_dispute_grace_sec'=>43200,
				'pm_oracle_dispute_response_sec'=>43200,
				'pm_dispute_auto_close_sec'=>1209600,
				'pm_dispute_vote_period_sec'=>259200,
				'pm_dispute_approve_min_percent'=>1000,
				'pm_oracle_penalty_percent'=>500,
				'pm_no_contest_penalty_percent'=>5000,
				'pm_dispute_reward_multiplier'=>30000,
				'pm_batch_epoch_blocks'=>20,
				'pm_reveal_window_blocks'=>200,
				'pm_commit_no_reveal_penalty_percent'=>2000,
				'pm_min_batch_bet'=>'1.000 VIZ',
				'pm_commit_reveal_enabled'=>true,
				'pm_processing_cap_per_block'=>200,
				'pm_lazy_pool_enabled'=>true,
				'pm_lazy_alloc_percent'=>2000,
				'pm_lazy_max_total_alloc_percent'=>7000,
				'pm_lazy_lock_sec'=>604800,
				'pm_lazy_recall_step_percent'=>1000,
				'pm_lazy_emergency_penalty_percent'=>5000,
				'pm_lazy_min_liquidity_fee_percent'=>200,
				'pm_leverage_enabled'=>false,
				'pm_leverage_fund_percent'=>10,
				'pm_leverage_max_per_position_bp'=>20,
				'pm_leverage_pool_profit_percent'=>10,
				'pm_leverage_safety_margin_percent'=>1,
				'pm_leverage_max_slippage_percent'=>10,
				'pm_leverage_min_market_liquidity'=>'5000.000 VIZ',
				'pm_leverage_max_position_ratio_percent'=>5,
				'pm_leverage_expiration_buffer_sec'=>86400,
				'pm_leverage_m_factor_percent'=>50,
				'pm_leverage_funding_rate_ppm_per_day'=>50,
				'pm_conversion_profit_cost_percent'=>50,
				'pm_closed_market_retention_sec'=>432000,
			]);
			$props_types=array_merge($props_types,[
				'pm_oracle_registration_fee'=>'asset',
				'pm_min_oracle_insurance'=>'asset',
				'pm_market_creation_fee'=>'asset',
				'pm_min_liquidity'=>'asset',
				'pm_max_outcomes'=>'uint8',
				'pm_max_market_duration'=>'uint32',
				'pm_max_oracle_fee_percent'=>'uint16',
				'pm_oracle_accept_window_sec'=>'uint32',
				'pm_listing_min_coverage_percent'=>'uint16',
				'pm_betting_min_coverage_percent'=>'uint16',
				'pm_default_time_penalty_percent'=>'uint16',
				'pm_max_time_penalty'=>'uint32',
				'pm_dispute_fee'=>'asset',
				'pm_dispute_grace_sec'=>'uint32',
				'pm_oracle_dispute_response_sec'=>'uint32',
				'pm_dispute_auto_close_sec'=>'uint32',
				'pm_dispute_vote_period_sec'=>'uint32',
				'pm_dispute_approve_min_percent'=>'uint16',
				'pm_oracle_penalty_percent'=>'uint16',
				'pm_no_contest_penalty_percent'=>'uint16',
				'pm_dispute_reward_multiplier'=>'uint32',
				'pm_batch_epoch_blocks'=>'uint32',
				'pm_reveal_window_blocks'=>'uint32',
				'pm_commit_no_reveal_penalty_percent'=>'uint16',
				'pm_min_batch_bet'=>'asset',
				'pm_commit_reveal_enabled'=>'bool',
				'pm_processing_cap_per_block'=>'uint32',
				'pm_lazy_pool_enabled'=>'bool',
				'pm_lazy_alloc_percent'=>'uint16',
				'pm_lazy_max_total_alloc_percent'=>'uint16',
				'pm_lazy_lock_sec'=>'uint32',
				'pm_lazy_recall_step_percent'=>'uint16',
				'pm_lazy_emergency_penalty_percent'=>'uint16',
				'pm_lazy_min_liquidity_fee_percent'=>'uint16',
				'pm_leverage_enabled'=>'bool',
				'pm_leverage_fund_percent'=>'uint16',
				'pm_leverage_max_per_position_bp'=>'uint16',
				'pm_leverage_pool_profit_percent'=>'uint16',
				'pm_leverage_safety_margin_percent'=>'uint16',
				'pm_leverage_max_slippage_percent'=>'uint16',
				'pm_leverage_min_market_liquidity'=>'asset',
				'pm_leverage_max_position_ratio_percent'=>'uint16',
				'pm_leverage_expiration_buffer_sec'=>'uint32',
				'pm_leverage_m_factor_percent'=>'uint16',
				'pm_leverage_funding_rate_ppm_per_day'=>'uint32',
				'pm_conversion_profit_cost_percent'=>'uint16',
				'pm_closed_market_retention_sec'=>'uint32',
			]);
		}
		$json='["versioned_chain_properties_update",{';
		$json.='"owner":"'.$owner.'"';
		$new_props=$default_props;
		foreach($props as $prop=>$value){
			$new_props[$prop]=$value;
		}
		$json.=',"props":['.$version.','.json_encode($new_props).']';
		$json.='}]';
		$raw='2E';//operation number is 46
		$raw.=$this->encode_string($owner);
		$raw.=$this->encode_uint8($version).$this->encode_array($new_props,$props_types,true);
		return [$json,$raw];
	}
	function build_custom($required_active_auths=[],$required_regular_auths=[],$id=null,$json_str=null){
		$json='["custom",{';
		$json.='"required_active_auths":[';
		$accounts_list=[];
		foreach($required_active_auths as $account){
			$accounts_list[]='"'.$account.'"';
		}
		$json.=implode(',',$accounts_list);
		$json.=']';
		$json.=',"required_regular_auths":[';
		$accounts_list=[];
		foreach($required_regular_auths as $account){
			$accounts_list[]='"'.$account.'"';
		}
		$json.=implode(',',$accounts_list);
		$json.=']';
		$json.=',"id":"'.$id.'"';
		$json.=',"json":"'.addslashes($json_str).'"';
		$json.='}]';
		$raw='0a';//operation number is 10
		$raw.=$this->encode_array($required_active_auths,'string');
		$raw.=$this->encode_array($required_regular_auths,'string');
		$raw.=$this->encode_string($id);
		$raw.=$this->encode_string($json_str);
		return [$json,$raw];
	}
	function build_validator_update($owner,$url,$block_signing_key){
		$json='["validator_update",{';
		$json.='"owner":"'.$owner.'"';
		$json.=',"url":"'.$url.'"';
		$json.=',"block_signing_key":"'.$block_signing_key.'"';
		$json.='}]';
		$raw='06';//operation number is 6
		$raw.=$this->encode_string($owner);
		$raw.=$this->encode_string($url);
		$raw.=$this->encode_public_key($block_signing_key);
		return [$json,$raw];
	}
	function build_account_validator_vote($account,$validator,$approve=true){
		$json='["account_validator_vote",{';
		$json.='"account":"'.$account.'"';
		$json.=',"validator":"'.$validator.'"';
		$json.=',"approve":'.($approve?'true':'false').'';
		$json.='}]';

		$raw='07';//operation number is 7
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($validator);
		$raw.=$this->encode_bool($approve);
		return [$json,$raw];
	}
	function build_account_validator_proxy($account,$proxy){
		$json='["account_validator_proxy",{';
		$json.='"account":"'.$account.'"';
		$json.=',"proxy":"'.$proxy.'"';
		$json.='}]';

		$raw='08';//operation number is 8
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($proxy);
		return [$json,$raw];
	}
	function build_witness_update($owner,$url,$block_signing_key){
		$json='["witness_update",{';
		$json.='"owner":"'.$owner.'"';
		$json.=',"url":"'.$url.'"';
		$json.=',"block_signing_key":"'.$block_signing_key.'"';
		$json.='}]';
		$raw='06';//operation number is 6
		$raw.=$this->encode_string($owner);
		$raw.=$this->encode_string($url);
		$raw.=$this->encode_public_key($block_signing_key);
		return [$json,$raw];
	}
	function build_set_paid_subscription($account,$url,$levels,$amount,$period){
		$json='["set_paid_subscription",{';
		$json.='"account":"'.$account.'"';
		$json.=',"url":"'.$url.'"';
		$json.=',"levels":'.$levels.'';
		$json.=',"amount":"'.$amount.'"';
		$json.=',"period":'.$period.'';
		$json.='}]';
		$raw='32';//operation number is 50
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($url);
		$raw.=$this->encode_uint16($levels);
		$raw.=$this->encode_asset($amount);
		$raw.=$this->encode_uint16($period);
		return [$json,$raw];
	}
	function build_paid_subscribe($subscriber,$account,$level,$amount,$period,$auto_renewal){
		$json='["paid_subscribe",{';
		$json.='"subscriber":"'.$subscriber.'"';
		$json.=',"account":"'.$account.'"';
		$json.=',"level":'.$level.'';
		$json.=',"amount":"'.$amount.'"';
		$json.=',"period":'.$period.'';
		$json.=',"auto_renewal":'.($auto_renewal?'true':'false').'';
		$json.='}]';
		$raw='33';//operation number is 51
		$raw.=$this->encode_string($subscriber);
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_uint16($level);
		$raw.=$this->encode_asset($amount);
		$raw.=$this->encode_uint16($period);
		$raw.=$this->encode_bool($auto_renewal);
		return [$json,$raw];
	}
	function build_set_account_price($account,$account_seller,$account_offer_price,$account_on_sale){
		$json='["set_account_price",{';
		$json.='"account":"'.$account.'"';
		$json.=',"account_seller":"'.$account_seller.'"';
		$json.=',"account_offer_price":"'.$account_offer_price.'"';
		$json.=',"account_on_sale":'.($account_on_sale?'true':'false').'';
		$json.='}]';
		$raw='36';//operation number is 54
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($account_seller);
		$raw.=$this->encode_asset($account_offer_price);
		$raw.=$this->encode_bool($account_on_sale);
		return [$json,$raw];
	}
	function build_target_account_sale($account,$account_seller,$target_buyer,$account_offer_price,$account_on_sale){
		$json='["target_account_sale",{';
		$json.='"account":"'.$account.'"';
		$json.=',"account_seller":"'.$account_seller.'"';
		$json.=',"target_buyer":"'.$target_buyer.'"';
		$json.=',"account_offer_price":"'.$account_offer_price.'"';
		$json.=',"account_on_sale":'.($account_on_sale?'true':'false').'';
		$json.='}]';
		$raw='3d';//operation number is 61
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($account_seller);
		$raw.=$this->encode_string($target_buyer);
		$raw.=$this->encode_asset($account_offer_price);
		$raw.=$this->encode_bool($account_on_sale);
		return [$json,$raw];
	}
	function build_set_reward_sharing($owner,$sharing_rate){
		$json='["set_reward_sharing",{';
		$json.='"owner":"'.$owner.'"';
		$json.=',"sharing_rate":'.$sharing_rate;
		$json.='}]';
		$raw='40';//operation number is 64
		$raw.=$this->encode_string($owner);
		$raw.=$this->encode_uint16($sharing_rate);
		return [$json,$raw];
	}
	function build_set_subaccount_price($account,$subaccount_seller,$subaccount_offer_price,$subaccount_on_sale){
		$json='["set_subaccount_price",{';
		$json.='"account":"'.$account.'"';
		$json.=',"subaccount_seller":"'.$subaccount_seller.'"';
		$json.=',"subaccount_offer_price":"'.$subaccount_offer_price.'"';
		$json.=',"subaccount_on_sale":'.($subaccount_on_sale?'true':'false').'';
		$json.='}]';
		$raw='37';//operation number is 55
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($subaccount_seller);
		$raw.=$this->encode_asset($subaccount_offer_price);
		$raw.=$this->encode_bool($subaccount_on_sale);
		return [$json,$raw];
	}
	function build_buy_account($buyer,$account,$account_offer_price,$account_authorities_key,$tokens_to_shares){
		$json='["buy_account",{';
		$json.='"buyer":"'.$buyer.'"';
		$json.=',"account":"'.$account.'"';
		$json.=',"account_offer_price":"'.$account_offer_price.'"';
		$json.=',"account_authorities_key":"'.$account_authorities_key.'"';
		$json.=',"tokens_to_shares":"'.$tokens_to_shares.'"';
		$json.='}]';
		$raw='38';//operation number is 56
		$raw.=$this->encode_string($buyer);
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_asset($account_offer_price);
		$raw.=$this->encode_public_key($account_authorities_key);
		$raw.=$this->encode_asset($tokens_to_shares);
		return [$json,$raw];
	}
	function build_proposal_create($author,$title,$memo,$expiration_time,$proposed_operations=[],$review_period_time=false){
		$json='["proposal_create",{';
		$json.='"author":"'.$author.'"';
		$json.=',"title":"'.$title.'"';
		$json.=',"memo":"'.$memo.'"';
		$json.=',"expiration_time":"'.$expiration_time.'"';
		$proposed_operations_json_arr=[];
		$proposed_operations_raw_arr=[];
		foreach($proposed_operations as $operation_arr){
			$proposed_operations_json_arr[]='{"op":'.$operation_arr[0].'}';
			$proposed_operations_raw_arr[]=$operation_arr[1];
		}
		$json.=',"proposed_operations":['.implode(',',$proposed_operations_json_arr).']';
		//review_period_time optional
		if($review_period_time){
			$json.=',"review_period_time":"'.$review_period_time.'"';
		}
		$json.='}]';
		$raw='16';//operation number is 22
		$raw.=$this->encode_string($author);
		$raw.=$this->encode_string($title);
		$raw.=$this->encode_string($memo);
		$raw.=$this->encode_timestamp($expiration_time);
		$raw.=$this->encode_uint8(count($proposed_operations_raw_arr)).implode('',$proposed_operations_raw_arr);
		//review_period_time optional
		if(false===$review_period_time){
			$raw.='00';
		}
		else{
			$raw.='01';
			$raw.=$this->encode_timestamp($review_period_time);
		}
		$raw.='00';//op extension
		return [$json,$raw];
	}
	function build_proposal_update($author,$title,
		$active_approvals_to_add=[],$active_approvals_to_remove=[],
		$master_approvals_to_add=[],$master_approvals_to_remove=[],
		$regular_approvals_to_add=[],$regular_approvals_to_remove=[],
		$key_approvals_to_add=[],$key_approvals_to_remove=[]
	){
		$json='["proposal_update",{';
		$json.='"author":"'.$author.'"';
		$json.=',"title":"'.$title.'"';
		$active_approvals_to_add_arr=[];
		foreach($active_approvals_to_add as $v){
			$active_approvals_to_add_arr[]='"'.$v.'"';
		}
		$json.=',"active_approvals_to_add":['.implode(',',$active_approvals_to_add_arr).']';
		$active_approvals_to_remove_arr=[];
		foreach($active_approvals_to_remove as $v){
			$active_approvals_to_remove_arr[]='"'.$v.'"';
		}
		$json.=',"active_approvals_to_remove":['.implode(',',$active_approvals_to_remove_arr).']';

		$master_approvals_to_add_arr=[];
		foreach($master_approvals_to_add as $v){
			$master_approvals_to_add_arr[]='"'.$v.'"';
		}
		$json.=',"master_approvals_to_add":['.implode(',',$master_approvals_to_add_arr).']';
		$master_approvals_to_remove_arr=[];
		foreach($master_approvals_to_remove as $v){
			$master_approvals_to_remove_arr[]='"'.$v.'"';
		}
		$json.=',"master_approvals_to_remove":['.implode(',',$master_approvals_to_remove_arr).']';

		$regular_approvals_to_add_arr=[];
		foreach($regular_approvals_to_add as $v){
			$regular_approvals_to_add_arr[]='"'.$v.'"';
		}
		$json.=',"regular_approvals_to_add":['.implode(',',$regular_approvals_to_add_arr).']';
		$regular_approvals_to_remove_arr=[];
		foreach($regular_approvals_to_remove as $v){
			$regular_approvals_to_remove_arr[]='"'.$v.'"';
		}
		$json.=',"regular_approvals_to_remove":['.implode(',',$regular_approvals_to_remove_arr).']';

		$key_approvals_to_add_arr=[];
		foreach($key_approvals_to_add as $v){
			$key_approvals_to_add_arr[]='"'.$v.'"';
		}
		$json.=',"key_approvals_to_add":['.implode(',',$key_approvals_to_add_arr).']';
		$key_approvals_to_remove_arr=[];
		foreach($key_approvals_to_remove as $v){
			$key_approvals_to_remove_arr[]='"'.$v.'"';
		}
		$json.=',"key_approvals_to_remove":['.implode(',',$key_approvals_to_remove_arr).']';

		$json.='}]';
		$raw='17';//operation number is 23
		$raw.=$this->encode_string($author);
		$raw.=$this->encode_string($title);
		$raw.=$this->encode_array($active_approvals_to_add,['string']);
		$raw.=$this->encode_array($active_approvals_to_remove,['string']);
		$raw.=$this->encode_array($master_approvals_to_add,['string']);
		$raw.=$this->encode_array($master_approvals_to_remove,['string']);
		$raw.=$this->encode_array($regular_approvals_to_add,['string']);
		$raw.=$this->encode_array($regular_approvals_to_remove,['string']);
		$raw.=$this->encode_array($key_approvals_to_add,['string']);
		$raw.=$this->encode_array($key_approvals_to_remove,['string']);
		$raw.='00';//op extension
		return [$json,$raw];
	}
	function build_proposal_delete($author,$title,$requester){
		$json='["proposal_delete",{';
		$json.='"author":"'.$author.'"';
		$json.=',"title":"'.$title.'"';
		$json.=',"requester":"'.$requester.'"';
		$json.='}]';
		$raw='18';//operation number is 24
		$raw.=$this->encode_string($author);
		$raw.=$this->encode_string($title);
		$raw.=$this->encode_string($requester);
		$raw.='00';//op extension
		return [$json,$raw];
	}

	// ===== Prediction Markets (Onix, HF14) — 23 user operations =====
	// Each op ends with an empty extensions vector (raw '00'; the key is omitted from JSON like the
	// core ops above). Percent fields are basis points (10000=100%) unless the field note says otherwise;
	// the pm_leverage_* / conversion knobs are plain percent but are governance params, not op fields.
	// Object ids (market_id/bet_id/liquidity_id/position_id/commit_id) are bare integers (pm_object_id_type).
	function build_pm_oracle_register($owner,$insurance,$fee_percent,$fixed_fee,$rules_url,$auto_accept_creator='',$auto_accept_resolver='',$auto_accept=false){
		$json='["pm_oracle_register",{';
		$json.='"owner":'.$this->json_string($owner);
		$json.=',"insurance":"'.$insurance.'"';
		$json.=',"fee_percent":'.$fee_percent;
		$json.=',"fixed_fee":"'.$fixed_fee.'"';
		$json.=',"rules_url":'.$this->json_string($rules_url);
		$json.=',"auto_accept_creator":'.$this->json_string($auto_accept_creator);
		$json.=',"auto_accept_resolver":'.$this->json_string($auto_accept_resolver);
		$json.=',"auto_accept":'.($auto_accept?'true':'false');
		$json.='}]';
		$raw='42';//op-id 66
		$raw.=$this->encode_string($owner);
		$raw.=$this->encode_asset($insurance);
		$raw.=$this->encode_uint16($fee_percent);
		$raw.=$this->encode_asset($fixed_fee);
		$raw.=$this->encode_string($rules_url);
		$raw.=$this->encode_string($auto_accept_creator);
		$raw.=$this->encode_string($auto_accept_resolver);
		$raw.=$this->encode_bool($auto_accept);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_oracle_update($owner,$insurance_delta=null,$fee_percent=null,$fixed_fee=null,$rules_url=null,$auto_accept_creator=null,$auto_accept_resolver=null,$auto_accept=null){
		//optional fields: pass null to leave unchanged. insurance_delta is a signed asset ("-5.000 VIZ" to withdraw).
		$json='["pm_oracle_update",{';
		$json.='"owner":'.$this->json_string($owner);
		if($insurance_delta!==null){$json.=',"insurance_delta":"'.$insurance_delta.'"';}
		if($fee_percent!==null){$json.=',"fee_percent":'.$fee_percent;}
		if($fixed_fee!==null){$json.=',"fixed_fee":"'.$fixed_fee.'"';}
		if($rules_url!==null){$json.=',"rules_url":'.$this->json_string($rules_url);}
		if($auto_accept_creator!==null){$json.=',"auto_accept_creator":'.$this->json_string($auto_accept_creator);}
		if($auto_accept_resolver!==null){$json.=',"auto_accept_resolver":'.$this->json_string($auto_accept_resolver);}
		if($auto_accept!==null){$json.=',"auto_accept":'.($auto_accept?'true':'false');}
		$json.='}]';
		$raw='43';//op-id 67
		$raw.=$this->encode_string($owner);
		$raw.=$this->encode_optional($insurance_delta!==null,$insurance_delta!==null?$this->encode_asset($insurance_delta):'');
		$raw.=$this->encode_optional($fee_percent!==null,$fee_percent!==null?$this->encode_uint16($fee_percent):'');
		$raw.=$this->encode_optional($fixed_fee!==null,$fixed_fee!==null?$this->encode_asset($fixed_fee):'');
		$raw.=$this->encode_optional($rules_url!==null,$rules_url!==null?$this->encode_string($rules_url):'');
		$raw.=$this->encode_optional($auto_accept_creator!==null,$auto_accept_creator!==null?$this->encode_string($auto_accept_creator):'');
		$raw.=$this->encode_optional($auto_accept_resolver!==null,$auto_accept_resolver!==null?$this->encode_string($auto_accept_resolver):'');
		$raw.=$this->encode_optional($auto_accept!==null,$auto_accept!==null?$this->encode_bool($auto_accept):'');
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_create_market($creator,$oracle,$market_type,$outcomes,$url,$oracle_fee_percent,$oracle_fixed_fee,$creator_fee_percent,$liquidity_fee_percent,$liquidity,$lmsr_b,$betting_expiration,$result_expiration,$time_penalty_type=0,$time_penalty_value=0,$penalty_curve_type=0,$allow_early_resolution=false,$allow_cancellation=false,$allow_batch=false,$allow_instant_bet=true,$endogeneity_tier=1,$dispute_mode=0,$dispute_resolver='',$dispute_penalty_percent=0,$metadata=''){
		$outcomes=array_values($outcomes);
		$json='["pm_create_market",{';
		$json.='"creator":'.$this->json_string($creator);
		$json.=',"oracle":'.$this->json_string($oracle);
		$json.=',"market_type":'.$market_type;
		$json.=',"outcomes":'.json_encode($outcomes,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		$json.=',"url":'.$this->json_string($url);
		$json.=',"oracle_fee_percent":'.$oracle_fee_percent;
		$json.=',"oracle_fixed_fee":"'.$oracle_fixed_fee.'"';
		$json.=',"creator_fee_percent":'.$creator_fee_percent;
		$json.=',"liquidity_fee_percent":'.$liquidity_fee_percent;
		$json.=',"liquidity":"'.$liquidity.'"';
		$json.=',"lmsr_b":'.$lmsr_b;
		$json.=',"betting_expiration":"'.$betting_expiration.'"';
		$json.=',"result_expiration":"'.$result_expiration.'"';
		$json.=',"time_penalty_type":'.$time_penalty_type;
		$json.=',"time_penalty_value":'.$time_penalty_value;
		$json.=',"penalty_curve_type":'.$penalty_curve_type;
		$json.=',"allow_early_resolution":'.($allow_early_resolution?'true':'false');
		$json.=',"allow_cancellation":'.($allow_cancellation?'true':'false');
		$json.=',"allow_batch":'.($allow_batch?'true':'false');
		$json.=',"allow_instant_bet":'.($allow_instant_bet?'true':'false');
		$json.=',"endogeneity_tier":'.$endogeneity_tier;
		$json.=',"dispute_mode":'.$dispute_mode;
		$json.=',"dispute_resolver":'.$this->json_string($dispute_resolver);
		$json.=',"dispute_penalty_percent":'.$dispute_penalty_percent;
		$json.=',"metadata":'.$this->json_string($metadata);
		$json.='}]';
		$raw='44';//op-id 68
		$raw.=$this->encode_string($creator);
		$raw.=$this->encode_string($oracle);
		$raw.=$this->encode_uint8($market_type);
		$raw.=$this->encode_array($outcomes,['string']);
		$raw.=$this->encode_string($url);
		$raw.=$this->encode_uint16($oracle_fee_percent);
		$raw.=$this->encode_asset($oracle_fixed_fee);
		$raw.=$this->encode_uint16($creator_fee_percent);
		$raw.=$this->encode_uint16($liquidity_fee_percent);
		$raw.=$this->encode_asset($liquidity);
		$raw.=$this->encode_int64($lmsr_b);
		$raw.=$this->encode_timestamp($betting_expiration);
		$raw.=$this->encode_timestamp($result_expiration);
		$raw.=$this->encode_uint8($time_penalty_type);
		$raw.=$this->encode_uint32($time_penalty_value);
		$raw.=$this->encode_uint8($penalty_curve_type);
		$raw.=$this->encode_bool($allow_early_resolution);
		$raw.=$this->encode_bool($allow_cancellation);
		$raw.=$this->encode_bool($allow_batch);
		$raw.=$this->encode_bool($allow_instant_bet);
		$raw.=$this->encode_uint8($endogeneity_tier);
		$raw.=$this->encode_uint8($dispute_mode);
		$raw.=$this->encode_string($dispute_resolver);
		$raw.=$this->encode_int16($dispute_penalty_percent);
		$raw.=$this->encode_string($metadata);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_oracle_accept_market($oracle,$market_id,$accept,$oracle_fee_percent=0,$oracle_fixed_fee='0.000 VIZ'){
		$json='["pm_oracle_accept_market",{';
		$json.='"oracle":'.$this->json_string($oracle);
		$json.=',"market_id":'.$market_id;
		$json.=',"accept":'.($accept?'true':'false');
		$json.=',"oracle_fee_percent":'.$oracle_fee_percent;
		$json.=',"oracle_fixed_fee":"'.$oracle_fixed_fee.'"';
		$json.='}]';
		$raw='45';//op-id 69
		$raw.=$this->encode_string($oracle);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_bool($accept);
		$raw.=$this->encode_uint16($oracle_fee_percent);
		$raw.=$this->encode_asset($oracle_fixed_fee);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_place_bet($account,$market_id,$side,$outcome_index,$amount,$min_tokens=0,$mode=0){
		//binary: side 0/1, outcome_index -1. multi: side -1, outcome_index 0..N-1. mode 0 instant / 1 batch.
		$json='["pm_place_bet",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"market_id":'.$market_id;
		$json.=',"side":'.$side;
		$json.=',"outcome_index":'.$outcome_index;
		$json.=',"amount":"'.$amount.'"';
		$json.=',"min_tokens":'.$min_tokens;
		$json.=',"mode":'.$mode;
		$json.='}]';
		$raw='46';//op-id 70
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_int8($side);
		$raw.=$this->encode_int16($outcome_index);
		$raw.=$this->encode_asset($amount);
		$raw.=$this->encode_int64($min_tokens);
		$raw.=$this->encode_uint8($mode);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_commit_bet($account,$market_id,$commitment,$escrow_amount,$no_reveal_fee_percent){
		//commitment = SHA-256 hex of the preimage (see pm_commitment()). no_reveal_fee_percent MUST equal
		//median(pm_commit_no_reveal_penalty_percent) or the node rejects the commit.
		$json='["pm_commit_bet",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"market_id":'.$market_id;
		$json.=',"commitment":"'.strtolower($commitment).'"';
		$json.=',"escrow_amount":"'.$escrow_amount.'"';
		$json.=',"no_reveal_fee_percent":'.$no_reveal_fee_percent;
		$json.='}]';
		$raw='47';//op-id 71
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_sha256($commitment);
		$raw.=$this->encode_asset($escrow_amount);
		$raw.=$this->encode_uint16($no_reveal_fee_percent);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_reveal_bet($account,$commit_id,$side,$outcome_index,$amount,$salt,$min_tokens=0){
		$json='["pm_reveal_bet",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"commit_id":'.$commit_id;
		$json.=',"side":'.$side;
		$json.=',"outcome_index":'.$outcome_index;
		$json.=',"amount":"'.$amount.'"';
		$json.=',"salt":'.$this->json_string($salt);
		$json.=',"min_tokens":'.$min_tokens;
		$json.='}]';
		$raw='48';//op-id 72
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_int64($commit_id);
		$raw.=$this->encode_int8($side);
		$raw.=$this->encode_int16($outcome_index);
		$raw.=$this->encode_asset($amount);
		$raw.=$this->encode_string($salt);
		$raw.=$this->encode_int64($min_tokens);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_cancel_bet($account,$bet_id,$min_return=0){
		$json='["pm_cancel_bet",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"bet_id":'.$bet_id;
		$json.=',"min_return":'.$min_return;
		$json.='}]';
		$raw='49';//op-id 73
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_int64($bet_id);
		$raw.=$this->encode_int64($min_return);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_add_liquidity($provider,$market_id,$amount){
		$json='["pm_add_liquidity",{';
		$json.='"provider":'.$this->json_string($provider);
		$json.=',"market_id":'.$market_id;
		$json.=',"amount":"'.$amount.'"';
		$json.='}]';
		$raw='4a';//op-id 74
		$raw.=$this->encode_string($provider);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_asset($amount);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_withdraw_liquidity($provider,$liquidity_id,$amount='0.000 VIZ'){
		//amount 0 = full position
		$json='["pm_withdraw_liquidity",{';
		$json.='"provider":'.$this->json_string($provider);
		$json.=',"liquidity_id":'.$liquidity_id;
		$json.=',"amount":"'.$amount.'"';
		$json.='}]';
		$raw='4b';//op-id 75
		$raw.=$this->encode_string($provider);
		$raw.=$this->encode_int64($liquidity_id);
		$raw.=$this->encode_asset($amount);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_resolve_market($oracle,$market_id,$winning_outcome,$decision_url='',$decision_reason=''){
		$json='["pm_resolve_market",{';
		$json.='"oracle":'.$this->json_string($oracle);
		$json.=',"market_id":'.$market_id;
		$json.=',"winning_outcome":'.$winning_outcome;
		$json.=',"decision_url":'.$this->json_string($decision_url);
		$json.=',"decision_reason":'.$this->json_string($decision_reason);
		$json.='}]';
		$raw='4c';//op-id 76
		$raw.=$this->encode_string($oracle);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_int16($winning_outcome);
		$raw.=$this->encode_string($decision_url);
		$raw.=$this->encode_string($decision_reason);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_no_contest($oracle,$market_id,$reason=''){
		$json='["pm_no_contest",{';
		$json.='"oracle":'.$this->json_string($oracle);
		$json.=',"market_id":'.$market_id;
		$json.=',"reason":'.$this->json_string($reason);
		$json.='}]';
		$raw='4d';//op-id 77
		$raw.=$this->encode_string($oracle);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_string($reason);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_dispute_create($disputer,$market_id,$proposed_outcome,$reason=''){
		//proposed_outcome -1 = void/no-contest challenge
		$json='["pm_dispute_create",{';
		$json.='"disputer":'.$this->json_string($disputer);
		$json.=',"market_id":'.$market_id;
		$json.=',"proposed_outcome":'.$proposed_outcome;
		$json.=',"reason":'.$this->json_string($reason);
		$json.='}]';
		$raw='4e';//op-id 78
		$raw.=$this->encode_string($disputer);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_int16($proposed_outcome);
		$raw.=$this->encode_string($reason);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_dispute_vote($voter,$market_id,$vote_outcome,$vote_percent){
		//committee mode; signed with REGULAR authority. vote_outcome -1 = uphold oracle.
		$json='["pm_dispute_vote",{';
		$json.='"voter":'.$this->json_string($voter);
		$json.=',"market_id":'.$market_id;
		$json.=',"vote_outcome":'.$vote_outcome;
		$json.=',"vote_percent":'.$vote_percent;
		$json.='}]';
		$raw='4f';//op-id 79
		$raw.=$this->encode_string($voter);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_int16($vote_outcome);
		$raw.=$this->encode_int16($vote_percent);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_dispute_resolve($resolver,$market_id,$correct_outcome,$penalty_amount='0.000 VIZ',$ban_oracle=false,$ban_oracle_until='1970-01-01T00:00:00',$ban_creator=false,$ban_creator_until='1970-01-01T00:00:00'){
		//account mode verdict. *_until = 2106-02-07T06:28:15 for a permanent ban (time_point_sec::maximum()).
		$json='["pm_dispute_resolve",{';
		$json.='"resolver":'.$this->json_string($resolver);
		$json.=',"market_id":'.$market_id;
		$json.=',"correct_outcome":'.$correct_outcome;
		$json.=',"penalty_amount":"'.$penalty_amount.'"';
		$json.=',"ban_oracle":'.($ban_oracle?'true':'false');
		$json.=',"ban_oracle_until":"'.$ban_oracle_until.'"';
		$json.=',"ban_creator":'.($ban_creator?'true':'false');
		$json.=',"ban_creator_until":"'.$ban_creator_until.'"';
		$json.='}]';
		$raw='50';//op-id 80
		$raw.=$this->encode_string($resolver);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_int16($correct_outcome);
		$raw.=$this->encode_asset($penalty_amount);
		$raw.=$this->encode_bool($ban_oracle);
		$raw.=$this->encode_timestamp($ban_oracle_until);
		$raw.=$this->encode_bool($ban_creator);
		$raw.=$this->encode_timestamp($ban_creator_until);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_transfer_position($from,$bet_id,$to,$amount=0,$memo=''){
		//amount = weight to reassign (share_type); 0 = full. memo plaintext or #-prefixed ECIES.
		$json='["pm_transfer_position",{';
		$json.='"from":'.$this->json_string($from);
		$json.=',"bet_id":'.$bet_id;
		$json.=',"to":'.$this->json_string($to);
		$json.=',"amount":'.$amount;
		$json.=',"memo":'.$this->json_string($memo);
		$json.='}]';
		$raw='51';//op-id 81
		$raw.=$this->encode_string($from);
		$raw.=$this->encode_int64($bet_id);
		$raw.=$this->encode_string($to);
		$raw.=$this->encode_int64($amount);
		$raw.=$this->encode_string($memo);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_lazy_deposit($account,$amount){
		$json='["pm_lazy_deposit",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"amount":"'.$amount.'"';
		$json.='}]';
		$raw='52';//op-id 82
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_asset($amount);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_lazy_withdraw($account,$shares=0,$emergency=false){
		//shares = pool shares to burn; 0 = all. emergency = withdraw before lock ends (penalty on profit).
		$json='["pm_lazy_withdraw",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"shares":'.$shares;
		$json.=',"emergency":'.($emergency?'true':'false');
		$json.='}]';
		$raw='53';//op-id 83
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_int64($shares);
		$raw.=$this->encode_bool($emergency);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_leverage_open($account,$market_id,$outcome_index,$collateral,$loan,$min_tokens=0,$max_slippage_percent=0){
		//binary CPMM only; gated by pm_leverage_enabled. max_slippage_percent is bp.
		$json='["pm_leverage_open",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"market_id":'.$market_id;
		$json.=',"outcome_index":'.$outcome_index;
		$json.=',"collateral":"'.$collateral.'"';
		$json.=',"loan":"'.$loan.'"';
		$json.=',"min_tokens":'.$min_tokens;
		$json.=',"max_slippage_percent":'.$max_slippage_percent;
		$json.='}]';
		$raw='5b';//op-id 91
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_int16($outcome_index);
		$raw.=$this->encode_asset($collateral);
		$raw.=$this->encode_asset($loan);
		$raw.=$this->encode_int64($min_tokens);
		$raw.=$this->encode_uint16($max_slippage_percent);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_leverage_close($account,$position_id,$min_return=0){
		$json='["pm_leverage_close",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"position_id":'.$position_id;
		$json.=',"min_return":'.$min_return;
		$json.='}]';
		$raw='5c';//op-id 92
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_int64($position_id);
		$raw.=$this->encode_int64($min_return);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_leverage_convert($account,$position_id,$conversion_profit_cost){
		//conversion_profit_cost MUST equal median(pm_conversion_profit_cost_percent) (plain percent, 50=50%).
		$json='["pm_leverage_convert",{';
		$json.='"account":'.$this->json_string($account);
		$json.=',"position_id":'.$position_id;
		$json.=',"conversion_profit_cost":'.$conversion_profit_cost;
		$json.='}]';
		$raw='5d';//op-id 93
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_int64($position_id);
		$raw.=$this->encode_uint16($conversion_profit_cost);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_dispute_oracle_respond($oracle,$market_id,$response){
		$json='["pm_dispute_oracle_respond",{';
		$json.='"oracle":'.$this->json_string($oracle);
		$json.=',"market_id":'.$market_id;
		$json.=',"response":'.$this->json_string($response);
		$json.='}]';
		$raw='62';//op-id 98
		$raw.=$this->encode_string($oracle);
		$raw.=$this->encode_int64($market_id);
		$raw.=$this->encode_string($response);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function build_pm_unban($resolver,$target,$unban_oracle=false,$unban_creator=false){
		//resolver must equal the target's banned_by. At least one of unban_oracle/unban_creator must be true.
		$json='["pm_unban",{';
		$json.='"resolver":'.$this->json_string($resolver);
		$json.=',"target":'.$this->json_string($target);
		$json.=',"unban_oracle":'.($unban_oracle?'true':'false');
		$json.=',"unban_creator":'.($unban_creator?'true':'false');
		$json.='}]';
		$raw='63';//op-id 99
		$raw.=$this->encode_string($resolver);
		$raw.=$this->encode_string($target);
		$raw.=$this->encode_bool($unban_oracle);
		$raw.=$this->encode_bool($unban_creator);
		$raw.='00';//extensions
		return [$json,$raw];
	}
	function pm_commitment($market_id,$account,$side,$outcome_index,$amount,$min_tokens,$salt){
		//SHA-256 commitment for pm_commit_bet (spec 3.6.1). amount & min_tokens are share_type (milli-VIZ,
		//i.e. VIZ*1000). Raw binary concat, little-endian ints, 32-byte zero-padded account, then salt bytes.
		$preimage ='';
		$preimage.=pack('P',$market_id);//8 bytes int64 LE
		$preimage.=str_pad(substr($account,0,32),32,"\0",STR_PAD_RIGHT);//32-byte account buffer
		$preimage.=chr($side & 0xFF);//1 byte int8 (multi: -1 => 0xFF)
		$preimage.=pack('v',$outcome_index & 0xFFFF);//2 bytes int16 LE (binary: -1 => 0xFFFF)
		$preimage.=pack('P',$amount);//8 bytes int64 LE (milli-VIZ)
		$preimage.=pack('P',$min_tokens);//8 bytes int64 LE
		$preimage.=$salt;//raw bytes, no terminator
		return hash('sha256',$preimage);
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
		$input_vlq=Utils::vlq_create($input);
		return bin2hex($input_vlq).bin2hex(pack('H*',bin2hex($input)));
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
	function encode_int8($input){
		return bin2hex(pack('c',$input));//signed byte, -1 => ff
	}
	function encode_int64($input){
		//little-endian 8-byte integer (share_type / pm_object_id_type). Values are non-negative in PM ops.
		return bin2hex(pack('P',$input));
	}
	function encode_sha256($input){
		//raw 32-byte digest, no length prefix. Accepts 64-char hex string.
		$hex=strtolower($input);
		if(0===strpos($hex,'0x')){
			$hex=substr($hex,2);
		}
		return str_pad($hex,64,'0',STR_PAD_LEFT);
	}
	function encode_optional($present,$hex=''){
		//fc::optional<T>: 0x00 when absent, 0x01 + encoded value when present
		if($present){
			return '01'.$hex;
		}
		return '00';
	}
	function json_string($input){
		//robust JSON string literal (handles quotes/unicode); node re-decodes to the same UTF-8 bytes
		return json_encode((string)$input,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
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