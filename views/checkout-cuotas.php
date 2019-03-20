<div class='wc-cuotas-checkout' data-cuotas="<?php echo esc_attr( json_encode( $data ));?>">
	<?php if(!empty($providers)): ?>
		<div class='wc-cuotas-field wc-cuotas-provider'>
			<label>Medio de pago</label>
			<select data-select='provider' name='wc_cuotas_<?php echo $id;?>_provider'><option value=''>Elegi un medio de pago</option>
				<?php
				foreach($providers as $provider){
					$sel = ($preference['provider'] == $provider->term_id) ? "SELECTED" : "";
					echo "<option value='{$provider->term_id}' {$sel}>{$provider->name}</option>";
				}
				?>
			 </select>
		 </div>
	 <?php endif; ?>
	 <div class='wc-cuotas-field wc-cuotas-num'>
	 	 <label>Cantidad de cuotas</label>
		 <select data-select='num' name='wc_cuotas_<?php echo $id;?>_num'><option value=''>Sin cuotas</option>
			 <?php foreach($cuotas as $cuota){
				 $sel = ($preference['num'] == $cuota) ? "SELECTED" : "";
				 echo "<option value='{$cuota}' {$sel}>{$cuota} cuotas</option>";
			 } ?>
		 </select>
	 </div>
	 <?php if(!empty($preference) && $preference['provider'] !== "" && !empty($preference['num'])):
		 $cart = WC()->cart;
		 $total = $cart->cart_contents_total + $cart->shipping_total;
		 $settings = WC_Cuotas::getCuotaData($id,$preference['provider'],$preference['num']);
		 $ftotal = $settings['calc'] * $total;
		 $cuota = $ftotal / $preference['num'];
		 ?>
		 <div class='wc-cuotas-cuota'>
			 <div class='cuota' style='font-size: 120%'><strong><span><?php echo "{$preference['num']} cuotas de ".wc_price( $cuota );?></span></strong></div>
	 		 <div class='total'>Precio Financiado Total: <span><?php echo wc_price( $ftotal);?></span></div>
	 		 <div class='tea'>TEA: <span><?php echo number_format($settings['tea'],2,",",".");?>%</span></div>
	 		 <div class='cft' style='font-size:225%'>CFT: <span><?php echo number_format($settings['cft'],2,",",".");?>%</span></div>
			 <?php
			 if(!empty($data['apply_to_order'])){
				 echo "<p>Los costos de financiación se agregarán al pedido</p>";
			 }
			 ?>
		 </div>
	 <?php endif; ?>
	 <?php if(!empty($data['checkout_text'])){
		 echo "<p class='wc-cuotas-checkout-text'>{$data['checkout_text']}</p>";
	 } ?>
</div>
