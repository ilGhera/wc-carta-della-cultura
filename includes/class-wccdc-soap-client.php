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

		if ( $items_with_isbn === 0 ) {
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
					// Valida lunghezza ISBN (deve essere 13 cifre esatte)
					if ( strlen( $clean_isbn ) === 13 ) {
						// Valida checksum ISBN-13
						if ( $this->validate_isbn_13( $clean_isbn ) ) {
							$isbn_list[] = array(
								'isbn'    => $clean_isbn,
								'importo' => $item_import,
							);
						} else {
							// Lancia eccezione per bloccare l'ordine prima di spendere il buono
							throw new Exception( sprintf( __( 'ISBN non valido: %s (checksum errato)', 'ilghera-carta-della-cultura-for-woocommerce' ), $clean_isbn ) );
						}
					} else {
						// Lancia eccezione per bloccare l'ordine prima di spendere il buono
						throw new Exception( sprintf( __( 'ISBN non valido: %s (attese 13 cifre, trovate %d)', 'ilghera-carta-della-cultura-for-woocommerce' ), $clean_isbn, strlen($clean_isbn) ) );
					}
				}
			}
		}

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

		// Determina la fonte principale configurata
		$primary_source = get_option( 'wccdc-isbn-primary-source', 'wc_native' );

		// 1. Se fonte principale è "wc_native", cerca SOLO il campo nativo di WooCommerce
		if ( $primary_source === 'wc_native' ) {
			if ( method_exists( $product, 'get_global_unique_id' ) ) {
				$global_id = $product->get_global_unique_id();
				if ( ! empty( $global_id ) ) {
					$isbn = $global_id;
					return $isbn;
				} else {
					// Per varianti, prova a ottenere il GUID dal prodotto padre
					if ( $product->is_type( 'variation' ) ) {
						$parent_id = $product->get_parent_id();
						if ( $parent_id ) {
							$parent_product = wc_get_product( $parent_id );
							if ( $parent_product && method_exists( $parent_product, 'get_global_unique_id' ) ) {
								$parent_global_id = $parent_product->get_global_unique_id();
								if ( ! empty( $parent_global_id ) ) {
									$isbn = $parent_global_id;
									return $isbn;
								}
							}
						}
					}
					return null;
				}
			}
			// Se il metodo non esiste, restituisci null
			return null;
		}

		// 2. Se fonte principale è "custom", cerca SOLO nei campi configurati (meta, attributo, manuale)
		// Determina se è un meta field o un attributo
		if ( strpos( $isbn_field, 'meta:' ) === 0 ) {
			// Campo meta
			$meta_key = substr( $isbn_field, 5 );
			$isbn = $product->get_meta( $meta_key );
			// Se vuoto e siamo in una variante, prova dal padre
			if ( empty( $isbn ) && $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
				if ( $parent_id ) {
					$parent_product = wc_get_product( $parent_id );
					if ( $parent_product ) {
						$isbn = $parent_product->get_meta( $meta_key );
					}
				}
			}
		} elseif ( strpos( $isbn_field, 'attribute:' ) === 0 ) {
			// Attributo di prodotto (globale)
			$taxonomy = substr( $isbn_field, 10 );
			$isbn = $product->get_attribute( $taxonomy );
			// Se l'attributo è vuoto, prova a ottenere il termine
			if ( empty( $isbn ) ) {
				$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$isbn = $terms[0]->name;
				}
			}
			// Se ancora vuoto e siamo in una variante, prova dal padre
			if ( empty( $isbn ) && $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
				if ( $parent_id ) {
					$parent_product = wc_get_product( $parent_id );
					if ( $parent_product ) {
						$isbn = $parent_product->get_attribute( $taxonomy );
						if ( empty( $isbn ) ) {
							$terms = wp_get_post_terms( $parent_id, $taxonomy );
							if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
								$isbn = $terms[0]->name;
							}
						}
					}
				}
			}
		} else {
			// Inserimento manuale: normalizza il nome
			$field_lower = strtolower( trim( $isbn_field ) );

			// 1. Prova come meta field usando get_meta() (case-insensitive)
			// Ottieni tutti i meta keys del prodotto
			$meta_keys = $product->get_meta_data();
			foreach ( $meta_keys as $meta ) {
				if ( strtolower( $meta->key ) === $field_lower ) {
					$isbn = $product->get_meta( $meta->key );
					break;
				}
			}

			// 2. Se non trovato come meta, prova come attributo
			if ( empty( $isbn ) ) {
				// Per attributi globali: aggiungi automaticamente 'pa_' se non presente
				$taxonomy = $field_lower;
				if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
					$taxonomy = 'pa_' . $taxonomy;
				}

				// Prova con il prefisso pa_
				$isbn = $product->get_attribute( $taxonomy );
				if ( empty( $isbn ) ) {
					$terms = wp_get_post_terms( $product->get_id(), $taxonomy );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$isbn = $terms[0]->name;
					}
				}

				// Se ancora vuoto, prova senza pa_ (attributo personalizzato di prodotto)
				if ( empty( $isbn ) ) {
					$taxonomy = $field_lower;
					if ( strpos( $taxonomy, 'pa_' ) === 0 ) {
						$taxonomy = substr( $taxonomy, 3 );
					}
					$isbn = $product->get_attribute( $taxonomy );
					if ( empty( $isbn ) ) {
						// Per attributi personalizzati, cerca nei meta con prefisso 'attribute_'
						$attribute_meta_key = 'attribute_' . $taxonomy;
						$isbn = $product->get_meta( $attribute_meta_key );
					}
				}
			}

			// 3. Se ancora vuoto e siamo in una variante, prova dal padre (per meta e attributi)
			if ( empty( $isbn ) && $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
				if ( $parent_id ) {
					$parent_product = wc_get_product( $parent_id );
					if ( $parent_product ) {
						// Prova come meta nel padre
						$meta_keys = $parent_product->get_meta_data();
						foreach ( $meta_keys as $meta ) {
							if ( strtolower( $meta->key ) === $field_lower ) {
								$isbn = $parent_product->get_meta( $meta->key );
								break;
							}
						}

						// Se ancora vuoto, prova come attributo nel padre
						if ( empty( $isbn ) ) {
							$taxonomy = $field_lower;
							if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
								$taxonomy = 'pa_' . $taxonomy;
							}
							$isbn = $parent_product->get_attribute( $taxonomy );
							if ( empty( $isbn ) ) {
								$terms = wp_get_post_terms( $parent_id, $taxonomy );
								if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
									$isbn = $terms[0]->name;
								}
							}
							if ( empty( $isbn ) ) {
								$taxonomy = $field_lower;
								if ( strpos( $taxonomy, 'pa_' ) === 0 ) {
									$taxonomy = substr( $taxonomy, 3 );
								}
								$isbn = $parent_product->get_attribute( $taxonomy );
								if ( empty( $isbn ) ) {
									$attribute_meta_key = 'attribute_' . $taxonomy;
									$isbn = $parent_product->get_meta( $attribute_meta_key );
								}
							}
						}
					}
				}
			}
		}

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
		// Secondo il WSDL, Check richiede solo tipoOperazione e codiceVoucher
		// partitaIvaEsercente è opzionale, importo, tipoBene e codiceISBN non sono previsti
		$check_data = array(
			'tipoOperazione' => $value,
			'codiceVoucher'  => $this->codice_voucher,
		);

		try {
			$check = $this->soap_client()->Check(
				array(
					'checkReq' => $check_data,
				)
			);
			return $check;
		} catch (Exception $e) {
			// Log dell'errore SOAP per troubleshooting
			error_log('WCCDC SOAP ERROR check - Exception: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Chiamata Confirm utile ad utilizzare solo parte del valore del buono
	 *
	 * @param int|null $order_id ID dell'ordine per ottenere ISBN.
	 */
	public function confirm( $order_id = null ) {
		// Secondo il WSDL, Confirm richiede tipoOperazione, codiceVoucher e importo
		// tipoBene e codiceISBN non sono previsti
		$confirm_data = array(
			'tipoOperazione' => '1',
			'codiceVoucher'  => $this->codice_voucher,
			'importo'        => $this->import,
		);

		try {
			$confirm = $this->soap_client()->Confirm(
				array(
					'checkReq' => $confirm_data,
				)
			);
			return $confirm;
		} catch (Exception $e) {
			// Log dell'errore SOAP per troubleshooting
			error_log('WCCDC SOAP ERROR confirm - Exception: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Valida checksum ISBN-13
	 *
	 * @param string $isbn ISBN pulito (solo cifre).
	 * @return bool
	 */
	private function validate_isbn_13( $isbn ) {
		// Algoritmo di validazione ISBN-13
		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$digit = (int) $isbn[$i];
			// Moltiplicatore: 1 per posizioni dispari (0-based), 3 per pari
			$multiplier = ( $i % 2 === 0 ) ? 1 : 3;
			$sum += $digit * $multiplier;
		}
		$checksum = (10 - ($sum % 10)) % 10;
		return $checksum === (int) $isbn[12];
	}

	/**
	 * Chiamata InsertISBN per inviare i dettagli ISBN dopo la confirm
	 *
	 * @param int|null $order_id ID dell'ordine per ottenere ISBN.
	 * @return mixed
	 */
	public function insert_isbn( $order_id = null ) {
		// Ottieni lista di tutti gli ISBN con importi proporzionali
		$isbn_list = $order_id ? $this->get_isbn_list_from_order( $order_id, $this->import ) : null;

		// Se non ci sono ISBN, controlla se il campo ISBN è configurato
		$isbn_field = get_option( 'wccdc-isbn-field' );
		if ( empty( $isbn_list ) ) {
			// Se il campo ISBN è configurato (non "none"), dobbiamo fallire
			if ( ! empty( $isbn_field ) && $isbn_field !== 'none' ) {
				throw new Exception( __( 'ISBN non trovato nei prodotti nonostante il campo sia configurato. Verifica che tutti i prodotti abbiano ISBN valido.', 'ilghera-carta-della-cultura-for-woocommerce' ) );
			} else {
				// Campo ISBN non configurato: restituisci OK fittizio
				return (object) array(
					'checkResp' => (object) array(
						'esito' => 'OK'
					)
				);
			}
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

		try {
			// Chiamata InsertISBN con ValidazioneRequest come parametro diretto
			$response = $this->soap_client()->InsertISBN(
				$validazione_request
			);

			// La risposta SOAP può essere un oggetto stdClass con proprietà 'esito' diretta
			// oppure contenere un wrapper. Normalizziamo la risposta per avere sempre 'esito' a livello principale.
			$normalized_response = new stdClass();

			if ( isset( $response->esito ) ) {
				// Risposta diretta con esito
				$normalized_response->esito = $response->esito;
			} elseif ( isset( $response->ValidazioneResponse ) && isset( $response->ValidazioneResponse->esito ) ) {
				// Risposta incapsulata in ValidazioneResponse
				$normalized_response->esito = $response->ValidazioneResponse->esito;
			} elseif ( isset( $response->checkResp ) && isset( $response->checkResp->esito ) ) {
				// Per retrocompatibilità
				$normalized_response->esito = $response->checkResp->esito;
			} else {
				// Se non troviamo esito, assumiamo errore
				$normalized_response->esito = 'ERRORE';
			}

			return $normalized_response;

		} catch (Exception $e) {
			// Log dell'errore SOAP per troubleshooting
			error_log('WCCDC SOAP ERROR insert_isbn - Exception: ' . $e->getMessage());
			if (isset($e->detail)) {
				error_log('WCCDC SOAP ERROR insert_isbn - Exception detail: ' . print_r($e->detail, true));
			}
			if (isset($e->faultstring)) {
				error_log('WCCDC SOAP ERROR insert_isbn - Fault string: ' . $e->faultstring);
			}
			throw $e;
		}
	}
}
