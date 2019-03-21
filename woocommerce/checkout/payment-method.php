<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<li class="wc_payment_method payment_method_<?php echo $gateway->id; ?>">
	<input id="payment_method_<?php echo $gateway->id; ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />
	<label for="payment_method_<?php echo $gateway->id; ?>">
		<?php echo $gateway->get_title(); ?> <?php echo $gateway->get_icon(); ?>
		<?php do_action('wc_cuotas_gateway_title', $gateway ); ?>
	</label>
	<?php
	ob_start();
	$gateway->payment_fields();
	do_action('wc_cuotas_gateway',$gateway);
	$box_content = ob_get_clean();
	?>
	<?php if( !empty($box_content) ): ?>
		<div class="payment_box payment_method_<?php echo $gateway->id; ?>" <?php if ( ! $gateway->chosen ) : ?>style="display:none;"<?php endif; ?>>
			<?php echo $box_content; ?>
		</div>
	<?php endif; ?>
</li>
