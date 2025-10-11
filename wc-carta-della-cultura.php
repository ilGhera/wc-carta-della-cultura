<?php
/**
 * Plugin name: ilGhera Carta della Cultura for WooCommerce - Premium
 * Plugin URI: https://www.ilghera.com/product/wc-carta-della-cultura/
 * Description: Abilita in WooCommerce il pagamento con Carta della Cultura prevista dallo stato Italiano.
 * Author: ilGhera
 * Author URI: https://ilghera.com
 * Version: 0.9.0
 * Stable tag: 0.9.0
 * Requires at least: 4.0
 * Tested up to: 6.8
 * WC tested up to: 10
 * Text Domain: wccdc
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package wc-carta-della-cultura
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attivazione
 */
function wccdc_premium_activation() {

	/*Se presente, disattiva la versione free del plugin*/
	if ( function_exists( 'wccdc_activation' ) ) {
		deactivate_plugins( 'wc-carta-della-cultura/wc-carta-della-cultura.php' );
		remove_action( 'plugins_loaded', 'wccdc_activation' );
		wp_safe_redirect( admin_url( 'plugins.php?plugin_status=all&paged=1&s' ) );
	}

	/*WooCommerce è presente e attivo?*/
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/*Definizione costanti*/
	define( 'WCCDC_DIR', plugin_dir_path( __FILE__ ) );
	define( 'WCCDC_URI', plugin_dir_url( __FILE__ ) );
	define( 'WCCDC_INCLUDES', WCCDC_DIR . 'includes/' );
	define( 'WCCDC_INCLUDES_URI', WCCDC_URI . 'includes/' );
	define( 'WCCDC_VERSION', '0.9.0' );

	/*Main directory di upload*/
	$wp_upload_dir = wp_upload_dir();

	/*Creo se necessario la cartella wccdc-private*/
	if ( wp_mkdir_p( trailingslashit( $wp_upload_dir['basedir'] . '/wccdc-private/files/backups' ) ) ) {
		define( 'WCCDC_PRIVATE', $wp_upload_dir['basedir'] . '/wccdc-private/' );
		define( 'WCCDC_PRIVATE_URI', $wp_upload_dir['baseurl'] . '/wccdc-private/' );
	}

	/*Requires*/
	require WCCDC_INCLUDES . 'class-wccdc-gateway.php';
	require WCCDC_INCLUDES . 'class-wccdc-soap-client.php';
	require WCCDC_INCLUDES . 'class-wccdc-admin.php';
	require WCCDC_INCLUDES . 'class-wccdc.php';
	require WCCDC_INCLUDES . 'ilghera-notice/class-ilghera-notice.php';

	/**
	 * Script e folgi di stile front-end
	 *
	 * @return void
	 */
	function wccdc_load_scripts() {
		wp_enqueue_style( 'wccdc-style', WCCDC_URI . 'css/wc-carta-della-cultura.css', array(), WCCDC_VERSION );
		wp_enqueue_script( 'wccdc-scripts', WCCDC_URI . 'js/wc-carta-della-cultura.js', array(), WCCDC_VERSION, false );
		wp_localize_script(
			'wccdc-scripts',
			'wccdcOptions',
			array(
				'ajaxURL'          => admin_url( 'admin-ajax.php' ),
				'couponConversion' => get_option( 'wccdc-coupon' ),
			)
		);
	}

	/**
	 * Script e folgi di stile back-end
	 *
	 * @return void
	 */
	function wccdc_load_admin_scripts() {

		$admin_page = get_current_screen();

		if ( isset( $admin_page->base ) && 'woocommerce_page_wccdc-settings' === $admin_page->base ) {

			wp_enqueue_style( 'wccdc-admin-style', WCCDC_URI . 'css/wc-carta-della-cultura-admin.css', array(), WCCDC_VERSION );
			wp_enqueue_script( 'wccdc-admin-scripts', WCCDC_URI . 'js/wc-carta-della-cultura-admin.js', array(), WCCDC_VERSION, false );

			/* Nonce per l'eliminazione del certificato */
			$delete_nonce  = wp_create_nonce( 'wccdc-del-cert-nonce' );
			$add_cat_nonce = wp_create_nonce( 'wccdc-add-cat-nonce' );

			wp_localize_script(
				'wccdc-admin-scripts',
				'wccdcData',
				array(
					'delCertNonce' => $delete_nonce,
					'addCatNonce'  => $add_cat_nonce,
				)
			);

			/*tzCheckBox*/
			wp_enqueue_style( 'tzcheckbox-style', WCCDC_URI . 'js/tzCheckbox/jquery.tzCheckbox/jquery.tzCheckbox.css', array(), WCCDC_VERSION );
			wp_enqueue_script( 'tzcheckbox', WCCDC_URI . 'js/tzCheckbox/jquery.tzCheckbox/jquery.tzCheckbox.js', array( 'jquery' ), WCCDC_VERSION, false );
			wp_enqueue_script( 'tzcheckbox-script', WCCDC_URI . 'js/tzCheckbox/js/script.js', array( 'jquery' ), WCCDC_VERSION, false );

		}

	}

	/*Script e folgi di stile*/
	add_action( 'wp_enqueue_scripts', 'wccdc_load_scripts' );
	add_action( 'admin_enqueue_scripts', 'wccdc_load_admin_scripts' );

}
add_action( 'plugins_loaded', 'wccdc_premium_activation', 1 );


