<?php
namespace VIZ;

use DateTime;
use DateTimeZone;
use VIZ\Key;
use VIZ\JsonRPC;

class Auth{
	public $jsonrpc;
	public $key;
	public $range;
	public $domain;
	public $authority;
	public $action;
	public $fix_server_timezone=false;
	function __construct($node,$domain,$action='auth',$authority='regular',$range=60){
		$this->jsonrpc=new JsonRPC($node);
		$this->key=new Key();
		$this->domain=$domain;
		$this->action=$action;
		$this->authority=$authority;
		$this->range=$range;
	}
	function check($data,$signature){
		$data_arr=explode(':',$data);//domain:action:account:authority:unixtime:nonce

		$time=time();
		if($this->fix_server_timezone){
			$time-=(new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime());
		}
		$start_time=$time - $this->range;
		$end_time=$time + $this->range;

		$search_key=$this->key->recover_public_key($data,$signature);

		if($search_key){
			if($this->domain==$data_arr[0]){
				if($this->action==$data_arr[1]){
					if($this->authority==$data_arr[3]){
						if((int)$data_arr[4]<=$end_time){
							if((int)$data_arr[4]>=$start_time){
								$check_account=$data_arr[2];
								$account_arr=$this->jsonrpc->execute_method('get_account',[$check_account,'']);
								if(false!==$account_arr){
									if($check_account==$account_arr['name']){
										$weight_threshold=$account_arr[$this->authority.'_authority']['weight_threshold'];
										$summary_weight=0;
										foreach($account_arr[$this->authority.'_authority']['key_auths'] as $authority){
											if($search_key==$authority[0]){
												$summary_weight+=(int)$authority[1];
											}
										}
										if($summary_weight>=$weight_threshold){
											return true;
										}
										else{
											return false;
										}
									}
								}
							}
						}
					}
				}
			}
		}
		return false;
	}
}