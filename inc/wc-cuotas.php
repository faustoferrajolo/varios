<?php

class WC_Cuotas {

	public static $settings;

	function __construct() {
		add_action('init',						array( $this, 'action__init') );
		add_action('woocommerce_loaded',		array( $this, 'action__loaded'));

	}

	function action__init(){
		register_taxonomy('wc_cuotas_provider',array(
			"show_ui" => false,
			"public" => false,
			"hierarchical" => false,
			"rewrite" => false
		));
	}

	function action__loaded(){
		self::$settings = self::getCuotaSettings();
	}

	static function getCuotaSettings(){
		$providers = get_terms('wc_cuotas_provider',array(
			"hide_empty" => false
		));
		$cuotas = get_option('wc_cuotas');
		$pids = array();
		foreach($providers as $provider){
			$pids[] = $provider->term_id;
		}
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$cuotas = get_option('wc_cuotas');
		$settings = array();
		foreach($gateways as $gid => $gw){
			$data = get_option('wc_cuotas_gateway_'.$gid);
			if(empty($data)){
				continue;
			}
			$data['title'] = $gw->method_title;
			$data['data'] = self::_filterData($data['data'],$pids,$cuotas);
			if(empty($data['data'])){
				continue;
			}
			$settings[$gid] = $data;
		}
		return $settings;
	}

	static function _filterData($data,$providers,$cuotas){
		$result = array();
		foreach($data as $pid => $cdata){
			if($pid && !in_array($pid,$providers)){
				continue;
			}elseif(!$pid){
				if(count($data) > 1){
					continue;
				}
			}
			$row = array();
			foreach($cdata as $num => $pdata){
				if(!in_array($num,$cuotas)){
					continue;
				}
				if(empty($pdata['calc']) && isset($data[0][$num])){
					$pdata = $data[0][$num];
				}
				if(!empty($pdata['calc'])){
					$row[$num] = $pdata;
				}
			}
			if(!empty($row)){
				$result[$pid] = $row;
			}
		}
		return $result;
	}

	static function getCuotaData($gateway_id,$provider_id = 0, $num_cuotas = 0 ) {
		$cuotas = get_option('wc_cuotas');
		if(empty($num_cuotas)){
			$num_cuotas = get_option('wc_cuotas_default');
			if(empty($num_cuotas)){
				$num_cuotas = $cuotas[0];
			}
		}
		$gwdata = get_option('wc_cuotas_gateway_'.$gateway_id);
		if(empty($gwdata)){
			return false;
		}else{
			$data = $gwdata['data'];
			$cdata = false;
			if(!isset($data[$provider_id])){
				return false;
			}elseif(!isset($data[$provider_id][$num_cuotas])){
				return false;
			}else{
				$cdata = $data[$provider_id][$num_cuotas];
				if(empty($cdata['calc'])){
					$cdata = $data[0][$num_cuotas];
				}
				if(!empty($cdata['calc'])){
					$cdata['num'] = $num_cuotas;
					return $cdata;
				}
			}
		}
		return false;
	}

}

add_action('plugins_loaded',function(){
	new WC_Cuotas();
});
