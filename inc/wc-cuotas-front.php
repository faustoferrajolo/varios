<?php

class WC_Cuotas_Front {

	function __construct(){

		$pos = get_option('wc_cuotas_setting_calculator_pos', 15 );

		add_action('woocommerce_single_product_summary',		array( $this, 'action__product_cuotas'), $pos );
		add_action('wp_enqueue_scripts',						array( $this, 'action__enqueue') );
		add_action('wp_ajax_wc_cuotas_cuota',					array( $this, 'ajax__cuota') );
		add_action('wp_ajax_nopriv_wc_cuotas_cuota',			array( $this, 'ajax__cuota') );
		add_action('woocommerce_checkout_update_order_review',	array( $this, 'action__update_order') );
		add_action('woocommerce_cart_calculate_fees',			array( $this, 'action__calculate_fees') );
		add_action('woocommerce_checkout_update_order_meta',	array( $this, 'action__set_gateway_cuotas'), 20, 2 );
		add_action('wc_cuotas_gateway',							array( $this, 'action__gateway_cuotas') );
		add_action('wc_cuotas_gateway_title',					array( $this, 'action__gateway_cuotas_title') );

		add_filter('woocommerce_get_price_html',				array( $this, 'filter__price_html' ), 999, 2 );
		add_filter('woocommerce_locate_template',				array( $this, 'filter__locate_gateway_template'), 20, 3 );

	}

	function filter__price_html( $html, $product ){

		if(is_singular('product')){
			return $html;
		}
		$provider_id = $this->get_preferred_provider();
		$gateway_id  = $this->get_preferred_gateway();
		if(empty($provider_id) || empty($gateway_id)){
			return $html;
		}
		$data = WC_Cuotas::getCuotaData( $gateway_id, $provider_id );
		if(empty($data)){
			return $html;
		}
		$price = $product->get_price();
		$cprice = $price * $data['calc'];
		$cuota = $cprice / $data['num'];
		$cuota = wc_price($cuota);
		$html.= " <span class='cuotas'>o {$data['num']} cuotas de {$cuota}</span>";
		return $html;
	}

	function action__gateway_cuotas_title( $gateway ){
		$data = WC_Cuotas::getCuotaSettings();

		if(empty($data) || !isset($data[$gateway->id])){
			return;
		}
		$max = 0;
		foreach($data[$gateway->id]['data'] as $pid => $cuotas){
			$cuotas = array_keys($cuotas);
			$cuotas[] = $max;
			$max = max($cuotas);
		}
		if(!empty($max)){
			echo "<br/><small>Paga en hasta {$max} cuotas</small>";
		}
	}

	function filter__locate_gateway_template( $template, $template_name, $template_path ){
		global $woocommerce;
		$_template = $template;
		if ( ! $template_path ) {
		  $template_path = $woocommerce->template_url;
		}

		$plugin_path  = WC_CUOTAS_DIR . '/woocommerce/';

		// Look within passed path within the theme - this is priority

		$template = locate_template(
			array(
			  $template_path . $template_name,
			  $template_name
			)
		);

		// Modification: Get the template from this plugin, if it exists

		if ( ! $template && file_exists( $plugin_path . $template_name ) ) {
		  $template = $plugin_path . $template_name;
	    }

		// Use default template

		if ( ! $template ){
		  $template = $_template;
		}
		// Return what we found
		return $template;
	}

	function action__gateway_cuotas( $gateway ) {
		$id = $gateway->id;
		$data = WC_Cuotas::getCuotaSettings();
		if(empty($data) || !isset($data[$id])){
			return "";
		}
		$chosen = WC()->session->get('chosen_payment_method');
		$data = $data[$id];
		if($chosen == $id){
			$preference = WC()->session->get('wc_cuotas_checkout');
		}
		if(empty($preference)){
			$preference = array("provider" => "", "num" => "");
		}
		$data['preference'] = $preference;
		if(isset($data['data'][0])){
			$preference['provider'] = 0;
			$providers = false;
		}else{
			$providers = get_terms('wc_cuotas_provider',array(
				"hide_empty" => false,
				"include" => array_keys($data['data'])
			));
		}
		$cuotas = get_option('wc_cuotas');
		require( WC_CUOTAS_DIR . '/views/checkout-cuotas.php' );
	}

	function action__update_order( $post_data ) {
		parse_str($post_data,$data);
		$gateway = $_POST['payment_method'];
		$provider_key = "wc_cuotas_{$gateway}_provider";
		$num_key = "wc_cuotas_{$gateway}_num";
		$pref = array("provider" => "", "num" => "");
		if(!empty($data[$provider_key])){
			$pref["provider"] = $data[$provider_key];
		}
		if(!empty($data[$num_key])){
			$pref["num"] = $data[$num_key];
		}
		WC()->session->set('wc_cuotas_checkout',$pref);
	}

	function action__calculate_fees( $cart ) {
		$settings = WC_Cuotas::getCuotaSettings();
		if(empty($settings)){
			return;
		}
		$method = WC()->session->get('chosen_payment_method');
		if(!isset($settings[$method]) || empty($settings[$method]['apply_to_order'])){
			return;
		}
		$pref = WC()->session->get('wc_cuotas_checkout');
		if(!empty($pref) && !empty($pref['provider']) && !empty($pref['num'])){
			$settings = WC_Cuotas::getCuotaData($method,$pref['provider'],$pref['num']);
			$total = $cart->cart_contents_total + $cart->shipping_total;
			$fee = $total * ( $settings['calc'] - 1);
			$cart->add_fee('Costos de financiación',$fee);
		}
	}

