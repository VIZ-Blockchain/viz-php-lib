<?php
namespace VIZ;

use DateTime;
use DateTimeZone;
use VIZ\JsonRPC;

/**
 * HF15 agent access — read side (op-id 105, `set_agent_permission`).
 *
 * A VIZ key is no longer only an authority key. A principal may register up to
 * CHAIN_AGENT_MAX_PER_ACCOUNT live agents: a label plus a public key that may sign an explicit,
 * frozen list of that principal's operations, optionally scoped to off-chain addons, optionally
 * expiring (epoch `1970-01-01T00:00:00` = perpetual).
 *
 * The old check — "get_account and look at active_authority.key_auths" — silently rejects agent
 * keys, because an agent is not an account and never appears in any authority. The recipe now is
 * authority OR agents, and `check()` does both in one call.
 *
 * Rules mirrored from the node (`agent_evaluator.cpp::usable_agent_row`), which is the source of
 * truth here:
 *  - an agent answers direct active/regular requirements only, and only for operations on its list;
 *    the master authority is never satisfied by an agent, nor is a nested/other authority;
 *  - a row is unusable when expired or when its list holds a never-delegable name;
 *  - one key belongs to one agent of a principal; a key change of master/active, a recovery or a
 *    sale wipes every agent of the principal.
 */
class Agent{
	/** Mirrors the node's never_delegable_operation_names(): a row holding one of these grants nothing. */
	const NEVER_DELEGABLE=['set_agent_permission','proposal_update','account_update','recover_account','change_recovery_account','set_account_price','set_subaccount_price','target_account_sale'];

	/** Authority roles a key can belong to, most privileged first. Agents answer for none of them. */
	const ROLES=['master','active','regular'];

	public $jsonrpc;

	/** $node — an endpoint, or an existing JsonRPC to share (Auth does this, so both read the same node). */
	function __construct($node,$debug=false){
		$this->jsonrpc=($node instanceof JsonRPC)?$node:new JsonRPC($node,$debug);
	}

	/** Agent rows of a principal as the node returns them (expired rows included; it sweeps them lazily). */
	function get($account){
		$rows=$this->jsonrpc->execute_method('get_agent_permissions',[$account]);
		if(!is_array($rows)){
			return false;//unknown method (node without HF15) or a failed request
		}
		return $rows;
	}

	/** First live agent row signed by $key, or false. */
	function find($account,$key){
		$rows=$this->get($account);
		if(false===$rows){
			return false;
		}
		foreach($rows as $row){
			if(!is_array($row)||!isset($row['agent_key'])){
				continue;
			}
			if(!self::is_expired($row)&&self::key_equals($row['agent_key'],$key)){
				return $row;
			}
		}
		return false;
	}

	/** True when $key is a live agent of $account — and, when $operation is given, may sign it. */
	function has($account,$key,$operation=null){
		$row=$this->find($account,$key);
		if(false===$row){
			return false;
		}
		if(null===$operation||''===$operation){
			return true;
		}
		return self::grants($row,$operation);
	}

	/**
	 * Unified check: may $key act for $account? Answers for the authority roles first, then for the
	 * agents. Returns an array:
	 *  allowed   — bool
	 *  source    — 'authority' | 'agent' | 'none'
	 *  role      — authority role the key matched, when source is 'authority', else false
	 *  agent     — the matched agent row, when source is 'agent', else false
	 *  operation — the operation the caller asked about (null = any)
	 *
	 * With $operation = null an authority key is answered by membership alone, while an agent is
	 * only answered as "this is a live agent key": its grants are per-operation, so pass the wire
	 * name (`transfer`, `pm_place_bet`, ...) to check a specific operation.
	 * A key that is only in a nested authority (account_auths) is NOT resolved here — use
	 * `verify_authority` with a signed transaction for the full recursive check.
	 */
	function check($account,$key,$operation=null){
		$result=['allowed'=>false,'source'=>'none','role'=>false,'agent'=>false,'operation'=>$operation];

		$account_arr=$this->jsonrpc->execute_method('get_account',[$account,'']);
		if(is_array($account_arr)&&isset($account_arr['name'])&&$account==$account_arr['name']){
			foreach(self::ROLES as $role){
				$authority=isset($account_arr[$role.'_authority'])?$account_arr[$role.'_authority']:false;
				if(self::in_authority($authority,$key)){
					$result['allowed']=true;
					$result['source']='authority';
					$result['role']=$role;
					return $result;
				}
			}
		}

		$row=$this->find($account,$key);
		if(false!==$row&&(null===$operation||''===$operation||self::grants($row,$operation))){
			$result['allowed']=true;
			$result['source']='agent';
			$result['agent']=$row;
		}
		return $result;
	}

