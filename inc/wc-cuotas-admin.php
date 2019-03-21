<?php

class WC_Cuotas_Admin {

	function __construct(){

		add_action('admin_enqueue_scripts',								array( $this, 'action__admin_enqueue') );

		add_action('woocommerce_sections_cuotas',						array( $this, 'action__output_sections' ) );
		add_action('woocommerce_settings_tabs_cuotas',					array( $this, 'action__settings_tab') );
		add_action('woocommerce_update_options_cuotas', 				array( $this, 'action__save_settings') );
		add_action('woocommerce_admin_order_data_after_order_details', 	array( $this, 'action__show_cuotas_meta') );

		add_action('wp_ajax_wc_cuotas_add_provider',					array( $this, 'ajax__add_provider') );

		add_filter('woocommerce_settings_tabs_array',					array( $this, 'filter__settings_tabs' ), 99);
	}

	function filter__settings_tabs( $tabs ) {
		$tabs['cuotas'] = "Calculador de Cuotas";
		return $tabs;
	}

	function action__admin_enqueue(){
		wp_enqueue_style('wc-cuotas-admin', WC_CUOTAS_URL . '/assets/css/admin.css');
	}


	function action__settings_tab(){
		global $current_section;
		if(preg_match('/^gateway_/',$current_section)){
			$this->output__gateway_tab();
		}elseif($current_section == "cuotas"){
			$this->output__cuotas_tab();
		}elseif($current_section == "providers"){
			$this->output__providers_tab();
		}else{
			$current_section = "settings";
			$this->output__settings_tab();
		}

	}

	function action__save_settings(){
		global $current_section;
		//woocommerce_update_options( $this->get_settings( $current_section ) );
		if(empty($current_section)){
			$current_section = "settings";
		}
		if($current_section == "providers"){
			$providers = (!empty($_POST['wc_cuotas_providers'])) ? $_POST['wc_cuotas_providers'] : array();
			foreach($providers as $id => $name){
				if(empty($name)){
					wp_delete_term( $id, "wc_cuotas_provider");
				}else{
					wp_update_term($id,"wc_cuotas_provider",array(
						"name" => $name
					));
				}
			}
			if(!empty($_POST['wc_cuotas_default_provider'])){
				update_option('wc_cuotas_default_provider',$_POST['wc_cuotas_default_provider']);
			}else{
				delete_option('wc_cuotas_default_provider');
			}
		}elseif($current_section == "cuotas"){
			$cuotas = (!empty($_POST['wc_cuotas'])) ? $_POST['wc_cuotas'] : array();
			sort($cuotas);
			update_option('wc_cuotas',$cuotas);
			if(!empty($_POST['wc_cuotas_default'])){
				update_option('wc_cuotas_default',$_POST['wc_cuotas_default']);
			}else{
				delete_option('wc_cuotas_default');
			}
		}elseif(preg_match("/^gateway_/",$current_section)){
			$name = "wc_cuotas_".$current_section;


			if(!empty($_POST[$name])){
				$data = $_POST[$name];
				$math = new WC_Cuotas_Math();
				foreach($data['data'] as $provider_id => $cuota_data ) {
					foreach( $cuota_data as $num_cuotas => &$cdata ) {
						if($cdata['cft'] == 0) {
							$cata['calc'] = 1;
						}elseif($cdata['cft']!="") {
							$math->setInterest( $cdata['cft'] );
							$math->setInstallments( $num_cuotas );
							$math->calc();
							$cdata['calc'] = $math->factor;
						}
					}
					$data['data'][$provider_id] = $cuota_data;
				}
				update_option($name,$data);
			}else{
				delete_option($name);
			}
		}elseif($current_section == "settings"){
			$settings = stripslashes_deep( $_POST['wc_cuotas_settings'] );
			foreach($settings as $key => $title){
				update_option('wc_cuotas_setting_'.$key,$title);
			}
		}
	}