	function action__enqueue(){
		wp_enqueue_style('wc-cuotas',WC_CUOTAS_URL . 'assets/css/wc-cuotas.css' );
		$ext = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? ".js" : ".min.js";
		if(is_singular('product')){
			wp_enqueue_script('wc-cuotas', WC_CUOTAS_URL . 'assets/js/wc-cuotas'.$ext );
		}elseif(is_checkout()){
			wp_enqueue_script('wc-cuotas-checkout', WC_CUOTAS_URL . 'assets/js/wc-cuotas-checkout'.$ext );
		}

	}

	function action__product_cuotas(){
		global $product;
		$settings = WC_Cuotas::getCuotaSettings();
		if(empty($settings)){
			return;
		}

		$providers = get_terms('wc_cuotas_provider',array(
			"hide_empty" => false
		));
		$cuotas = get_option('wc_cuotas');
		$data = esc_attr( json_encode( $settings ));
		$title = get_option('wc_cuotas_setting_calculator_title','Calculá tus cuotas');
		echo "<div class='wc-cuotas-calculator' data-cuotas='{$data}'>";
		echo "<h4>{$title}</h4>";
		if(count($settings) > 1){
			echo "<div class='wc-cuotas-method wc-cuotas-field'><label>Metodo de pago</label>";
			echo "<select data-select='method'><option value=''>Elegi un metodo de pago</option>";
			foreach($settings as $gid => $row){
				echo "<option value='{$gid}'>{$row['title']}</option>";
			}
			echo "</select></div>";
		}else{
			echo "<div class='wc-cuotas-method wc-cuotas-field'><label>Método de pago</label>";
			$row = array_shift($settings);
			echo "<span>{$row['title']}</span>";
			echo "</div>";
		}
		echo "<div class='wc-cuotas-provider wc-cuotas-field'><label>Medio de pago</label>";
		echo "<select data-select='provider'><option value=''>Elegi un medio de pago</option>";
		foreach($providers as $term){
			echo "<option value='{$term->term_id}'>{$term->name}</option>";
		}
		echo "</select></div>";
		echo "<div class='wc-cuotas-num wc-cuotas-field'><label>Cantidad de cuotas</label>";
		echo "<select data-select='num'>";
		foreach($cuotas as $cuota){
			echo "<option value='{$cuota}'>{$cuota}</option>";
		}
		echo "</select></div>";
		echo "<div class='wc-cuotas-cuota' style='display:none;font-size:80%'>";
		echo "<div class='cuota' style='font-size: 120%'><strong><span></span></strong></div>";
		echo "<div class='total'>Precio Financiado Total: <span></span></div>";
		echo "<div class='tea'>TEA: <span></span></div>";
		echo "<div class='cft' style='font-size:225%'>CFT: <span></span></div>";
		echo "</div>";
		echo "</div>";

	}

	function action__set_gateway_cuotas( $order_id, $post_data ) {
		$preference = WC()->session->get('wc_cuotas_checkout');

		if(empty($preference) || empty($preference['num']) || empty($post_data['payment_method'])){
			return;
		}
		$method = $post_data['payment_method'];
		$settings = WC_Cuotas::getCuotaSettings();

		if(!isset($settings[$method]) || empty($settings[$method]['apply_to_order'])){
			return;
		}
		update_post_meta($order_id,"_wc_cuotas_num",$preference['num']);
		if(!empty($preference['provider'])){
			$provider = get_term_by('id',$preference['provider'],"wc_cuotas_provider");
			update_post_meta($order_id,"_wc_cuotas_provider",$provider->name);
		}else{
			$preference['provider'] = 0;
		}

		$data = WC_Cuotas::getCuotaData($method,$preference['provider'],$preference['num']);
		update_post_meta($order_id,"_wc_cuotas_data",$data);
		$order = new WC_Order($order_id);
		$cuota = $order->get_total() / $preference['num'];
		update_post_meta($order_id,"_wc_cuotas_cuota",$cuota);
	}

	function ajax__cuota(){
		$data = $_GET['data'];
		if(!empty($data['vid'])){
			$product = new WC_Product_Variation( $data['vid'] );
		}else{
			$product = new WC_Product( $data['pid'] );
		}
		$price = $product->get_price( false );
		$total = $price * $data['calc'];
		$cuota = $total / $data['num'];
		wp_send_json(array(
			"cuota" => "{$data['num']} cuotas de ".wc_price( $cuota ),
			"tea" => number_format( $data['tea'], 2, ",", ".")."%",
			"cft" => number_format( $data['cft'], 2, ",", ".")."%",
			"total" => wc_price( $total )
		));
		exit();
	}

	function get_preferred_gateway(){
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if( WC()->session && $gid = WC()->session->get('wc_cuotas_gateway') ){
			return $gid;
		}
		if(!empty($gateways)){
			return array_keys($gateways)[0];
		}else{
			return false;
		}
	}

	function get_preferred_provider(){
		if(WC()->session && $pid = WC()->session->get('wc_cuotas_provider')){
			return $pid;
		}

		$pid = get_option('wc_cuotas_default_provider');
		if(!empty($pid)){
			return $pid;
		}
		$terms = get_terms('wc_cuotas_provider',array(
			"num" => 1
		));

		if(!empty($terms)){
			return $terms[0]->term_id;
		}
		return false;
	}



}

add_action('plugins_loaded',function(){
	new WC_Cuotas_Front();
});
