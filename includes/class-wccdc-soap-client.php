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
	 * Ottiene tutti i codici ISBN dai prodotti dell'ordine con i relativi importi proporzionali
	 *
	 * @param int $order_id ID dell'ordine.
	 * @param float $importo_totale Importo totale da suddividere.
	 * @return array|null Array di array con 'isbn' e 'importo', o null se nessun ISBN trovato
	 */
	private function get_isbn_list_from_order( $order_id, $importo_totale ) {
		error_log('WCCDC DEBUG get_isbn_list_from_order START - Order ID: ' . $order_id . ', Importo totale: ' . $importo_totale);
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			error_log('WCCDC DEBUG get_isbn_list_from_order - Order not found');
			return null;
		}

		// Ottieni il campo ISBN configurato
		$isbn_field = get_option( 'wccdc-isbn-field' );
		error_log('WCCDC DEBUG get_isbn_list_from_order - ISBN field setting: ' . $isbn_field);
		
		if ( empty( $isbn_field ) || $isbn_field === 'none' ) {
			error_log('WCCDC DEBUG get_isbn_list_from_order - ISBN field is none or empty');
			return null;
		}

		// Se è un campo personalizzato (custom)
		if ( $isbn_field === 'custom' ) {
			$isbn_field = get_option( 'wccdc-custom-isbn-field-value' );
			error_log('WCCDC DEBUG get_isbn_list_from_order - Custom field value: ' . $isbn_field);
			if ( empty( $isbn_field ) ) {
				error_log('WCCDC DEBUG get_isbn_list_from_order - Custom field is empty');
				return null;
			}
		}

		$isbn_list = array();
		$items_with_isbn = 0;
		$total_items_price = 0;
		
		// Prima passata: conta prodotti con ISBN e calcola prezzo totale dei prodotti con ISBN
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			
			$isbn = $this->get_isbn_from_product( $product, $isbn_field );
			if ( ! empty( $isbn ) ) {
				$items_with_isbn++;
				$total_items_price += $item->get_total();
			}
		}
		
		error_log('WCCDC DEBUG get_isbn_list_from_order - Prodotti con ISBN: ' . $items_with_isbn . ', Totale prezzi: ' . $total_items_price);
		
		if ( $items_with_isbn === 0 ) {
			error_log('WCCDC DEBUG get_isbn_list_from_order - Nessun ISBN trovato in nessun prodotto');
			return null;
		}
		
		// Seconda passata: crea lista ISBN con importi proporzionali
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			
			$isbn = $this->get_isbn_from_product( $product, $isbn_field );
			if ( ! empty( $isbn ) ) {
				// Calcola importo proporzionale per questo prodotto
				$item_price = $item->get_total();
				if ( $total_items_price > 0 ) {
					$item_import = round( ( $item_price / $total_items_price ) * $importo_totale, 2 );
				} else {
					$item_import = round( $importo_totale / $items_with_isbn, 2 );
				}
				
				// Pulisci ISBN (rimuovi spazi, trattini) e mantieni solo numeri
				$clean_isbn = preg_replace( '/[^0-9]/', '', $isbn );
				
				if ( ! empty( $clean_isbn ) ) {
					$isbn_list[] = array(
						'isbn'    => $clean_isbn,
						'importo' => $item_import,
					);
					error_log('WCCDC DEBUG get_isbn_list_from_order - Aggiunto ISBN: ' . $clean_isbn . ', Importo: ' . $item_import);
				}
			}
		}
		
		error_log('WCCDC DEBUG get_isbn_list_from_order - Lista ISBN creata con ' . count($isbn_list) . ' elementi');
		return $isbn_list;
	}
	
	/**
	 * Ottiene l'ISBN da un singolo prodotto
	 *
	 * @param WC_Product $product Il prodotto.
	 * @param string $isbn_field Il campo ISBN configurato.
	 * @return string|null ISBN o null se non trovato
	 */
	public function get_isbn_from_product( $product, $isbn_field ) {
		$isbn = null;
		
		error_log('WCCDC DEBUG get_isbn_from_product - ISBN field: ' . $isbn_field . ', Product ID: ' . $product->get_id());
		
		// Determina se è un meta field o un attributo
		if ( strpos( $isbn_field, 'meta:' ) === 0 ) {
			// Campo meta
			$meta_key = substr( $isbn_field, 5 );
			$isbn = $product->get_meta( $meta_key );
			error_log('WCCDC DEBUG get_isbn_from_product - Meta field lookup: key=' . $meta_key . ', value=' . $isbn);
		} elseif ( strpos( $isbn_field, 'attribute:' ) === 0 ) {
			// Attributo di prodotto (globale)
			$taxonomy = substr( $isbn_field, 10 );
			$isbn = $product->get_attribute( $taxonomy );
			error_log('WCCDC DEBUG get_isbn_from_product - Attribute lookup: taxonomy=' . $taxonomy . ', value=' . $isbn);
			// Se l'attributo è vuoto, prova a ottenere il termine
			if ( empty( $isbn ) ) {
				$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$isbn = $terms[0]->name;
					error_log('WCCDC DEBUG get_isbn_from_product - Term lookup: term=' . $isbn);
				}
			}
		} else {
			// Inserimento manuale: normalizza il nome
			$field_lower = strtolower( trim( $isbn_field ) );
			error_log('WCCDC DEBUG get_isbn_from_product - Manual field (lowercase): ' . $field_lower);
			
			// 1. Prova come meta field usando get_meta() (case-insensitive)
			// Ottieni tutti i meta keys del prodotto
			$meta_keys = $product->get_meta_data();
			$meta_keys_log = array();
			foreach ( $meta_keys as $meta ) {
				$meta_keys_log[] = $meta->key;
				if ( strtolower( $meta->key ) === $field_lower ) {
					$isbn = $product->get_meta( $meta->key );
					error_log('WCCDC DEBUG get_isbn_from_product - Found as meta: key=' . $meta->key . ', value=' . $isbn);
					break;
				}
			}
			if ( empty( $isbn ) ) {
				error_log('WCCDC DEBUG get_isbn_from_product - Meta keys available: ' . implode(', ', $meta_keys_log));
			}
			
			// 2. Se non trovato come meta, prova come attributo
			if ( empty( $isbn ) ) {
				// Per attributi globali: aggiungi automaticamente 'pa_' se non presente
				$taxonomy = $field_lower;
				if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
					$taxonomy = 'pa_' . $taxonomy;
				}
				
				error_log('WCCDC DEBUG get_isbn_from_product - Trying attribute taxonomy: ' . $taxonomy);
				
				// Prova con il prefisso pa_
				$isbn = $product->get_attribute( $taxonomy );
				if ( empty( $isbn ) ) {
					$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$isbn = $terms[0]->name;
						error_log('WCCDC DEBUG get_isbn_from_product - Found as term: ' . $isbn);
					}
				}
				
				// Se ancora vuoto, prova senza pa_ (attributo personalizzato di prodotto)
				if ( empty( $isbn ) ) {
					$taxonomy = $field_lower;
					if ( strpos( $taxonomy, 'pa_' ) === 0 ) {
						$taxonomy = substr( $taxonomy, 3 );
					}
					error_log('WCCDC DEBUG get_isbn_from_product - Trying custom attribute: ' . $taxonomy);
					$isbn = $product->get_attribute( $taxonomy );
					if ( empty( $isbn ) ) {
						// Per attributi personalizzati, cerca nei meta con prefisso 'attribute_'
						$attribute_meta_key = 'attribute_' . $taxonomy;
						$isbn = $product->get_meta( $attribute_meta_key );
						error_log('WCCDC DEBUG get_isbn_from_product - Trying attribute meta: ' . $attribute_meta_key . ', value=' . $isbn);
					}
				}
			}
		}
		
		error_log('WCCDC DEBUG get_isbn_from_product - Final ISBN: ' . ( $isbn ? $isbn : 'NULL' ) );
		return $isbn;
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
		
		// Ottieni lista di tutti gli ISBN con importi proporzionali
		$isbn_list = $order_id ? $this->get_isbn_list_from_order( $order_id, $this->import ) : null;
		error_log('WCCDC DEBUG insert_isbn - ISBN list retrieved: ' . ($isbn_list ? print_r($isbn_list, true) : 'NULL'));
		
		// Se non ci sono ISBN, non chiamare il servizio
		if ( empty( $isbn_list ) ) {
			error_log('WCCDC DEBUG insert_isbn - Nessun ISBN trovato, salto chiamata SOAP');
			// Restituisci un oggetto fittizio con esito OK per non bloccare l'ordine
			return (object) array(
				'checkResp' => (object) array(
					'esito' => 'OK'
				)
			);
		}
		
		// Secondo il WSDL, InsertISBN richiede ValidazioneRequest con:
		// - codiceVoucher (string)
		// - tipoOperazione (string) - probabilmente sempre "1"
		// - listaISBN (opzionale) con dettaglioISBN array di oggetti con importo e isbn
		$validazione_request = array(
			'codiceVoucher'  => $this->codice_voucher,
			'tipoOperazione' => '1',
		);
		
		// Crea listaISBN con tutti gli elementi
		$dettaglio_isbn = array();
		foreach ( $isbn_list as $item ) {
			$dettaglio_isbn[] = array(
				'importo' => $item['importo'],
				'isbn'    => $item['isbn'],
			);
		}
		
		if ( ! empty( $dettaglio_isbn ) ) {
			$validazione_request['listaISBN'] = array(
				'dettaglioISBN' => $dettaglio_isbn
			);
		}
		
		error_log('WCCDC DEBUG insert_isbn - ValidazioneRequest data: ' . print_r($validazione_request, true));

		try {
			// Chiamata InsertISBN con ValidazioneRequest come parametro diretto
			$response = $this->soap_client()->InsertISBN(
				$validazione_request
			);
			error_log('WCCDC DEBUG insert_isbn - SOAP call successful');
			return $response;
		} catch (Exception $e) {
			error_log('WCCDC DEBUG insert_isbn - SOAP Exception: ' . $e->getMessage());
			// Log dettagliato dell'eccezione
			if (isset($e->detail)) {
				error_log('WCCDC DEBUG insert_isbn - Exception detail: ' . print_r($e->detail, true));
			}
			if (isset($e->faultstring)) {
				error_log('WCCDC DEBUG insert_isbn - Fault string: ' . $e->faultstring);
			}
			throw $e;
		}
	}
}