	private function output__gateway_tab(){
		global $current_section;
		$providers = get_terms('wc_cuotas_provider',array(
			"hide_empty" => false
		));
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$id = str_replace("gateway_","",$current_section);
		foreach($gateways as $gid => $gateway){
			if($gid == $id){
				break;
			}
		}
		echo "<h2>Cuotas para {$gateway->method_title}</h2>";

		$cuotas = get_option('wc_cuotas');
		if(empty($providers)){
			echo "<p>Por favor define algunos proveedores de pago/tarjets.</p>";
		}elseif(empty($cuotas)){
			echo "<p>Por favor define las cuotas a aplicar en el sitio.</p>";
		}else{
			echo "<p>A continuación puede definir los valores para el calculo de cuotas para este metodo de pago. Puede llenar un valor base en la primera fila. Si algun medio de pago tiene valores que se desvian de este valor base entonces puede llenar los campos del medio de pago correspondiente. De lo contrario se aplicará el valor base.</p><p>Al llenar solo la fila de los valores bases no se reflejaran los medios de pago para este metodo de pago y se calcularan las cuotas en base de los valores llenados en la primera fila.</p>";
			$settings = get_option('wc_cuotas_'.$current_section);
			$name = "wc_cuotas_{$current_section}";
			echo "<div class='wc-cuotas-wrapper'><div class='wc-cuotas-inner'>";
			echo "<table class='wc-cuotas-table'>";
			echo "<thead>";
			echo "<tr><th rowspan='2'></th>";
			foreach($cuotas as $cuota){
				echo "<th colspan='3'>{$cuota} Cuota(s)</th>";
			}
			echo "</tr>";
			echo "<tr>";
			foreach($cuotas as $cuota){
				echo "<th>CFT</th>";
				echo "<th>TEA</th>";
				echo "<th>Recargo</th>";
			}
			echo "</tr>";
			echo "</thead>";
			echo "<tbody>";
			echo "<tr><th>Valor Base</th>";
			foreach($cuotas as $cuota){
				$data = $this->get_provider_cuota_settings( 0, $cuota, $settings );
				echo "<td colspan='3'><div><table class='wc-cuotas-provider-data'><tr>";
				echo "<td><input type='number' value='{$data['cft']}' min='0' step='0.01' name='{$name}[data][0][{$cuota}][cft]' /></td>";
				echo "<td><input type='number' value='{$data['tea']}' min='0' step='0.01' name='{$name}[data][0][{$cuota}][tea]' /></td>";
				echo "<td><input type='number'  value='{$data['charge']}' min='0' step='0.01' name='{$name}[data][0][{$cuota}][charge]' /></td>";
				echo "</tr></table></div></td>";
			}
			echo "</tr>";

			foreach($providers as $provider){
				echo "<tr><th>{$provider->name}</th>";
				foreach($cuotas as $cuota){
					$data = $this->get_provider_cuota_settings( $provider->term_id, $cuota, $settings );
					$class = (!empty($data['disabled'])) ? "disabled" : "";
					$checked = (!empty($data['disabled'])) ? "" : "CHECKED";
					echo "<td colspan='3'><div class='{$class}'>";
					echo "<span class='wc-cuotas-enable'>Habilitar</span>";
					echo "<span class='wc-cuotas-disable'>-</span>";
					echo "<table class='wc-cuotas-provider-data'><tr>";
					echo "<td><input type='number' value='{$data['cft']}' min='0' step='0.01' name='{$name}[data][{$provider->term_id}][{$cuota}][cft]' /></td>";
					echo "<td><input type='number' value='{$data['tea']}' min='0' step='0.01' name='{$name}[data][{$provider->term_id}][{$cuota}][tea]' /></td>";
					echo "<td><input type='number' value='{$data['charge']}' min='0' step='0.01' name='{$name}[data][{$provider->term_id}][{$cuota}][charge]' /></td>";
					echo "</tr></table></div></td>";
				}
				echo "</tr>";
			}
			echo "</tbody>";
			echo "</table>";
			echo "</div></div>";

			echo "<table class='form-table'>";

			echo "<tr><th>Aplicar a pedido</th><td>";
			$c = (!empty($settings['apply_to_order'])) ? "CHECKED" : "";
			echo "<label><input type='checkbox' name='{$name}[apply_to_order]' value='1' {$c} /> Aplicar a total del pedido</label>";
			echo "</td></tr>";
			$checkout_text = (!empty($settings['checkout_text'])) ? esc_attr( $settings['checkout_text']) : "";
			echo "<tr><th>Texto para checkout</th><td><input type='text' style='width:100%' name='{$name}[checkout_text]' value='{$checkout_text}' /></td></tr>";
			echo "</table>";
			?>
			<script type="text/javascript">
				jQuery(function($){
					var table = $('.wc-cuotas-table');
					var wrapper = $('.wc-cuotas-wrapper');
					var inner = $('.wc-cuotas-inner');
					table.on('click','.wc-cuotas-disable',function(){
						var d = $(this).closest('div');
						d.addClass('disabled');
						d.find('input[type="number"]').prop('disabled',true).val('');
					});
					table.on('click','.wc-cuotas-enable',function(){
						var d = $(this).closest('div');
						d.removeClass('disabled');
						d.find('input[type="number"]').prop('disabled',false);
					});
					var d = table.find('div.disabled');
					table.find('div.disabled .wc-cuotas-disable').trigger('click');
					var firstLabel = table.find('thead th').first();
					var labels = table.clone();
					labels.find('td').remove();
					labels.find('thead').html('<tr><th></th></tr>');
					labels.find('thead th').css('height',firstLabel.outerHeight());
					wrapper.append(labels);
					labels.addClass('labels');
				});
			</script>
			<?php
		}
	}

