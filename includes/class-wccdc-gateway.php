<?php
/**
 * Estende la classe WC_Payment_Gateway di WooCommerce aggiungendo il nuovo gateway "Carta della Cultura".
 *
 * @author ilGhera
 * @package wc-carta-della-cultura/includes
 *
 * @since 1.4.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCCDC_Gateway class
 *
 * @since 1.4.6
 */
class WCCDC_Gateway extends WC_Payment_Gateway {

	/**
	 * The constructor
	 *
	 * @return void
	 */
	public function __construct() {

		$this->plugin_id          = 'woocommerce_carta_della_cultura';
		$this->id                 = 'carta-della-cultura';
		$this->has_fields         = true;
		$this->method_title       = __( 'Carta della Cultura', 'ilghera-carta-della-cultura-for-woocommerce' );
		$this->method_description = __( 'Consente ai beneficiari della Carta della Cultura di utilizzare il buono per l\'acquisto di libri muniti di codice ISBN.', 'ilghera-carta-della-cultura-for-woocommerce' );

		if ( get_option( 'wccdc-image' ) ) {

			$this->icon = WCCDC_URI . 'images/carta-della-cultura.png';

		}

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		/* Actions */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_code' ), 10, 1 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'display_code' ), 10, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_code' ), 10, 1 );

		/* Filters */
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'unset_wccdc_gateway' ) );

	}

	/**
	 * Disabilita il metodo di pagamento se i prodotti a carrello non sono nella categoria "libri"
	 * O se i prodotti hanno ISBN non validi (quando configurato)
	 *
	 * @param array $available_gateways I metodi di pagamento disponibili.
	 *
	 * @return array I metodi aggiornati
	 */
	public function unset_wccdc_gateway( $available_gateways ) {

		if ( is_admin() || ! is_checkout() ) {
			return $available_gateways;
		}

		// Controllo categorie (solo se opzione premium "Controllo prodotti" attiva)
		if ( get_option( 'wccdc-items-check' ) ) {
			$categories = get_option( 'wccdc-categories' );
			if ( empty( $categories ) ) {
				unset( $available_gateways['carta-della-cultura'] );
				return $available_gateways;
			}

			// Estrae solo gli ID delle categorie mappate per "libri"
			$allowed_cat_ids = array();
			foreach ( $categories as $cat ) {
				if ( is_array( $cat ) && isset( $cat['libri'] ) ) {
					$allowed_cat_ids[] = $cat['libri'];
				}
			}

			// Se non ci sono categorie consentite, disabilita il gateway
			if ( empty( $allowed_cat_ids ) ) {
				unset( $available_gateways['carta-della-cultura'] );
				return $available_gateways;
			}

			// Verifica che tutti i prodotti nel carrello appartengano ad almeno una delle categorie consentite
			foreach ( WC()->cart->get_cart_contents() as $cart_item_key => $values ) {
				$product_id = $values['product_id'];
				$terms      = get_the_terms( $product_id, 'product_cat' );
				$product_cat_ids = array();

				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$product_cat_ids[] = $term->term_id;
					}
				}

				// Controlla se il prodotto ha almeno una categoria tra quelle consentite
				$intersect = array_intersect( $product_cat_ids, $allowed_cat_ids );
				if ( empty( $intersect ) ) {
					// Prodotto non consentito: disabilita il gateway
					unset( $available_gateways['carta-della-cultura'] );
					return $available_gateways;
				}
			}
		}

		// Validazione ISBN (sempre attiva - necessaria per conformità CdC)
		$isbn_primary_source = get_option( 'wccdc-isbn-primary-source', 'wc_native' );
		$isbn_field = get_option( 'wccdc-isbn-field' );

		// Flag per verificare che tutti i prodotti abbiano ISBN valido
		$all_have_valid_isbn = true;

		// Verifica ISBN per ogni prodotto nel carrello
		foreach ( WC()->cart->get_cart_contents() as $cart_item_key => $values ) {
			$product = $values['data'];
			if ( ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			$isbn = null;
			$clean_isbn = null;

			// 1. Se fonte principale è "wc_native", cerca il campo nativo WooCommerce
			if ( $isbn_primary_source === 'wc_native' ) {
				if ( method_exists( $product, 'get_global_unique_id' ) ) {
					$isbn = $product->get_global_unique_id();
					// Se vuoto e siamo in una variante, prova dal padre
					if ( empty( $isbn ) && $product->is_type( 'variation' ) ) {
						$parent_id = $product->get_parent_id();
						if ( $parent_id ) {
							$parent_product = wc_get_product( $parent_id );
							if ( $parent_product && method_exists( $parent_product, 'get_global_unique_id' ) ) {
								$isbn = $parent_product->get_global_unique_id();
							}
						}
					}
				}
			}
			// 2. Se fonte principale è "custom" e campo ISBN configurato
			elseif ( $isbn_primary_source === 'custom' && ! empty( $isbn_field ) && $isbn_field !== 'none' ) {
				// Se è un campo personalizzato (custom)
				if ( $isbn_field === 'custom' ) {
					$isbn_field = get_option( 'wccdc-custom-isbn-field-value' );
					if ( empty( $isbn_field ) ) {
						// Campo personalizzato vuoto: nascondi gateway per sicurezza
						unset( $available_gateways['carta-della-cultura'] );
						return $available_gateways;
					}
				}

				// Ottieni ISBN usando la funzione pubblica
				$soap_client = new WCCDC_Soap_Client( '', 0 ); // Voucher e importo non necessari per questa chiamata
				$isbn = $soap_client->get_isbn_from_product( $product, $isbn_field );
			}

			// Se non abbiamo ISBN, nascondi gateway
			if ( empty( $isbn ) ) {
				$all_have_valid_isbn = false;
				break;
			}

			// Pulisci ISBN (rimuovi spazi, trattini)
			$clean_isbn = preg_replace( '/[^0-9]/', '', $isbn );
			// Valida lunghezza (13 cifre)
			if ( strlen( $clean_isbn ) !== 13 ) {
				// ISBN non valido
				$all_have_valid_isbn = false;
				break;
			}
			// Valida checksum ISBN-13
			if ( ! self::validate_isbn_13( $clean_isbn ) ) {
				// ISBN checksum non valido
				$all_have_valid_isbn = false;
				break;
			}
		}

		// Se almeno un prodotto non ha ISBN valido, nascondi gateway
		if ( ! $all_have_valid_isbn ) {
			unset( $available_gateways['carta-della-cultura'] );
			return $available_gateways;
		}

		return $available_gateways;
	}

	/**
	 * Campi relativi al sistema di pagamento, modificabili nel back-end
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters(
			'wc_offline_form_fields',
			array(
				'enabled'     => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Abilita pagamento con Carta della Cultura', 'ilghera-carta-della-cultura-for-woocommerce' ),
					'default' => 'yes',
				),
				'title'       => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'ilghera-carta-della-cultura-for-woocommerce' ),
					'default'     => __( 'Carta della Cultura', 'ilghera-carta-della-cultura-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'   => __( 'Messaggio utente', 'woocommerce' ),
					'type'    => 'textarea',
					'default' => '',
				),
			)
		);

	}

	/**
	 * Campo per l'inserimento del buono nella pagina di checkout
	 */
	public function payment_fields() {
		?>
		<p>
			<?php echo wp_kses_post( $this->description ); ?>
			<label for="wc-codice-carta-della-cultura">
				<?php esc_html_e( 'Inserisci qui il tuo codice', 'ilghera-carta-della-cultura-for-woocommerce' ); ?>
				<span class="required">*</span>
			</label>
			<input type="text" class="wc-codice-carta-della-cultura" id="wc-codice-carta-della-cultura" name="wc-codice-carta-della-cultura" />
		</p>
		<?php
	}

	/**
	 * Restituisce le categorie prodotto corrispondenti al bene "libri"
	 *
	 * @param array $categories gli abbinamenti di categoria salvati nel db.
	 *
	 * @return array gli ID di categoria acquistabili
	 */
	public static function get_purchasable_cats( $categories = null ) {

		$wccdc_categories = is_array( $categories ) ? $categories : get_option( 'wccdc-categories' );
		$output = array();

		if ( $wccdc_categories ) {
			foreach ( $wccdc_categories as $cat ) {
				if ( is_array( $cat ) && isset( $cat['libri'] ) ) {
					$output[] = $cat['libri'];
				}
			}
		}

		return $output;
	}

	/**
	 * Valida checksum ISBN-13
	 *
	 * @param string $isbn ISBN pulito (solo cifre).
	 *
	 * @return bool
	 */
	private static function validate_isbn_13( $isbn ) {
		// Algoritmo di validazione ISBN-13
		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$digit = (int) $isbn[ $i ];
			// Moltiplicatore: 1 per posizioni dispari (0-based), 3 per pari
			$multiplier = ( $i % 2 === 0 ) ? 1 : 3;
			$sum += $digit * $multiplier;
		}
		$checksum = ( 10 - ( $sum % 10 ) ) % 10;
		return $checksum === (int) $isbn[12];
	}

	/**
	 * Tutti i prodotti dell'ordine devono essere della tipologia (cat) consentita dal buono Carta della Cultura.
	 *
	 * @param  object $order the WC order.
	 *
	 * @return bool
	 */
	public static function is_purchasable( $order ) {

		$wccdc_categories = get_option( 'wccdc-categories' );
		$cats            = self::get_purchasable_cats( $wccdc_categories );
		$items           = $order->get_items();
		$output          = true;

		if ( is_array( $cats ) && ! empty( $wccdc_categories ) ) {

			foreach ( $items as $item ) {
				$terms = get_the_terms( $item['product_id'], 'product_cat' );
				$ids   = array();

				if ( is_array( $terms ) ) {

					foreach ( $terms as $term ) {

						$ids[] = $term->term_id;

					}
				}

				$results = array_intersect( $ids, $cats );

				if ( ! is_array( $results ) || empty( $results ) ) {

					$output = false;
					continue;

				}
			}
		}

		return $output;

	}

	/**
	 * Mostra il buono Carta della Cultura nella thankyou page, nelle mail e nella pagina dell'ordine.
	 *
	 * @param  object $order the WC order.
	 *
	 * @return void
	 */
	public function display_code( $order ) {

		$data = $order->get_data();

		if ( 'carta-della-cultura' === $data['payment_method'] ) {

			echo '<p><strong>' . esc_html__( 'Carta della Cultura', 'ilghera-carta-della-cultura-for-woocommerce' ) . ': </strong>' . esc_html( $order->get_meta( 'wc-codice-carta-della-cultura' ) ) . '</p>';

		}

	}

	/**
	 * Processa il buono Carta della Cultura inserito
	 *
	 * @param int    $order_id     l'id dell'ordine.
	 * @param string $wccdc_code il buono Carta della Cultura.
	 * @param float  $import       il totale dell'ordine o il valore del coupon.
	 *
	 * @return mixed string in caso di errore, 1 in alternativa
	 */
	public static function process_code( $order_id, $wccdc_code, $import, $converted = false, $complete = false ) {

		global $woocommerce;

		$output      = 1;
		$order       = wc_get_order( $order_id );
		$soap_client = new WCCDC_Soap_Client( $wccdc_code, $import );

		try {

			/*Prima verifica del buono*/
			$response      = $soap_client->check( 1, $order_id );
			$bene          = $response->checkResp->ambito; // Il bene acquistabile con il buono inserito.
			$importo_buono = floatval( $response->checkResp->importo ); // L'importo del buono inserito.
			$operation     = null;

			// Controllo di sicurezza: il buono non deve superare 100€
			if ( $importo_buono > 100.00 ) {
				return __( 'Il valore del buono Carta della Cultura non può superare 100€.', 'ilghera-carta-della-cultura-for-woocommerce' );
			}

			/*Verifica se i prodotti dell'ordine sono compatibili con i beni acquistabili con il buono*/
			$purchasable = self::is_purchasable( $order );

			if ( ! $purchasable ) {

				$output = __( 'Uno o più prodotti nel carrello non sono acquistabili con il buono inserito.', 'ilghera-carta-della-cultura-for-woocommerce' );

			} else {

				try {

					/* Validazione buono */
					$operation = $soap_client->confirm();

					if ( is_object( $operation ) && 'OK' === $operation->checkResp->esito ) {

						/*Aggiungo il buono all'ordine*/
						$order->update_meta_data( 'wc-codice-carta-della-cultura', $wccdc_code );

						/* Se ci sono prodotti con ISBN, invia gli ISBN */
						$isbn_field = get_option( 'wccdc-isbn-field', '' );
						if ( $isbn_field ) {
							try {
								$soap_client->insert_isbn( $order_id );
							} catch ( Exception $e ) {
								// Log dell'errore ma non bloccare l'ordine
								error_log( 'WCCDC ISBN insertion error: ' . $e->getMessage() );
							}
						}

						/* Ordine completato */
						$order->payment_complete();

						/*Svuota carrello*/
						$woocommerce->cart->empty_cart();

					} else {

						$output = $operation->checkResp->esito;
					}
				} catch ( Exception $e ) {

					$output = $e->detail->FaultVoucher->exceptionMessage;

				}
			}
		} catch ( Exception $e ) {

			$output = $e->detail->FaultVoucher->exceptionMessage;

		}

		return $output;

	}

	/**
	 * Gestisce il processo di pagamento, verificando la validità del buono inserito dall'utente
	 *
	 * @param  int $order_id l'id dell'ordine.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order  = wc_get_order( $order_id );
		$import = floatval( $order->get_total() );
		$notice = null;
		$output = array(
			'result'   => 'failure',
			'redirect' => '',
		);

		$data         = $this->get_post_data();
		$wccdc_code = $data['wc-codice-carta-della-cultura']; // Il buono inserito dall'utente.

		if ( $wccdc_code ) {

			$notice = self::process_code( $order_id, $wccdc_code, $import );

			if ( 1 === intval( $notice ) ) {

				$output = array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);

			} else {

				/* translators: %s: Error message from voucher validation */
				wc_add_notice( sprintf( __( 'Carta della Cultura - %s', 'ilghera-carta-della-cultura-for-woocommerce' ), $notice ), 'error' );

			}
		}

		return $output;

	}

}
