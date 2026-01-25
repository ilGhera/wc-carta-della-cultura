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
		error_log('WCCDC DEBUG get_isbn_from_order START - Order ID: ' . $order_id);
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			error_log('WCCDC DEBUG get_isbn_from_order - Order not found');
			return null;
		}

		// Ottieni il campo ISBN configurato
		$isbn_field = get_option( 'wccdc-isbn-field' );
		error_log('WCCDC DEBUG get_isbn_from_order - ISBN field setting: ' . $isbn_field);
		
		if ( empty( $isbn_field ) || $isbn_field === 'none' ) {
			error_log('WCCDC DEBUG get_isbn_from_order - ISBN field is none or empty');
			return null;
		}

		// Se è un campo personalizzato (custom)
		if ( $isbn_field === 'custom' ) {
			$isbn_field = get_option( 'wccdc-custom-isbn-field-value' );
			error_log('WCCDC DEBUG get_isbn_from_order - Custom field value: ' . $isbn_field);
			if ( empty( $isbn_field ) ) {
				error_log('WCCDC DEBUG get_isbn_from_order - Custom field is empty');
				return null;
			}
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				error_log('WCCDC DEBUG get_isbn_from_order - Product not found in item');
				continue;
			}

			error_log('WCCDC DEBUG get_isbn_from_order - Processing product ID: ' . $product->get_id());
			
			$isbn = null;
			
			// Determina se è un meta field o un attributo
			if ( strpos( $isbn_field, 'meta:' ) === 0 ) {
				// Campo meta
				$meta_key = substr( $isbn_field, 5 );
				error_log('WCCDC DEBUG get_isbn_from_order - Meta field search, key: ' . $meta_key);
				$isbn = $product->get_meta( $meta_key );
				error_log('WCCDC DEBUG get_isbn_from_order - Meta value found: ' . $isbn);
			} elseif ( strpos( $isbn_field, 'attribute:' ) === 0 ) {
				// Attributo di prodotto (globale)
				$taxonomy = substr( $isbn_field, 10 );
				error_log('WCCDC DEBUG get_isbn_from_order - Attribute field search, taxonomy: ' . $taxonomy);
				$isbn = $product->get_attribute( $taxonomy );
				error_log('WCCDC DEBUG get_isbn_from_order - Attribute value found: ' . $isbn);
				// Se l'attributo è vuoto, prova a ottenere il termine
				if ( empty( $isbn ) ) {
					$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$isbn = $terms[0]->name;
						error_log('WCCDC DEBUG get_isbn_from_order - Term value found: ' . $isbn);
					}
				}
			} else {
				// Inserimento manuale: normalizza il nome
				$field_lower = strtolower( trim( $isbn_field ) );
				error_log('WCCDC DEBUG get_isbn_from_order - Manual field search, normalized: ' . $field_lower);
				
				// 1. Prova come meta field (case-insensitive)
				$meta_keys = $product->get_meta_data();
				error_log('WCCDC DEBUG get_isbn_from_order - Product meta keys count: ' . count($meta_keys));
				foreach ( $meta_keys as $meta ) {
					if ( strtolower( $meta->key ) === $field_lower ) {
						$isbn = $meta->value;
						error_log('WCCDC DEBUG get_isbn_from_order - Found in meta: key=' . $meta->key . ', value=' . $isbn);
						break;
					}
				}
				
				// 2. Se non trovato come meta, prova come attributo
				if ( empty( $isbn ) ) {
					error_log('WCCDC DEBUG get_isbn_from_order - Not found in meta, trying attribute');
					// Per attributi globali: aggiungi automaticamente 'pa_' se non presente
					$taxonomy = $field_lower;
					if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
						$taxonomy = 'pa_' . $taxonomy;
					}
					error_log('WCCDC DEBUG get_isbn_from_order - Trying taxonomy with pa_: ' . $taxonomy);
					
					// Prova con il prefisso pa_
					$isbn = $product->get_attribute( $taxonomy );
					error_log('WCCDC DEBUG get_isbn_from_order - Attribute value with pa_: ' . $isbn);
					if ( empty( $isbn ) ) {
						$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
						if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
							$isbn = $terms[0]->name;
							error_log('WCCDC DEBUG get_isbn_from_order - Term value with pa_: ' . $isbn);
						}
					}
					
					// Se ancora vuoto, prova senza pa_ (attributo personalizzato di prodotto)
					if ( empty( $isbn ) ) {
						error_log('WCCDC DEBUG get_isbn_from_order - Trying without pa_ prefix');
						$taxonomy = $field_lower;
						if ( strpos( $taxonomy, 'pa_' ) === 0 ) {
							$taxonomy = substr( $taxonomy, 3 );
						}
						error_log('WCCDC DEBUG get_isbn_from_order - Trying taxonomy: ' . $taxonomy);
						$isbn = $product->get_attribute( $taxonomy );
						error_log('WCCDC DEBUG get_isbn_from_order - Attribute value without pa_: ' . $isbn);
						if ( empty( $isbn ) ) {
							// Per attributi personalizzati, cerca nei meta con prefisso 'attribute_'
							$attribute_meta_key = 'attribute_' . $taxonomy;
							error_log('WCCDC DEBUG get_isbn_from_order - Trying attribute meta: ' . $attribute_meta_key);
							$isbn = $product->get_meta( $attribute_meta_key );
							error_log('WCCDC DEBUG get_isbn_from_order - Attribute meta value: ' . $isbn);
						}
					}
				}
			}

			if ( ! empty( $isbn ) ) {
				error_log('WCCDC DEBUG get_isbn_from_order - Raw ISBN found: ' . $isbn);
				// Pulisci il valore (rimuovi spazi, trattini) e mantieni solo numeri
				$clean_isbn = preg_replace( '/[^0-9]/', '', $isbn );
				error_log('WCCDC DEBUG get_isbn_from_order - Cleaned ISBN: ' . $clean_isbn);
				// Non validiamo la lunghezza, lasciamo che sia il server SOAP a farlo
				// Invia sempre l'ISBN pulito se non vuoto
				if ( ! empty( $clean_isbn ) ) {
					error_log('WCCDC DEBUG get_isbn_from_order - Returning ISBN: ' . $clean_isbn . ' (' . strlen($clean_isbn) . ' digits)');
					return $clean_isbn;
				} else {
					error_log('WCCDC DEBUG get_isbn_from_order - Cleaned ISBN is empty');
				}
			} else {
				error_log('WCCDC DEBUG get_isbn_from_order - No ISBN found for this product');
			}
		}

		error_log('WCCDC DEBUG get_isbn_from_order - No ISBN found in any product');
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
		error_log('WCCDC DEBUG check START - Operation type: ' . $value . ', Order ID: ' . $order_id . ', Voucher: ' . $this->codice_voucher . ', Import: ' . $this->import);
		
		// Secondo il WSDL, Check richiede solo tipoOperazione e codiceVoucher
		// partitaIvaEsercente è opzionale, importo, tipoBene e codiceISBN non sono previsti
		$check_data = array(
			'tipoOperazione' => $value,
			'codiceVoucher'  => $this->codice_voucher,
		);
		
		// partitaIvaEsercente è opzionale, non lo inviamo per ora
		// $check_data['partitaIvaEsercente'] = '';
		
		error_log('WCCDC DEBUG check - Request data: ' . print_r($check_data, true));

		try {
			$check = $this->soap_client()->Check(
				array(
					'checkReq' => $check_data,
				)
			);
			error_log('WCCDC DEBUG check - SOAP call successful');
			return $check;
		} catch (Exception $e) {
			error_log('WCCDC DEBUG check - SOAP Exception: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Chiamata Confirm utile ad utilizzare solo parte del valore del buono
	 *
	 * @param int|null $order_id ID dell'ordine per ottenere ISBN.
	 */
	public function confirm( $order_id = null ) {
		error_log('WCCDC DEBUG confirm START - Order ID: ' . $order_id . ', Voucher: ' . $this->codice_voucher . ', Import: ' . $this->import);
		
		// Secondo il WSDL, Confirm richiede tipoOperazione, codiceVoucher e importo
		// tipoBene e codiceISBN non sono previsti
		$confirm_data = array(
			'tipoOperazione' => '1',
			'codiceVoucher'  => $this->codice_voucher,
			'importo'        => $this->import,
		);
		
		error_log('WCCDC DEBUG confirm - Request data: ' . print_r($confirm_data, true));

		try {
			$confirm = $this->soap_client()->Confirm(
				array(
					'checkReq' => $confirm_data,
				)
			);
			error_log('WCCDC DEBUG confirm - SOAP call successful');
			return $confirm;
		} catch (Exception $e) {
			error_log('WCCDC DEBUG confirm - SOAP Exception: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Chiamata InsertISBN per inviare i dettagli ISBN dopo la confirm
	 *
	 * @param int|null $order_id ID dell'ordine per ottenere ISBN.
	 * @return mixed
	 */
	public function insert_isbn( $order_id = null ) {
		error_log('WCCDC DEBUG insert_isbn START - Order ID: ' . $order_id . ', Voucher: ' . $this->codice_voucher . ', Import: ' . $this->import);
		
		$isbn = $order_id ? $this->get_isbn_from_order( $order_id ) : null;
		error_log('WCCDC DEBUG insert_isbn - ISBN retrieved: ' . ($isbn ? $isbn : 'NULL'));
		
		// Secondo il WSDL, InsertISBN richiede codiceVoucher, tipoOperazione e listaISBN
		// listaISBN è un array di DettaglioIsbnBean (importo e isbn)
		$insert_data = array(
			'codiceVoucher'  => $this->codice_voucher,
			'tipoOperazione' => '1',
		);
		
		if ( ! empty( $isbn ) ) {
			// Crea listaISBN con un solo elemento
			$insert_data['listaISBN'] = array(
				'dettaglioISBN' => array(
					array(
						'importo' => $this->import,
						'isbn'    => $isbn,
					)
				)
			);
			error_log('WCCDC DEBUG insert_isbn - Adding listaISBN with ISBN: ' . $isbn . ', Import: ' . $this->import);
		} else {
			error_log('WCCDC DEBUG insert_isbn - No ISBN to add to request');
		}
		
		error_log('WCCDC DEBUG insert_isbn - Request data: ' . print_r($insert_data, true));

		try {
			$response = $this->soap_client()->InsertISBN(
				array(
					'checkReq' => $insert_data,
				)
			);
			error_log('WCCDC DEBUG insert_isbn - SOAP call successful');
			return $response;
		} catch (Exception $e) {
			error_log('WCCDC DEBUG insert_isbn - SOAP Exception: ' . $e->getMessage());
			throw $e;
		}
	}
}