	private function get_provider_cuota_settings( $provider_id, $cuota, $settings ){
		$tpl = array(
			"cft" => "",
			"tea" => "",
			"calc" => "",
			"charge" => ""
		);
		if(isset($settings['data']) && isset($settings['data'][$provider_id]) && isset($settings['data'][$provider_id][$cuota])){
			$data = $settings['data'][$provider_id][$cuota];
		}elseif($provider_id){
			$data = $tpl;
			$data['disabled'] = true;
		}else{
			$data = $tpl;
		}
		$data = array_merge($tpl,$data);
		return $data;
	}

	private function output__settings_tab(){
		echo "<h2>Configuración Calculador de Cuotas</h2>";
		echo "<table class='form-table'>";
		$title = get_option('wc_cuotas_setting_calculator_title','Calculá tus cuotas');
		echo "<tr><th>Título Calculador de Cuotas</th><td><input type='text' style='width:100%' name='wc_cuotas_settings[calculator_title]' value='".esc_attr( $title )."' /></td></tr>";
		echo "<tr><th>Posición Calculador</th><td>";
		$pos = get_option('wc_cuotas_setting_calculator_pos',15);
		$options = array(
			15 => "Después del precio",
			25 => "Después del resumen",
			35 => "Después del botón",
			45 => "Después de los datos meta"
		);
		foreach($options as $val => $label){
			$c = ( $pos == $val ) ? "CHECKED" : "";
			echo "<div><label><input type='radio' name='wc_cuotas_settings[calculator_pos]' value='{$val}' {$c} /> {$label}</label></div>";
		}
		echo "</td></tr>";
		echo "</table>";
	}

	private function output__providers_tab(){
		$providers = get_terms("wc_cuotas_provider",array(
			"hide_empty" => false
		));
		echo "<h2>Medios de pago</h2>";
		echo "<ul class='wc-cuotas-list wc-cuotas-providers'>";
		foreach($providers as $provider){
			$this->output__provider_row( $provider );
		}
		if(empty($providers)){
			echo "<p class='no-providers'><em>No se han encontrado medios de pago</em></p>";
		}
		echo "</ul>";
		echo "<h3>Añadir un medio de pago</h3>";
		echo "<p><input type='text' /><button type='button' class='wc-cuotas-add-provider button button-primary button-small'>Añadir</button></p>";
		?>
		<script type="text/javascript">
			jQuery(function($){
				var list = $('.wc-cuotas-providers'),
				btn = $('.wc-cuotas-add-provider');

				list.on('click','a.remove',function(){
					$(this).closest('li').remove();
				});

				btn.on('click',function(){
					var input = $(this).closest('p').find('input');
					if(input.val()){
						$.get(ajaxurl,{action:'wc_cuotas_add_provider',term: input.val()},function(r){
							input.val('');
							if(r.success){
								list.append(r.html);
								list.find('p.no-providers').remove();
							}else{
								alert(r.message);
							}
						});
					}
				});

			});

		</script>
		<?php
	}