/**
 * HPOS compatibility
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);


/**
 * Update checker
 */
require plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$wccdc_update_checker = PucFactory::buildUpdateChecker(
	'https://www.ilghera.com/wp-update-server-2/?action=get_metadata&slug=wc-carta-della-cultura-premium',
	__FILE__,
	'wc-carta-della-cultura-premium'
);

$wccdc_update_checker->addQueryArgFilter( 'wccdc_secure_update_check' );

/**
 * PUC Secure update check
 *
 * @param array $query_args the parameters.
 *
 * @return array
 */
function wccdc_secure_update_check( $query_args ) {
	$key = base64_encode( get_option( 'wccdc-premium-key' ) );

	if ( $key ) {
		$query_args['premium-key'] = $key;
	}
	return $query_args;
}


/**
 * Avvisi utente in fase di aggiornaemnto plugin
 *
 * @param array  $plugin_data the plugin metadata.
 * @param object $response    metadata about the available plugin update.
 *
 * @return void
 */
function wccdc_update_message( $plugin_data, $response ) {

	$message = null;
	$key     = get_option( 'wccdc-premium-key' );

	$message = null;

	if ( ! $key ) {

		/* Translators: the admin URL */
		$message = sprintf( __( 'Per ricevere aggiornamenti devi inserire la tua <b>Premium Key</b> nelle <a href="%sadmin.php/?page=wccdc-settings">impostazioni del plugin</a>. Clicca <a href="https://www.ilghera.com/product/woocommerce-carta-della-cultura-premium/" target="_blank">qui</a> per maggiori informazioni.', 'wccdc' ), admin_url() );

	} else {

		$decoded_key = explode( '|', base64_decode( $key ) );
		$bought_date = date( 'd-m-Y', strtotime( $decoded_key[1] ) );
		$limit       = strtotime( $bought_date . ' + 365 day' );
		$now         = strtotime( 'today' );

		if ( $limit < $now ) {
			$message = __( 'Sembra che la tua <strong>Premium Key</strong> sia scaduta. Clicca <a href="https://www.ilghera.com/product/woocommerce-carta-della-cultura-premium/" target="_blank">qui</a> per maggiori informazioni.', 'wccdc' );
		} elseif ( 3518 !== intval( $decoded_key[2] ) ) {
			$message = __( 'Sembra che la tua <strong>Premium Key</strong> non sia valida. Clicca <a href="https://www.ilghera.com/product/woocommerce-carta-della-cultura-premium/" target="_blank">qui</a> per maggiori informazioni.', 'wccdc' );
		}
	}

	$allowed = array(
		'b' => array(),
		'a' => array(
			'href'   => array(),
			'target' => array(),
		),
	);

	echo ( $message ) ? '<br><span class="wccdc-alert">' . wp_kses( $message, $allowed ) . '</span>' : '';

}
add_action( 'in_plugin_update_message-wc-carta-della-cultura-premium/wc-carta-della-cultura.php', 'wccdc_update_message', 10, 2 );

