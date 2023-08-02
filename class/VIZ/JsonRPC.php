<?php
namespace VIZ;

class JsonRpc{
	public $endpoint='';
	public $debug=false;
	public $request_arr=array();
	public $result_arr=array();
	public $post_num=0;
	public $increase_post_num=true;
	public $header_arr=array();
	public $host_ip=array();
	public $accept_gzip=false;
	public $check_ssl=true;
	public $return_only_result=true;
	public $read_timeout=3;//sec
	function __construct($endpoint='',$debug=false){
		$this->endpoint=$endpoint;
		$this->debug=$debug;
		$this->request_arr=array();
		$this->result_arr=array();
	}
	function set_header($name,$value){
		$this->header_arr[$name]=$value;
		if(''==$value){
			unset($this->header_arr[$name]);
		}
	}
	private $api=array(
		//account_by_key
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/account_by_key/account_by_key_plugin.cpp
		'get_key_references'=>'account_by_key',

		//account_history
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/account_history/plugin.cpp
		'get_account_history'=>'account_history',

		//committee_api
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/committee_api/committee_api.cpp
		'get_committee_request'=>'committee_api',
		'get_committee_request_votes'=>'committee_api',
		'get_committee_requests_list'=>'committee_api',

		//custom_protocol_api
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/custom_protocol_api/custom_protocol_api.cpp
		'get_account'=>'custom_protocol_api',

		//database_api
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/database_api/api.cpp
		/* Blocks and transactions */
		'get_block'=>'database_api',
		'get_block_header'=>'database_api',
		'get_irreversible_block'=>'database_api',
		'get_irreversible_block_header'=>'database_api',
		'set_block_applied_callback'=>'database_api',
		'get_chain_properties'=>'database_api',
		'get_config'=>'database_api',
		'get_database_info'=>'database_api',
		'get_dynamic_global_properties'=>'database_api',
		'get_hardfork_version'=>'database_api',
		'get_next_scheduled_hardfork'=>'database_api',
		/* Accounts */
		'get_account_count'=>'database_api',
		'get_accounts'=>'database_api',
		'get_accounts_on_sale'=>'database_api',
		'get_accounts_on_auction'=>'database_api',
		'get_escrow'=>'database_api',
		'get_expiring_vesting_delegations'=>'database_api',
		'get_owner_history'=>'database_api',
		'get_recovery_request'=>'database_api',
		'get_subaccounts_on_sale'=>'database_api',
		'get_vesting_delegations'=>'database_api',
		'get_withdraw_routes'=>'database_api',
		'lookup_account_names'=>'database_api',
		'lookup_accounts'=>'database_api',
		/* Authority / validation */
		'get_potential_signatures'=>'database_api',
		'get_proposed_transaction'=>'database_api',
		'get_proposed_transactions'=>'database_api',
		'get_required_signatures'=>'database_api',
		'get_transaction_hex'=>'database_api',
		'verify_account_authority'=>'database_api',
		'verify_authority'=>'database_api',

		//invite_api
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/invite_api/invite_api.cpp
		'get_invite_by_id'=>'invite_api',
		'get_invite_by_key'=>'invite_api',
		'get_invites_list'=>'invite_api',

		//network_broadcast_api
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/network_broadcast_api/network_broadcast_api.cpp
		'broadcast_block'=>'network_broadcast_api',
		'broadcast_transaction'=>'network_broadcast_api',
		'broadcast_transaction_synchronous'=>'network_broadcast_api',
		'broadcast_transaction_with_callback'=>'network_broadcast_api',

		//operation_history
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/operation_history/plugin.cpp
		'get_ops_in_block'=>'operation_history',
		'get_transaction'=>'operation_history',

		//paid_subscription_api
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/paid_subscription_api/paid_subscription_api.cpp
		'get_active_paid_subscriptions'=>'paid_subscription_api',
		'get_inactive_paid_subscriptions'=>'paid_subscription_api',
		'get_paid_subscription_options'=>'paid_subscription_api',
		'get_paid_subscription_status'=>'paid_subscription_api',
		'get_paid_subscriptions'=>'paid_subscription_api',

		//witness_api
		//https://github.com/VIZ-Blockchain/viz-cpp-node/blob/master/plugins/witness_api/plugin.cpp
		'get_active_witnesses'=>'witness_api',
		'get_witness_by_account'=>'witness_api',
		'get_witness_count'=>'witness_api',
		'get_witness_schedule'=>'witness_api',
		'get_witnesses'=>'witness_api',
		'get_witnesses_by_counted_vote'=>'witness_api',
		'get_witnesses_by_vote'=>'witness_api',
		'lookup_witness_accounts'=>'witness_api',
	);
	function get_url($url,$post=array(),$debug=false){
		$this->last_url=$url;
		$method='GET';
		if($post){
			$method='POST';
		}
		preg_match('#://(.*)/#iUs',$url.'/',$stock);
		preg_match('#://'.$stock[1].'/(.*)$#iUs',$url,$stock2);
		$host=$stock[1];
		$use_port=false;
		if(false!==strpos($host,':')){
			$use_port=intval(substr($host,strpos($host,':')+1));
			$host=substr($host,0,strpos($host,':'));
		}
		$path=$stock2[1];

		$content_type='application/json';
		if(is_array($post)){
			$post_str_arr=array();
			foreach($post as $k=>$v){
				$post_str_arr[]=urlencode($k).'='.$v;
			}
			$post=implode('&',$post_str_arr);
			$content_type='application/x-www-form-urlencoded';
		}
		$request=$method." /".$path." HTTP/1.1\r\n";
		$request.="Host: ".$host."\r\n";
		$request.="Connection: close\r\n";
		if($this->accept_gzip){
			$request.="Accept-Encoding: gzip\r\n";
		}
		$request.="Content-Type: ".$content_type."\r\n";
		foreach($this->header_arr as $k=>$v){
			$request.=$k.": ".$v."\r\n";
		}
		$request.="Content-Length: ".strlen($post)."\r\n\r\n";
		$request.=$post;
		$request.="\r\n";
		$request.="\r\n";
		if($debug||$this->debug){
			$this->request_arr[]=$request;
		}
		$result='';
		$port=80;

		$socket_connect=$host;

		if(!isset($this->host_ip[$socket_connect])){
			$this->host_ip[$socket_connect]=gethostbyname($host);//get ip by host and cache it
		}

		$socket_connect=$this->host_ip[$socket_connect];

		if(false!==strpos($url,'https://')){
			$port=443;
			$socket_connect='ssl://'.$socket_connect;
		}
		else
		if(false!==strpos($url,'wss://')){
			$port=443;
			$socket_connect='ssl://'.$socket_connect;
		}
		if(false!==$use_port){
			$port=$use_port;
		}
		$socket_connect.=':'.$port;

		$context=stream_context_create();
		stream_context_set_option($context,'ssl','peer_name',$host);
		if(!$this->check_ssl){
			stream_context_set_option($context,'ssl','verify_peer',false);
			stream_context_set_option($context,'ssl','verify_peer_name',false);
			stream_context_set_option($context,'ssl','allow_self_signed',true);
		}
		if($sock=stream_socket_client($socket_connect,$errno,$errstr,2,STREAM_CLIENT_CONNECT,$context)){
			stream_set_timeout($sock,$this->read_timeout);
			fwrite($sock,$request,strlen($request));
			$read_timeout=microtime(true)+$this->read_timeout;
			while(!feof($sock)){
				$result.=fread($sock,1024);
				if(microtime(true)>$read_timeout){
					break;
				}
			}
			fclose($sock);
			if(microtime(true)>$read_timeout){
				if($debug||$this->debug){
					print '!!! SOCKET TIMED OUT '.$this->read_timeout.' SEC ['.date('d.m.Y H:i:s').']'.PHP_EOL;
					print_r($request);
				}
				return false;
			}
		}
		else{
			return false;
		}
		if($debug||$this->debug){
			$this->result_arr[]=$result;
		}
		return $result;
	}
	function check_redirect($data,$post=array(),$debug=false){
		$headers=mb_substr($data,0,mb_strpos($data,"\r\n\r\n"));
		preg_match('~Location:(.[^\n]*)~is',$headers,$m);
		if(isset($m[1])){
			$url=trim($m[1]);
			if($url){
				return $this->get_url($url,$post,$debug||$this->debug);
			}
		}
		return $data;
	}
	function clear_chunked($data) {
		$arr=explode("\r\n",$data);
		$i=0;
		$count=count($arr);
		while($i<=$count){
			if(strlen($arr[$i])<=5){
				unset($arr[$i]);
				$i+=2;
			}
			else{
				$arr[$i]="\r\n".$arr[$i];
				$i+=1;
			}
		}
		$result=implode('',$arr);
		return $result;
	}
	function parse_web_result($data){
		$headers=mb_substr($data,0,mb_strpos($data,"\r\n\r\n"));
		$clear_result=mb_substr($data,mb_strpos($data,"\r\n\r\n")+4);
		if(false!==strpos($headers,'Transfer-Encoding: chunked')){$clear_result=$this->clear_chunked($clear_result);}
		if(false!==strpos($headers,'Content-Encoding: gzip')){$clear_result=gzdecode($clear_result);}
		return array($headers,$clear_result);
	}
	function raw_method($method,$params){
		if(!isset($this->api[$method])){
			return false;
		}
		$result='{"id":'.$this->post_num.',"jsonrpc":"2.0","method":"call","params":["'.$this->api[$method].'","'.$method.'",['.$params.']]}';
		if($this->increase_post_num){
			$this->post_num++;
		}
		return $result;
	}
	function build_method($method,$params){
		$params_arr=array();
		$params_str='';
		if(count($params)>0){
			foreach($params as $k => $v){
				if(is_array($v)){
					if(isset($v['raw'])){
						unset($v['raw']);
						$params_arr[]=json_encode($v);
						break;
					}
					$v='["'.implode('","',$v).'"]';
				}
				else{
					if(is_bool($v)){
						if($v){
							$v='true';
						}
						else{
							$v='false';
						}
					}
					else{
						if(!is_int($v)){
							$v='"'.$v.'"';
						}
					}
				}
				if(is_int($k)){
					$params_arr[]=$v;
				}
				else{
					$params_arr[]='"'.$k.'":'.$v.'';
				}
			}
			$params_str=implode(',',$params_arr);
		}
		$result='{"id":'.$this->post_num.',"jsonrpc":"2.0","method":"call","params":["'.$this->api[$method].'","'.$method.'",['.(($params)?$params_str:'').']]}';
		if($this->increase_post_num){
			$this->post_num++;
		}
		return $result;
	}
	function execute_method($method,$params=array(),$debug=false){
		if(!is_array($params)){
			$jsonrpc_query=$this->raw_method($method,$params);
		}
		else{
			$jsonrpc_query=$this->build_method($method,$params);
		}
		if(false===$jsonrpc_query){//not actual api method
			$result=false;
		}
		else{
			$result=$this->get_url($this->endpoint,$jsonrpc_query,$debug||$this->debug);
		}
		if(false!==$result){
			list($header,$result)=$this->parse_web_result($result);
			if($debug||$this->debug){
				print PHP_EOL.'<!-- ENDPOINT: '.$this->endpoint.' -->'.PHP_EOL;
				print '<!-- QUERY: '.$jsonrpc_query.' -->'.PHP_EOL;
				print '<!-- HEADER: '.$header.' -->'.PHP_EOL;
				print '<!-- RESULT: '.$result.' -->'.PHP_EOL;
			}
			if(false===strpos($header," 200 OK\r\n")){//check server status code
				return false;//server code error
			}
			$result_arr=json_decode($result,true);
			if($this->return_only_result){//simple mode
				if(isset($result_arr['result'])){
					return $result_arr['result'];
				}
				else{
					//error message in response, result not exist
					return false;
				}
			}
			else{//extended mode with error message
				return $result_arr;
			}
		}
		else{
			//can be not existed api method, socket error, server timeout
			return false;
		}
	}
}