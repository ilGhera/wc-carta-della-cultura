<?php
/**
 * Class WCCDC
 *
 * @author ilGhera
 * @package wc-carta-della-cultura/includes
 *
 * @since 1.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCCDC class
 *
 * @since 1.4.0
 */
class WCCDC {

	/**
	 * The constructor
	 *
	 * @return void
	 */
	public function __construct() {

		/* Filters */
		add_filter( 'woocommerce_payment_gateways', array( $this, 'wccdc_add_gateway_class' ) );

		/* Actions */
		add_action( 'admin_init', array( $this, 'register_isbn_setting' ) );
	}

	/**
	 * Registra l'impostazione per il campo ISBN
	 *
	 * @return void
	 */
	public function register_isbn_setting() {
		register_setting( 'wccdc-options', 'wccdc-isbn-source' );
		register_setting( 'wccdc-options', 'wccdc-isbn-field' );
		register_setting( 'wccdc-options', 'wccdc-custom-isbn-field-value' );
	}

	/**
	 * Restituisce i dati della sessione WC corrente
	 *
	 * @return array
	 */
	public function get_session_data() {

		$session = WC()->session;

		if ( $session ) {

			return $session->get_session_data();

		}

	}

	/**
	 * Se presente un certificato, aggiunge il nuovo gateway a quelli disponibili in WooCommerce
	 *
	 * @param array $methods gateways disponibili.
	 *
	 * @return array
	 */
	public function wccdc_add_gateway_class( $methods ) {

		$sandbox   = get_option( 'wccdc-sandbox' );

        if ( $sandbox || ( wccdc_admin::get_the_file( '.pem' ) && get_option( 'wccdc-cert-activation' ) ) ) {

            $methods[] = 'WCCDC_Gateway';

        }

		return $methods;

	}

}

new WCCDC();

