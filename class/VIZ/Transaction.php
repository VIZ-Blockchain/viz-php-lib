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
	function execute($transaction_json){
		return $this->api->execute_method('broadcast_transaction',$transaction_json,false);
	}
	function build($operations_json,$operations_data,$operations_count){
		$dgp=$this->api->execute_method('get_dynamic_global_properties');
		if(!$this->api->return_only_result){
			$dgp=$dgp['result'];
		}
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
		$raw.=$this->encode_string($account);
		$raw.=$this->encode_string($witness);
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
		$json.=',"url":"'.url.'"';
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
		$json.=',"request_id":'.request_id.'';
		$json.='}]';
		$raw='24';//operation number is 36
		$raw.=$this->encode_string($creator);
		$raw.=$this->encode_uint32($request_id);
		return [$json,$raw];
	}
	function build_committee_vote_request($creator,$request_id,$vote_percent){
		$json='["committee_vote_request",{';
		$json.='"creator":"'.$creator.'"';
		$json.=',"request_id":'.request_id.'';
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
	function build_versioned_chain_properties_update($owner,$props){
		$version=3;
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
			'inflation_witness_percent'=>2000,
			'inflation_ratio_committee_vs_reward_fund'=>5000,
			'inflation_recalc_period'=>806400,
			'data_operations_cost_additional_bandwidth'=>10000,
			'witness_miss_penalty_percent'=>100,
			'witness_miss_penalty_duration'=>86400,
			'create_invite_min_balance'=>'10.000 VIZ',
			'committee_create_request_fee'=>'100.000 VIZ',
			'create_paid_subscription_fee'=>'100.000 VIZ',
			'account_on_sale_fee'=>'10.000 VIZ',
			'subaccount_on_sale_fee'=>'100.000 VIZ',
			'witness_declaration_fee'=>'10.000 VIZ',
			'withdraw_intervals'=>28,
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
			'inflation_witness_percent'=>'int16',
			'inflation_ratio_committee_vs_reward_fund'=>'int16',
			'inflation_recalc_period'=>'uint32',
			'data_operations_cost_additional_bandwidth'=>'uint32',
			'witness_miss_penalty_percent'=>'int16',
			'witness_miss_penalty_duration'=>'uint32',
			'create_invite_min_balance'=>'asset',
			'committee_create_request_fee'=>'asset',
			'create_paid_subscription_fee'=>'asset',
			'account_on_sale_fee'=>'asset',
			'subaccount_on_sale_fee'=>'asset',
			'witness_declaration_fee'=>'asset',
			'withdraw_intervals'=>'uint16'
		];
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
	function build_custom($required_active_auths=[],$required_regular_auths=[],$id,$json_str){
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