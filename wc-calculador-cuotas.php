<?php

/*
Plugin Name: WooCommerce Calculador de Cuotas
Version: 1.0
Author: Martin Hein for Triptongo
*/

define('WC_CUOTAS_DIR', dirname( __FILE__ ) );
define('WC_CUOTAS_URL', plugin_dir_url( __FILE__ ) );

require_once( 'inc/wc-cuotas.php' );
require_once( 'inc/wc-cuotas-admin.php' );
require_once( 'inc/wc-cuotas-front.php' );
require_once( 'inc/wc-cuotas-math.php' );
