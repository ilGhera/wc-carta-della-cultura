<?php
/**
 * Plugin name: ilGhera Carta della Cultura for WooCommerce
 * Plugin URI: https://www.ilghera.com/product/carta-della-cultura-for-wc-premium/
 * Description: Abilita in WooCommerce il pagamento con Carta della Cultura prevista dallo stato Italiano.
 * Author: ilGhera
 * Author URI: https://ilghera.com
 * Version: 0.9.2
 * Stable tag: 0.9.2
 * Requires at least: 4.0
 * Tested up to: 6.9
 * WC tested up to: 10
 * Text Domain: ilghera-carta-della-cultura-for-woocommerce
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

	/*WooCommerce è presente e attivo?*/
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/*Definizione costanti*/
	define( 'WCCDC_DIR', plugin_dir_path( __FILE__ ) );
	define( 'WCCDC_URI', plugin_dir_url( __FILE__ ) );
	define( 'WCCDC_INCLUDES', WCCDC_DIR . 'includes/' );
	define( 'WCCDC_INCLUDES_URI', WCCDC_URI . 'includes/' );
	define( 'WCCDC_VERSION', '0.9.2' );

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

	/**
	 * Script e folgi di stile front-end
	 *
	 * @return void
	 */
	function wccdc_load_scripts() {
		wp_enqueue_style( 'wccdc-style', WCCDC_URI . 'css/wc-carta-della-cultura.css', array(), WCCDC_VERSION );
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

