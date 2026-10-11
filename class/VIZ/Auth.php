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
	public $agent;
	public $addon=null;
	public $fix_server_timezone=false;
	function __construct($node,$domain,$action='auth',$authority='regular',$range=60,$addon=null){
		$this->jsonrpc=new JsonRPC($node);
		$this->key=new Key();
		$this->agent=new Agent($this->jsonrpc);
		$this->domain=$domain;
		$this->action=$action;
		$this->authority=$authority;
		$this->range=$range;
		$this->addon=$addon;
	}
	function check($data,$signature){
		$data_arr=explode(':',$data);//domain:action:account:authority:unixtime:nonce
		if(count($data_arr)<6){
			return false;//malformed payload — refuse before reading indexes that may not exist
		}

		$time=time();
		if($this->fix_server_timezone){
			$time-=(new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime());
		}
		$start_time=$time - $this->range;
		$end_time=$time + $this->range;

		$search_key=$this->key->recover_public_key($data,$signature);
		if(!$search_key){
			return false;
		}
		if($this->domain!=$data_arr[0]){
			return false;
		}
		if($this->action!=$data_arr[1]){
			return false;
		}
		if($this->authority!=$data_arr[3]){
			return false;
		}
		if((int)$data_arr[4]>$end_time){
			return false;
		}
		if((int)$data_arr[4]<$start_time){
			return false;
		}

		$check_account=$data_arr[2];

		//1) the key is a key of the demanded authority of the account (as before HF15)
		$account_arr=$this->jsonrpc->execute_method('get_account',[$check_account,'']);
		if(is_array($account_arr)&&isset($account_arr['name'])&&$check_account==$account_arr['name']){
			$authority=isset($account_arr[$this->authority.'_authority'])?$account_arr[$this->authority.'_authority']:false;
			if(Agent::in_authority($authority,$search_key)){
				return true;
			}
		}

		//2) the key is an agent of the account, scoped to our addon (HF15)
		return $this->check_agent($check_account,$search_key);
	}

	/**
	 * HF15 agent branch for domain auth. An agent is not an account and never appears in any
	 * authority, so branch 1 can never see it — that is why this branch exists at all.
	 *
	 * The grant consulted here is the ADDON: domain auth is not a chain operation, so the row's
	 * operation list is not what a service matches on (addons are off-chain scopes the node stores
	 * and never interprets — the service matches its OWN scope). The usual agent gates still hold:
	 * an agent never answers a master requirement, the row must be alive, and a row the node refuses
	 * as poisoned grants nothing at all — including its addons.
	 *
	 * With no addon configured the branch is off and `check()` behaves exactly as it did before HF15.
	 */
	function check_agent($account,$key){
		if(null===$this->addon||''===$this->addon){
			return false;
		}
		if('master'===$this->authority){
			return false;
		}
		$row=$this->agent->find($account,$key);
		if(false===$row){
			return false;
		}
		if(Agent::poisoned($row)){
			return false;
		}
		return Agent::addon_matches($row,$this->addon);
	}
}