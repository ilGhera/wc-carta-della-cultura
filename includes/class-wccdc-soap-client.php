<?php
/**
 * Gestice le chiamate del web service
 *
 * @author ilGhera
 * @package wc-carta-della-cultura/includes
 *
 * @since 1.4.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCCDC_Soap_Client class
 *
 * @since 1.4.6
 */
class WCCDC_Soap_Client {

	/**
	 * Opzione sandbox
	 *
	 * @var bool
	 */
	public $sandbox;

	/**
	 * Il certificato .pem
	 *
	 * @var string
	 */
	public $local_cert;

	/**
	 * L'endpoint
	 *
	 * @var string
	 */
	public $location;

	/**
	 * La password legata al certificato
	 *
	 * @var string
	 */
	public $passphrase;

	/**
	 * Il file WSDL previsto da Carta della Cultura
	 *
	 * @var string
	 */
	public $wsdl;

	/**
	 * Il buono Carta della Cultura
	 *
	 * @var string
	 */
	public $codice_voucher;

	/**
	 * Il valore del buono
	 *
	 * @var float
	 */
	public $import;

	/**
	 * The constructor
	 *
	 * @param string $codice_voucher il codice Carta della Cultura.
	 * @param float  $import         il valore del buono.
	 *
	 * @return void
	 */
	public function __construct( $codice_voucher, $import ) {

		$this->sandbox = get_option( 'wccdc-sandbox' );

		if ( $this->sandbox ) {
			$this->local_cert = WCCDC_DIR . 'demo/wccdc-demo-certificate.pem';
			$this->location   = 'https://wstest.cartadellacultura.it/WSUtilizzoVoucherCDCWEB/VerificaVoucher';
			$this->passphrase = 'm3D0T4aM';

		} else {
			$this->local_cert = WCCDC_PRIVATE . $this->get_local_cert();
			$this->location   = 'https://ws.cartadellacultura.it/WSUtilizzoVoucherCDCWEB/VerificaVoucher';
			$this->passphrase = $this->get_user_passphrase();
		}

		$this->wsdl           = WCCDC_INCLUDES . 'VerificaVoucher.wsdl';
		$this->codice_voucher = $codice_voucher;
		$this->import         = $import;

	}

	/**
	 * Restituisce il nome del certificato presente nella cartella "Private"
	 *
	 * @return string
	 */
	public function get_local_cert() {
		$cert = wccdc_admin::get_the_file( '.pem' );
		if ( $cert ) {
			return esc_html( basename( $cert ) );
		}
	}

	/**
	 * Restituisce la password memorizzata dall'utente nella compilazione del form
	 *
	 * @return string
	 */
	public function get_user_passphrase() {
		return base64_decode( get_option( 'wccdc-password' ) );
	}

	/**
	 * Istanzia il SoapClient
	 */
	public function soap_client() {
		$soap_client = new SoapClient(
			$this->wsdl,
			array(
				'local_cert'     => $this->local_cert,
				'location'       => $this->location,
				'passphrase'     => $this->passphrase,
				'cache_wsdl'     => WSDL_CACHE_NONE,
				'stream_context' => stream_context_create(
					array(
						'http' => array(
							'user_agent' => 'PHP/SOAP',
						),
						'ssl'  => array(
							'verify_peer'       => false,
							'verify_peer_name'  => false,
							'allow_self_signed' => true,
							'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
						),
					)
				),
			)
		);

		return $soap_client;
	}

	/**
	 * Ottiene il codice ISBN dal primo prodotto dell'ordine
	 *
	 * @param int $order_id ID dell'ordine.
	 * @return string|null ISBN o null se non trovato
	 */
	private function get_isbn_from_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		// Ottieni il campo ISBN configurato
		$isbn_field = get_option( 'wccdc-isbn-field' );
		if ( empty( $isbn_field ) || $isbn_field === 'none' ) {
			return null;
		}

		// Se è un campo personalizzato (custom)
		if ( $isbn_field === 'custom' ) {
			$isbn_field = get_option( 'wccdc-custom-isbn-field-value' );
			if ( empty( $isbn_field ) ) {
				return null;
			}
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$isbn = null;
			
			// Determina se è un meta field o un attributo
			if ( strpos( $isbn_field, 'meta:' ) === 0 ) {
				// Campo meta
				$meta_key = substr( $isbn_field, 5 );
				$isbn = $product->get_meta( $meta_key );
			} elseif ( strpos( $isbn_field, 'attribute:' ) === 0 ) {
				// Attributo di prodotto
				$taxonomy = substr( $isbn_field, 10 );
				$isbn = $product->get_attribute( $taxonomy );
				// Se l'attributo è vuoto, prova a ottenere il termine
				if ( empty( $isbn ) ) {
					$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$isbn = $terms[0]->name;
					}
				}
			} else {
				// Fallback: prova come meta field
				$isbn = $product->get_meta( $isbn_field );
			}

			if ( ! empty( $isbn ) ) {
				// Pulisci il valore (rimuovi spazi, trattini)
				$clean_isbn = preg_replace( '/[^0-9]/', '', $isbn );
				// Verifica che sia un ISBN valido (13 cifre)
				if ( preg_match( '/^[0-9]{13}$/', $clean_isbn ) ) {
					return $clean_isbn;
				}
			}
		}

		return null;
	}

	/**
	 * Chiamata Check di tipo 1 e 2
	 *
	 * @param  integer $value il tipo di operazione da eseguire
	 * 1 per solo controllo
	 * 2 per scalare direttamente il valore del buono.
	 * @param  int|null $order_id ID dell'ordine per ottenere ISBN.
	 */
	public function check( $value = 1, $order_id = null ) {
		$isbn = $order_id ? $this->get_isbn_from_order( $order_id ) : null;
		
		$check_data = array(
			'tipoOperazione' => $value,
			'codiceVoucher'  => $this->codice_voucher,
			'importo'        => $this->import,
			'tipoBene'       => 'L', // Sempre "L" per libri
		);
		
		if ( ! empty( $isbn ) ) {
			$check_data['codiceISBN'] = $isbn;
		}

		$check = $this->soap_client()->Check(
			array(
				'checkReq' => $check_data,
			)
		);

		return $check;
	}

	/**
	 * Chiamata Confirm utile ad utilizzare solo parte del valore del buono
	 *
	 * @param int|null $order_id ID dell'ordine per ottenere ISBN.
	 */
	public function confirm( $order_id = null ) {
		$isbn = $order_id ? $this->get_isbn_from_order( $order_id ) : null;
		
		$confirm_data = array(
			'tipoOperazione' => '1',
			'codiceVoucher'  => $this->codice_voucher,
			'importo'        => $this->import,
			'tipoBene'       => 'L', // Sempre "L" per libri
		);
		
		if ( ! empty( $isbn ) ) {
			$confirm_data['codiceISBN'] = $isbn;
		}

		$confirm = $this->soap_client()->Confirm(
			array(
				'checkReq' => $confirm_data,
			)
		);

		return $confirm;
	}

}