	private function output__provider_row( $term ){
		if(is_int($term)){
			$term = get_term_by('term_id',$term,"wc_cuotas_provider");
		}
		$val = esc_attr($term->name);
		$default_provider = get_option('wc_cuotas_default_provider');
		$c = ($default_provider == $term->term_id) ? "CHECKED" : "";
		echo "<li><input type='radio' name='wc_cuotas_default_provider' value='{$term->term_id}' {$c} /><input name='wc_cuotas_providers[{$term->term_id}]' type='text' value='{$val}' /><label><input type='checkbox' value='0' name='wc_cuotas_providers[{$term->term_id}]' /> Borrar medio de pago</label></li>";
	}

	private function output__cuotas_tab(){
		echo "<h2>Cuotas</h2>";
		$cuotas = get_option('wc_cuotas');
		if(empty($cuotas)){
			$cuotas = array();
		}
		sort($cuotas);
		$data = esc_attr( json_encode( $cuotas) );
		$default = get_option('wc_cuotas_default');
		echo "<ul class='wc-cuotas-list' data-cuotas='{$data}' data-default='{$default}'>";

		echo "</ul>";
		echo "<p class='wc-cuotas-add-cuota'>";
		echo "<input type='number' min='1' step='1' /><button type='button' class='button button-primary button-small'>Añadir</button></p>";
		echo "</p>";
		?>
		<script type="text/template" id="wc-cuotas-row">
			<li><input type="radio" name="wc_cuotas_default" value='%val%' /><input type='hidden' name='wc_cuotas[]' value='%val%' /> %val% <a class='remove'>&times;</a></li>
		</script>
		<script type="text/javascript">
			jQuery(function($){
				var list = $('.wc-cuotas-list'),
				tpl = $('#wc-cuotas-row').html(),
				add = $('.wc-cuotas-add-cuota');
				list.on('click','a.remove',function(){
					$(this).closest('li').remove();
					var c = list.find('input[type="radio"]:checked');
				});
				add.find('button').on('click',function(){
					var i = add.find('input');
					if(i.val()){
						addRow(i.val());
						i.val('');
					}
				});
				function addRow(val,check){
					var li = $(tpl.replace(/%val%/ig,val));
					list.append(li);
					if(check){
						li.find('input[type="radio"]').prop('checked',true);
					}
				}
				var def = list.data('default');
				$(list.data('cuotas')).each(function(){
					addRow(this,this == def);
				});
			});

		</script>
		<?php
	}

	private function get_sections(){
		$sections = array();
		$sections['settings'] = "General";
		$sections['providers'] = "Medios de pago";
		$sections['cuotas'] = "Cuotas";
	    $gateways = WC()->payment_gateways->get_available_payment_gateways();
		foreach($gateways as $id => $gateway){
			$sections['gateway_'.$id] = $gateway->method_title;
		}
		return $sections;
	}

	public function action__output_sections() {
		global $current_section;

		$sections = $this->get_sections();

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
			echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=cuotas&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
		}
		echo '</ul><br class="clear" />';
	}

	public function action__show_cuotas_meta( $order ) {
		$num = get_post_meta($order->id,"_wc_cuotas_num",true);
		$cuota = get_post_meta($order->id,"_wc_cuotas_cuota",true);
		if(empty($cuota) || empty($num)){
			return;
		}
		echo "<div class='wc-cuotas-order-info'><h3>Pago en cuotas</h3>";
		echo "<p><strong>{$num} cuotas de ".wc_price($cuota)."</strong>";
		$provider = get_post_meta($order->id,"_wc_cuotas_provider",true);
		if(!empty($provider)){
			echo "<br/>Medio de pago: {$provider}";
		}
		echo "</p></div>";
	}

	public function ajax__add_provider(){
		$term = wp_insert_term( $_GET['term'], "wc_cuotas_provider" );
		if(is_wp_error()){
			wp_send_json(array(
				"success" => false,
				"message" => "No se pudo agregar el medio de pago"
			));
		}else{
			ob_start();
			$this->output__provider_row( $term['term_id'] );
			$html = ob_get_clean();
			wp_send_json(array(
				"success" => true,
				"html" => $html
			));
		}
	}


}

add_action('plugins_loaded',function(){
	new WC_Cuotas_Admin();
});