	/** True when the agent row is alive at head block time. The node's own flag wins; the timestamp is the fallback. */
	static function is_expired($row){
		if(isset($row['expired'])){
			return (bool)$row['expired'];
		}
		if(!isset($row['expiration'])){
			return false;
		}
		//the chain reports UTC without a 'Z', so parse it as UTC explicitly
		$time=strtotime($row['expiration'].' UTC');
		if(false===$time||$time<=0){
			return false;//epoch = perpetual; an unparsable value is not a reason to deny the key
		}
		return $time<=time();
	}

	/** True when the row's list covers $operation and the row is not poisoned by a never-delegable name. */
	static function grants($row,$operation){
		if(!isset($row['operations'])||!is_array($row['operations'])){
			return false;
		}
		if(!in_array($operation,$row['operations'],true)){
			return false;
		}
		return !self::poisoned($row);
	}

	/**
	 * True when the row's list holds a never-delegable name. The node refuses such a row WHOLE
	 * (`usable_agent_row` bails on the first forbidden name), it does not merely drop that name —
	 * so the row grants nothing at all, including its off-chain addons.
	 */
	static function poisoned($row){
		if(!isset($row['operations'])||!is_array($row['operations'])){
			return false;
		}
		foreach($row['operations'] as $name){
			if(in_array($name,self::NEVER_DELEGABLE,true)){
				return true;
			}
		}
		return false;
	}

	/**
	 * True when the row is scoped to $scope. Addons are opaque off-chain scopes: the node stores
	 * them and never interprets them, so a service matches its OWN scope here, by exact string
	 * (case-sensitive — the chain does not normalise them for us).
	 */
	static function addon_matches($row,$scope){
		if(!isset($row['addons'])||!is_array($row['addons'])){
			return false;
		}
		return in_array($scope,$row['addons'],true);
	}

	/** True when $key is a member of the authority with enough weight to meet its threshold. */
	static function in_authority($authority,$key){
		if(!is_array($authority)||!isset($authority['key_auths'])||!is_array($authority['key_auths'])){
			return false;
		}
		$threshold=isset($authority['weight_threshold'])?(int)$authority['weight_threshold']:0;
		$weight=0;
		foreach($authority['key_auths'] as $member){
			if(isset($member[0])&&isset($member[1])&&self::key_equals($member[0],$key)){
				$weight+=(int)$member[1];
			}
		}
		return $weight>=$threshold&&$weight>0;
	}

	/** Compare two encoded keys, tolerating whitespace, a missing 'VIZ' prefix and base58 spelling differences. */
	static function key_equals($a,$b){
		if(!is_string($a)||!is_string($b)){
			return false;
		}
		if($a===$b){
			return true;
		}
		return self::normalize_key($a)===self::normalize_key($b);
	}

	/** Canonical `VIZ...` form of an encoded public key; the trimmed input when it cannot be parsed. */
	static function normalize_key($key){
		$original=trim((string)$key);
		if(''===$original){
			return '';
		}
		$encoded=0===strpos($original,'VIZ')?$original:'VIZ'.$original;
		$decoded=new Key();
		if($decoded->import_public($encoded)){
			return $decoded->encode();
		}
		return $original;//unparsable: hand it back verbatim so the caller still compares something concrete
	}
}
