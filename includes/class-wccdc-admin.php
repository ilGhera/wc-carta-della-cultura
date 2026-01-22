<?php
/**
 * Pagina opzioni e gestione certificati
 *
 * @author ilGhera
 * @package wc-carta-della-cultura/includes
 *
 * @since 1.4.7
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCCDC_Admin class
 *
 * @since 1.4.7
 */
class WCCDC_Admin {

	/**
	 * The sandbox option
	 *
	 * @var bool
	 */
	private $sandbox;

	/**
	 * The constructor
	 *
	 * @return void
	 */
	public function __construct() {

		$this->sandbox = get_option( 'wccdc-sandbox' );

		add_action( 'admin_init', array( $this, 'wccdc_save_settings' ) );
		add_action( 'admin_init', array( $this, 'generate_cert_request' ) );
		add_action( 'admin_init', array( $this, 'scan_isbn_fields' ) );
		add_action( 'admin_menu', array( $this, 'register_options_page' ) );
		add_action( 'wp_ajax_wccdc-delete-certificate', array( $this, 'delete_certificate_callback' ), 1 );
		add_action( 'wp_ajax_wccdc-add-cat', array( $this, 'add_cat_callback' ) );
		add_action( 'wp_ajax_wccdc-sandbox', array( $this, 'sandbox_callback' ) );
		add_action( 'wp_ajax_wccdc_rescan_isbn', array( $this, 'rescan_isbn_callback' ) );
	}

	/**
	 * Registra la pagina opzioni del plugin
	 *
	 * @return void
	 */
	public function register_options_page() {

		add_submenu_page( 'woocommerce', __( 'ilGhera Carta della Cultura for WooCommerce - Impostazioni', 'ilghera-carta-della-cultura-for-woocommerce' ), __( 'Carta della Cultura for WC', 'ilghera-carta-della-cultura-for-woocommerce' ), 'manage_options', 'wccdc-settings', array( $this, 'wccdc_settings' ) );

	}

	/**
	 * Verifica la presenza di un file per estenzione
	 *
	 * @param string $ext l,estensione del file da cercare.
	 *
	 * @return string l'url file
	 */
	public static function get_the_file( $ext ) {

		$files = array();

		foreach ( glob( WCCDC_PRIVATE . '*' . $ext ) as $file ) {
			$files[] = $file;
		}

		$output = empty( $files ) ? false : $files[0];

		return $output;

	}

	/**
	 * Cancella il certificato
	 *
	 * @return void
	 */
	public function delete_certificate_callback() {

		if ( isset( $_POST['wccdc-delete'], $_POST['delete-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['delete-nonce'] ) ), 'wccdc-del-cert-nonce' ) ) {

			$cert = isset( $_POST['cert'] ) ? sanitize_text_field( wp_unslash( $_POST['cert'] ) ) : '';

			if ( $cert ) {

				unlink( WCCDC_PRIVATE . $cert );

			}
		}

		exit;

	}

	/**
	 * Restituisce il nome esatto del bene Carta della Cultura partendo dallo slug
	 *
	 * @param  array  $beni      l'elenco dei beni di Carta della Cultura.
	 * @param  string $bene_slug lo slug del bene.
	 *
	 * @return string
	 */
	public function get_bene_lable( $beni, $bene_slug ) {
		return 'Libri'; // Sempre "Libri"
	}

	/**
	 * Categoria per la verifica in fase di checkout
	 *
	 * @param  int   $n               il numero dell'elemento aggiunto.
	 * @param  array $data            bene e categoria come chiave e valore.
	 * @param  array $exclude_beni    buoni già abbinati a categorie WC (al momento non utilizzato).
	 * @param  array $exclude_categories categorie già selezionate da escludere.
	 *
	 * @return mixed
	 */
	public function setup_cat( $n, $data = null, $exclude_beni = null, $exclude_categories = null ) {

		echo '<li class="setup-cat cat-' . esc_attr( $n ) . '">';

			$terms      = get_terms( 'product_cat' );
			$term_value = is_array( $data ) && isset( $data['libri'] ) ? $data['libri'] : '';

			// Se $exclude_categories non è fornito, calcolalo dalle categorie già selezionate nella pagina
			if ( $exclude_categories === null ) {
				$exclude_categories = array();
				// Non possiamo accedere alle altre dropdown direttamente qui, quindi questo sarà gestito via JavaScript
				// Ma per coerenza manteniamo il parametro
			}

			echo '<select name="wccdc-categories-' . esc_attr( $n ) . '" class="wccdc-field categories">';
				echo '<option value="">Categoria WooCommerce</option>';

			foreach ( $terms as $term ) {
				// Escludi categorie già selezionate se specificato
				if ( is_array( $exclude_categories ) && in_array( $term->term_id, $exclude_categories ) ) {
					continue;
				}
				
				echo '<option value="' . esc_attr( $term->term_id ) . '"' . ( intval( $term_value ) === $term->term_id ? ' selected="selected"' : '' ) . '>' . esc_html( $term->name ) . '</option>';
			}
			echo '</select>';

			// Campo nascosto per salvare automaticamente il bene "libri"
			echo '<input type="hidden" name="wccdc-beni-' . esc_attr( $n ) . '" value="libri">';

			if ( 1 === intval( $n ) ) {

				echo '<div class="add-cat-container">';
					echo '<img class="add-cat" src="' . esc_url( WCCDC_URI . 'images/add-cat.png' ) . '">';
					echo '<img class="add-cat-hover wccdc" src="' . esc_url( WCCDC_URI . 'images/add-cat-hover.png' ) . '">';
				echo '</div>';

			} else {

				echo '<div class="remove-cat-container">';
					echo '<img class="remove-cat" src="' . esc_url( WCCDC_URI . 'images/remove-cat.png' ) . '">';
					echo '<img class="remove-cat-hover" src="' . esc_url( WCCDC_URI . 'images/remove-cat-hover.png' ) . '">';
				echo '</div>';

			}

			echo '</li>';
	}

	/**
	 * Aggiunge una nuova categoria per la verifica in fase di checkout
	 *
	 * @return void
	 */
	public function add_cat_callback() {

		if ( isset( $_POST['add-cat-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['add-cat-nonce'] ) ), 'wccdc-add-cat-nonce' ) ) {

			$number             = isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '';
			$exclude_beni       = isset( $_POST['exclude-beni'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude-beni'] ) ) : '';
			$exclude_categories = isset( $_POST['exclude-categories'] ) ? array_map( 'intval', (array) $_POST['exclude-categories'] ) : array();

			if ( $number ) {

				$this->setup_cat( $number, null, $exclude_beni, $exclude_categories );

			}
		}

		exit;
	}

	/**
	 * Trasforma il contenuto di un certificato .pem in .der
	 *
	 * @param  string $pem_data il certificato .pem.
	 *
	 * @return string
	 */
	public function pem2der( $pem_data ) {

		$begin    = '-----BEGIN CERTIFICATE REQUEST-----';
		$end      = '-----END CERTIFICATE REQUEST-----';
		$pem_data = substr( $pem_data, strpos( $pem_data, $begin ) + strlen( $begin ) );
		$pem_data = substr( $pem_data, 0, strpos( $pem_data, $end ) );
		$der      = base64_decode( $pem_data );

		return $der;
	}

	/**
	 * Download della richiesta di certificato da utilizzare sul portale Carta della Cultura
	 * Se non presenti, genera la chiave e la richiesta di certificato .der,
	 *
	 * @return void
	 */
	public function generate_cert_request() {

		if ( isset( $_POST['wccdc-generate-der-hidden'], $_POST['wccdc-generate-der-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccdc-generate-der-nonce'] ) ), 'wccdc-generate-der' ) ) {

			/*Crea il file .der*/
			$country_name             = isset( $_POST['countryName'] ) ? sanitize_text_field( wp_unslash( $_POST['countryName'] ) ) : '';
			$state_or_provice_name    = isset( $_POST['stateOrProvinceName'] ) ? sanitize_text_field( wp_unslash( $_POST['stateOrProvinceName'] ) ) : '';
			$locality_name            = isset( $_POST['localityName'] ) ? sanitize_text_field( wp_unslash( $_POST['localityName'] ) ) : '';
			$organization_name        = isset( $_POST['organizationName'] ) ? sanitize_text_field( wp_unslash( $_POST['organizationName'] ) ) : '';
			$organizational_unit_name = isset( $_POST['organizationalUnitName'] ) ? sanitize_text_field( wp_unslash( $_POST['organizationalUnitName'] ) ) : '';
			$common_name              = isset( $_POST['commonName'] ) ? sanitize_text_field( wp_unslash( $_POST['commonName'] ) ) : '';
			$email_address            = isset( $_POST['emailAddress'] ) ? sanitize_text_field( wp_unslash( $_POST['emailAddress'] ) ) : '';
			$wccdc_password            = isset( $_POST['wccdc-password'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-password'] ) ) : '';

			/*Salvo passw nel db*/
			if ( $wccdc_password ) {
				update_option( 'wccdc-password', base64_encode( $wccdc_password ) );
			}

			$dn = array(
				'countryName'            => $country_name,
				'stateOrProvinceName'    => $state_or_provice_name,
				'localityName'           => $locality_name,
				'organizationName'       => $organization_name,
				'organizationalUnitName' => $organizational_unit_name,
				'commonName'             => $common_name,
				'emailAddress'           => $email_address,
			);

			/*Genera la private key*/
			$privkey = openssl_pkey_new(
				array(
					'private_key_bits' => 2048,
					'private_key_type' => OPENSSL_KEYTYPE_RSA,
				)
			);

			/*Genera ed esporta la richiesta di certificato .pem*/
			$csr = openssl_csr_new( $dn, $privkey, array( 'digest_alg' => 'sha256' ) );
			openssl_csr_export_to_file( $csr, WCCDC_PRIVATE . 'files/certificate-request.pem' );

			/*Trasforma la richiesta di certificato in .der*/
			$csr_der = $this->pem2der( file_get_contents( WCCDC_PRIVATE . 'files/certificate-request.pem' ) );

			/*Preparo il backup*/
			$bu_folder            = WCCDC_PRIVATE . 'files/backups/';
			$bu_new_folder_name   = count( glob( $bu_folder . '*', GLOB_ONLYDIR ) ) + 1;
			$bu_new_folder_create = wp_mkdir_p( trailingslashit( $bu_folder . $bu_new_folder_name ) );

			/*Salvo file di backup*/
			if ( $bu_new_folder_create ) {

				/*Esporta la richiesta di certificato .der*/
				file_put_contents( WCCDC_PRIVATE . 'files/backups/' . $bu_new_folder_name . '/certificate-request.der', $csr_der );

				/*Esporta la private key*/
				openssl_pkey_export_to_file( $privkey, WCCDC_PRIVATE . 'files/backups/' . $bu_new_folder_name . '/key.der' );

			}

			/*Esporta la richiesta di certificato .der*/
			file_put_contents( WCCDC_PRIVATE . 'files/certificate-request.der', $csr_der );

			/*Esporta la private key*/
			openssl_pkey_export_to_file( $privkey, WCCDC_PRIVATE . 'files/key.der' );

			/*Download file .der*/
			$cert_req_url = WCCDC_PRIVATE . 'files/certificate-request.der';

			if ( $cert_req_url ) {
				header( 'Content-Description: File Transfer' );
				header( 'Content-Type: application/octet-stream' );
				header( 'Content-Transfer-Encoding: binary' );
				header( 'Content-disposition: attachment; filename="' . basename( $cert_req_url ) . '"' );
				header( 'Expires: 0' );
				header( 'Cache-Control: must-revalidate' );
				header( 'Pragma: public' );

				readfile( $cert_req_url );

				exit;
			}
		}
	}

	/**
	 * Attivazione certificato
	 *
	 * @return string
	 */
	public function wccdc_cert_activation() {

		$soap_client = new WCCDC_Soap_Client( '11aa22bb', '' );

		try {

			$operation = $soap_client->check( 1 );
			return 'ok';

		} catch ( Exception $e ) {

			$notice = isset( $e->detail->FaultVoucher->exceptionMessage ) ? $e->detail->FaultVoucher->exceptionMessage : $e->faultstring;
			error_log( 'Error wccdc_cert_activation: ' . print_r( $e, true ) );

			return $notice;

		}
	}

	/**
	 * Funzionalita Sandbox
	 *
	 * @return void
	 */
	public function sandbox_callback() {

		if ( isset( $_POST['sandbox'], $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wccdc-sandbox' ) ) {

			$this->sandbox = sanitize_text_field( wp_unslash( $_POST['sandbox'] ) );

			update_option( 'wccdc-sandbox', $this->sandbox );
			update_option( 'wccdc-cert-activation', $this->sandbox );

		}

		exit();

	}

	/**
	 * Scansiona i prodotti per identificare campi meta e attributi che potrebbero contenere ISBN
	 *
	 * @return void
	 */
	public function scan_isbn_fields() {
		// Esegui la scansione solo una volta al giorno
		$last_scan = get_option( 'wccdc-isbn-scan-timestamp', 0 );
		if ( time() - $last_scan < DAY_IN_SECONDS ) {
			return;
		}

		global $wpdb;

		$candidates = array();

		// 1. Cerca nei CUSTOM FIELDS (post_meta)
		$meta_keys = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_key
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE p.post_type = 'product'
			   AND pm.meta_key NOT LIKE '\_%'
			   AND pm.meta_key NOT IN ('total_sales', '_stock_status')
			 ORDER BY pm.meta_key"
		);

		foreach ( $meta_keys as $meta_key ) {
			$sample_values = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pm.meta_value
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					 WHERE p.post_type = 'product'
					   AND pm.meta_key = %s
					   AND pm.meta_value IS NOT NULL
					   AND pm.meta_value != ''
					 LIMIT 5",
					$meta_key
				)
			);

			$isbn_count = 0;
			$total_count = count( $sample_values );

			foreach ( $sample_values as $value ) {
				$clean_value = preg_replace( '/[^0-9]/', '', $value );
				if ( preg_match( '/^[0-9]{13}$/', $clean_value ) ) {
					$isbn_count++;
				}
			}

			// Se almeno il 50% dei campioni sembra ISBN, aggiungi ai candidati
			if ( $total_count > 0 && ( $isbn_count / $total_count ) >= 0.5 ) {
				$candidates['meta'][ $meta_key ] = array(
					'sample' => $sample_values[0] ?? '',
					'count'  => $isbn_count,
					'type'   => 'meta',
				);
			}
		}

		// 2. Cerca negli ATTRIBUTI DI PRODOTTO (tassonomie)
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		
		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $tax->attribute_name );
			
			// Ottieni alcuni termini di questa tassonomia
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy_name,
				'hide_empty' => false,
				'number'     => 5,
			) );
			
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			
			$isbn_count = 0;
			$total_count = count( $terms );
			$sample_values = array();
			
			foreach ( $terms as $term ) {
				$sample_values[] = $term->name;
				$clean_value = preg_replace( '/[^0-9]/', '', $term->name );
				if ( preg_match( '/^[0-9]{13}$/', $clean_value ) ) {
					$isbn_count++;
				}
			}
			
			// Se almeno il 50% dei campioni sembra ISBN, aggiungi ai candidati
			if ( $total_count > 0 && ( $isbn_count / $total_count ) >= 0.5 ) {
				$candidates['attribute'][ $taxonomy_name ] = array(
					'sample' => $sample_values[0] ?? '',
					'count'  => $isbn_count,
					'type'   => 'attribute',
					'label'  => $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name,
				);
			}
		}

		// DEBUG: Log per vedere cosa è stato trovato
		error_log('WCCDC ISBN Scan Results: ' . print_r($candidates, true));
		
		// Se non trova nulla, mostra almeno alcuni campi comuni
		if ( empty( $candidates['meta'] ) && empty( $candidates['attribute'] ) ) {
			// Cerca campi comuni che potrebbero contenere ISBN
			$common_fields = array('isbn', 'ISBN', 'codice_isbn', '_isbn', 'isbn_code');
			foreach ( $common_fields as $field ) {
				$candidates['meta'][ $field ] = array(
					'sample' => '',
					'count'  => 0,
					'type'   => 'meta',
				);
			}
		}

		// Salva i candidati trovati
		update_option( 'wccdc-isbn-candidates', $candidates );
		update_option( 'wccdc-isbn-scan-timestamp', time() );
	}

	/**
	 * Pagina opzioni plugin
	 *
	 * @return void
	 */
	public function wccdc_settings() {

		/*Recupero le opzioni salvate nel db*/
		$premium_key               = get_option( 'wccdc-premium-key' );
		$passphrase                = base64_decode( get_option( 'wccdc-password' ) );
		$categories                = get_option( 'wccdc-categories' );
		$tot_cats                  = $categories ? count( $categories ) : 0;
		$wccdc_image                = get_option( 'wccdc-image' );
		$wccdc_items_check          = get_option( 'wccdc-items-check' );
		$wccdc_orders_on_hold       = get_option( 'wccdc-orders-on-hold' );
		$wccdc_exclude_shipping     = get_option( 'wccdc-exclude-shipping' );
		$wccdc_coupon               = get_option( 'wccdc-coupon' );
		$wccdc_email_subject        = get_option( 'wccdc-email-subject' );
		$wccdc_email_heading        = get_option( 'wccdc-email-heading' );
		$wccdc_email_order_received = get_option( 'wccdc-email-order-received' );
		$wccdc_email_order_failed   = get_option( 'wccdc-email-order-failed' );
		$wccdc_isbn_field           = get_option( 'wccdc-isbn-field', 'none' );

		echo '<div class="wrap">';
			echo '<div class="wrap-left">';
				echo '<h1>ilGhera Carta della Cultura for WooCommerce- ' . esc_html( __( 'Impostazioni', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</h1>';

				/*Premium key form*/
				echo '<form method="post" action="">';
					echo '<table class="form-table wccdc-table">';
						echo '<th scope="row">' . esc_html__( 'Premium Key', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" class="regular-text code" name="wccdc-premium-key" id="wccdc-premium-key" placeholder="' . esc_attr__( 'Inserisci la tua Premium Key', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" value="' . esc_attr( $premium_key ) . '" />';
							echo '<p class="description">' . wp_kses_post( __( 'Aggiungi la tua Premium Key e mantieni aggiornato <strong>ilGhera Carta della Cultura for Woocommerce - Premium</strong>.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';

							wp_nonce_field( 'wccdc-premium-key', 'wccdc-premium-key-nonce' );

							echo '<input type="hidden" name="premium-key-sent" value="1" />';
							echo '<input type="submit" class="button button-primary wccdc-button"" value="' . esc_html__( 'Salva ', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" />';
						echo '</td>';
					echo '</table>';
				echo '</form>';

				/*Tabs*/
				echo '<div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"></div>';
				echo '<h2 id="wccdc-admin-menu" class="nav-tab-wrapper woo-nav-tab-wrapper">';
					echo '<a href="#" data-link="wccdc-certificate" class="nav-tab nav-tab-active" onclick="return false;">' . esc_html( __( 'Certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</a>';
					echo '<a href="#" data-link="wccdc-options" class="nav-tab" onclick="return false;">' . esc_html__( 'Opzioni', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</a>';
				echo '</h2>';

				/*Certificate*/
				echo '<div id="wccdc-certificate" class="wccdc-admin" style="display: block;">';

					/*Carica certificato .pem*/
					echo '<h3>' . esc_html__( 'Carica il tuo certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</h3>';
					echo '<p class="description">' . esc_html__( 'Se sei già in posseso di un certificato non devi fare altro che caricarlo con relativa password, nient\'altro.', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

					echo '<form name="wccdc-upload-certificate" class="wccdc-upload-certificate one-of" method="post" enctype="multipart/form-data" action="">';
						echo '<table class="form-table wccdc-table">';

							/*Carica certificato*/
							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Carica certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
		if ( $file = self::get_the_file( '.pem' ) ) {

			$activation = $this->wccdc_cert_activation();

			if ( 'ok' === $activation ) {

				echo '<span class="cert-loaded">' . esc_html( basename( $file ) ) . '</span>';
				echo '<a class="button delete wccdc-delete-certificate">' . esc_html__( 'Elimina', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</a>';
				echo '<p class="description">' . esc_html__( 'File caricato e attivato correttamente.', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

				update_option( 'wccdc-cert-activation', 1 );

			} else {

				echo '<span class="cert-loaded error">' . esc_html( basename( $file ) ) . '</span>';
				echo '<a class="button delete wccdc-delete-certificate">' . esc_html__( 'Elimina', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</a>';

				/* Translators: the error message */
				echo '<p class="description">' . sprintf( esc_html__( 'L\'attivazione del certificato ha restituito il seguente errore: %s', 'ilghera-carta-della-cultura-for-woocommerce' ), esc_html( $activation ) ) . '</p>';

				delete_option( 'wccdc-cert-activation' );

			}
		} else {

			echo '<input type="file" accept=".pem" name="wccdc-certificate" class="wccdc-certificate">';
			echo '<p class="description">' . esc_html__( 'Carica il certificato (.pem) necessario alla connessione con Carta della Cultura', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

		}

								echo '</td>';
							echo '</tr>';

							/*Password utilizzata per la creazione del certificato*/
							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Password', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									echo '<input type="password" name="wccdc-password" placeholder="**********" value="' . esc_attr( $passphrase ) . '" required>';
									echo '<p class="description">' . esc_html__( 'La password utilizzata per la generazione del certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

									wp_nonce_field( 'wccdc-upload-certificate', 'wccdc-certificate-nonce' );

									echo '<input type="hidden" name="wccdc-certificate-hidden" value="1">';
									echo '<input type="submit" class="button-primary wccdc-button" value="' . esc_html__( 'Salva certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '">';
								echo '</td>';
							echo '</tr>';

						echo '</table>';
					echo '</form>';

		/*Se il certificato non è presente vengono mostrati gli strumentui per generarlo*/
		if ( ! self::get_the_file( '.pem' ) ) {

			/*Genera richiesta certificato .der*/
			echo '<h3>' . esc_html__( 'Richiedi un certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'Con questo strumento puoi generare un file .der necessario per richiedere il tuo certificato su Carta della Cultura.', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

			echo '<form id="generate-certificate-request" method="post" class="one-of" enctype="multipart/form-data" action="">';
				echo '<table class="form-table wccdc-table">';
					echo '<tr>';
						echo '<th scope="row">' . esc_html__( 'Stato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="countryName" placeholder="IT" required>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Provincia', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="stateOrProvinceName" placeholder="Es. Milano" required>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Località', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="localityName" placeholder="Es. Legnano" required>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Nome azienda', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="organizationName" placeholder="Es. Taldeitali srl" required>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Reparto azienda', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="organizationalUnitName" placeholder="Es. Vendite" required>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Nome', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="commonName" placeholder="Es. Franco Bianchi" required>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Email', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="email" name="emailAddress" placeholder="Es. franco.bianchi@taldeitali.it" required>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Password', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="password" name="wccdc-password" placeholder="**********" required>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row"></th>';
						echo '<td>';
						wp_nonce_field( 'wccdc-generate-der', 'wccdc-generate-der-nonce' );
						echo '<input type="hidden" name="wccdc-generate-der-hidden" value="1">';
						echo '<input type="submit" name="generate-der" class="button-primary wccdc-button generate-der" value="' . esc_attr__( 'Scarica file .der', 'ilghera-carta-della-cultura-for-woocommerce' ) . '">';
						echo '</td>';
					echo '</tr>';

				echo '</table>';
			echo '</form>';

			/*Genera certificato .pem*/
			echo '<h3>' . esc_html( __( 'Crea il tuo certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'Con questo ultimo passaggio, potrai iniziare a ricevere pagamenti attraverso buoni Carta della Cultura.', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

			echo '<form name="wccdc-generate-certificate" class="wccdc-generate-certificate one-of" method="post" enctype="multipart/form-data" action="">';
				echo '<table class="form-table wccdc-table">';

					/*Carica certificato*/
					echo '<tr>';
						echo '<th scope="row">' . esc_html__( 'Genera certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';

							echo '<input type="file" accept=".cer" name="wccdc-cert" class="wccdc-cert">';
							echo '<p class="description">' . esc_html__( 'Carica il file .cer ottenuto da Carta della Cultura per procedere', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

							wp_nonce_field( 'wccdc-generate-certificate', 'wccdc-gen-certificate-nonce' );

							echo '<input type="hidden" name="wccdc-gen-certificate-hidden" value="1">';
							echo '<input type="submit" class="button-primary wccdc-button" value="' . esc_html__( 'Genera certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '">';

						echo '</td>';
					echo '</tr>';

				echo '</table>';
			echo '</form>';

		}

				echo '</div>';

				/*Modalità Sandbox*/
				echo '<div id="wccdc-sandbox-option" class="wccdc-admin" style="display: block;">';
					echo '<h3>' . esc_html__( 'Modalità Sandbox', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</h3>';
				echo '<p class="description">';
					/* Translators: the email address */
					printf( wp_kses_post( __( 'Attiva questa funzionalità per testare buoni Carta della Cultura in un ambiente di prova.<br>Richiedi i buoni test scrivendo a <a href="%s">numeroverde@beniculturali.it</a>', 'ilghera-carta-della-cultura-for-woocommerce' ) ), 'mailto:numeroverde@beniculturali.it' );
				echo '</p>';

					echo '<form name="wccdc-sandbox" class="wccdc-sandbox" method="post" enctype="multipart/form-data" action="">';
						echo '<table class="form-table wccdc-table">';

							/*Carica certificato*/
							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Sandbox', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td class="wccdc-sandbox-field">';
									echo '<input type="checkbox" name="wccdc-sandbox" class="wccdc-sandbox"' . ( $this->sandbox ? ' checked="checked"' : null ) . '>';
									echo '<p class="description">' . esc_html__( 'Attiva modalità Sandbox', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

									wp_nonce_field( 'wccdc-sandbox', 'wccdc-sandbox-nonce' );

									echo '<input type="hidden" name="wccdc-sandbox-hidden" value="1">';

								echo '</td>';
							echo '</tr>';

						echo '</table>';
					echo '</form>';
				echo '</div>';

				/*Options*/
				echo '<div id="wccdc-options" class="wccdc-admin">';

					echo '<form name="wccdc-options" class="wccdc-form wccdc-options" method="post" enctype="multipart/form-data" action="">';
						echo '<table class="form-table">';

							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Categorie', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';

									echo '<ul  class="categories-container">';

		if ( $categories ) {

			// Raccogli tutte le categorie già selezionate
			$all_selected_categories = array();
			foreach ( $categories as $cat_data ) {
				if ( is_array( $cat_data ) && isset( $cat_data['libri'] ) ) {
					$all_selected_categories[] = $cat_data['libri'];
				}
			}

			for ( $i = 1; $i <= $tot_cats; $i++ ) {
				// Per ogni dropdown, escludi tutte le altre categorie selezionate
				$exclude_for_this_dropdown = $all_selected_categories;
				// Rimuovi la categoria corrente dall'esclusione (perché deve rimanere selezionata)
				$current_cat = isset( $categories[ $i - 1 ]['libri'] ) ? $categories[ $i - 1 ]['libri'] : '';
				if ( $current_cat ) {
					$key = array_search( $current_cat, $exclude_for_this_dropdown );
					if ( $key !== false ) {
						unset( $exclude_for_this_dropdown[$key] );
					}
				}
				
				$this->setup_cat( $i, $categories[ $i - 1 ], null, $exclude_for_this_dropdown );
			}
		} else {

			$this->setup_cat( 1 );

		}

									echo '</ul>';
									echo '<input type="hidden" name="wccdc-tot-cats" class="wccdc-tot-cats" value="' . ( is_array( $categories ) ? esc_attr( count( $categories ) ) : 1 ) . '">';
									echo '<p class="description">' . esc_html__( 'Seleziona le categorie di prodotti corrispondenti ai libri acquistabili con Carta della Cultura.', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Fonte ISBN', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									
									$isbn_source = get_option( 'wccdc-isbn-source', 'meta' );
									
									echo '<div style="margin-bottom:15px;">';
										echo '<label style="margin-right:20px;">';
											echo '<input type="radio" name="wccdc-isbn-source" value="meta"' . checked( $isbn_source, 'meta', false ) . ' /> ';
											echo esc_html__( 'Campo personalizzato (post_meta)', 'ilghera-carta-della-cultura-for-woocommerce' );
										echo '</label>';
										echo '<label>';
											echo '<input type="radio" name="wccdc-isbn-source" value="attribute"' . checked( $isbn_source, 'attribute', false ) . ' /> ';
											echo esc_html__( 'Attributo di prodotto', 'ilghera-carta-della-cultura-for-woocommerce' );
										echo '</label>';
										echo '<p class="description">' . esc_html__( 'Scegli dove il plugin deve cercare il codice ISBN nei tuoi prodotti.', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';
									echo '</div>';
									
								echo '</td>';
							echo '</tr>';
							
							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Campo ISBN', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									
									// Ottieni i candidati trovati
									$candidates = get_option( 'wccdc-isbn-candidates', array() );
									$meta_candidates = isset( $candidates['meta'] ) ? $candidates['meta'] : array();
									$attribute_candidates = isset( $candidates['attribute'] ) ? $candidates['attribute'] : array();
									
									echo '<select name="wccdc-isbn-field" class="wccdc-isbn-field">';
										echo '<option value="none"' . selected( $wccdc_isbn_field, 'none', false ) . '>' . 
											 esc_html__( 'Nessuno (non inviare ISBN)', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</option>';
										
										// Mostra solo i campi della fonte selezionata
										$isbn_source = get_option( 'wccdc-isbn-source', 'meta' );
										
										if ( $isbn_source === 'meta' && ! empty( $meta_candidates ) ) {
											echo '<optgroup label="' . esc_attr__( 'Campi personalizzati', 'ilghera-carta-della-cultura-for-woocommerce' ) . '">';
											foreach ( $meta_candidates as $meta_key => $data ) {
												$sample = $data['sample'];
												$truncated_sample = strlen( $sample ) > 20 ? substr( $sample, 0, 20 ) . '...' : $sample;
												$option_label = sprintf(
													'%s (%s)',
													esc_html( $meta_key ),
													esc_html( $truncated_sample )
												);
												echo '<option value="meta:' . esc_attr( $meta_key ) . '"' . selected( $wccdc_isbn_field, 'meta:' . $meta_key, false ) . '>' . 
													 $option_label . '</option>';
											}
											echo '</optgroup>';
										}
										
										if ( $isbn_source === 'attribute' && ! empty( $attribute_candidates ) ) {
											echo '<optgroup label="' . esc_attr__( 'Attributi di prodotto', 'ilghera-carta-della-cultura-for-woocommerce' ) . '">';
											foreach ( $attribute_candidates as $taxonomy => $data ) {
												$sample = $data['sample'];
												$truncated_sample = strlen( $sample ) > 20 ? substr( $sample, 0, 20 ) . '...' : $sample;
												$label = isset( $data['label'] ) ? $data['label'] : $taxonomy;
												$option_label = sprintf(
													'%s (%s)',
													esc_html( $label ),
													esc_html( $truncated_sample )
												);
												echo '<option value="attribute:' . esc_attr( $taxonomy ) . '"' . selected( $wccdc_isbn_field, 'attribute:' . $taxonomy, false ) . '>' . 
													 $option_label . '</option>';
											}
											echo '</optgroup>';
										}
										
										// Opzione per inserire manualmente un campo non rilevato
										echo '<option value="custom"' . selected( $wccdc_isbn_field, 'custom', false ) . '>' . 
											 esc_html__( 'Inserisci manualmente...', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</option>';
										
									echo '</select>';
									
									// Campo di testo per inserimento manuale (visibile solo se selezionato "custom")
									echo '<div id="wccdc-custom-isbn-field" style="margin-top:10px;' . ( $wccdc_isbn_field === 'custom' ? '' : 'display:none;' ) . '">';
										echo '<input type="text" name="wccdc-custom-isbn-field-value" value="' . 
											 esc_attr( get_option( 'wccdc-custom-isbn-field-value', '' ) ) . '" placeholder="' . 
											 esc_attr__( 'es. isbn, codice_isbn, _isbn oppure pa_isbn', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" />';
										echo '<p class="description">' . esc_html__( 'Inserisci il nome esatto del campo meta o dell\'attributo che contiene l\'ISBN', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';
									echo '</div>';
									
									echo '<p class="description">' . 
										 esc_html__( 'Seleziona il campo che contiene il codice ISBN nei tuoi prodotti. Il plugin ha scansionato automaticamente i campi disponibili.', 'ilghera-carta-della-cultura-for-woocommerce' ) . 
										 '</p>';
									
									// Pulsante per forzare una nuova scansione
									echo '<p>';
										echo '<a href="#" id="wccdc-rescan-isbn" class="button button-secondary">' . 
											 esc_html__( 'Riesamina campi', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</a>';
										echo '<span class="spinner" style="float:none;margin-left:5px;"></span>';
										echo '<span id="wccdc-rescan-message" style="margin-left:10px;"></span>';
									echo '</p>';
									
									// DEBUG: Mostra i campi trovati
									echo '<div style="margin-top:10px; padding:10px; background:#f5f5f5; border:1px solid #ddd;">';
										echo '<strong>' . esc_html__( 'DEBUG - Campi trovati:', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</strong><br>';
										$candidates_debug = get_option( 'wccdc-isbn-candidates', array() );
										if ( empty( $candidates_debug ) ) {
											echo esc_html__( 'Nessun campo trovato. La scansione potrebbe non essere ancora stata eseguita.', 'ilghera-carta-della-cultura-for-woocommerce' );
										} else {
											echo '<pre style="font-size:11px;">';
											echo esc_html( print_r( $candidates_debug, true ) );
											echo '</pre>';
										}
									echo '</div>';
									
								echo '</td>';
							echo '</tr>';

							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Utilizzo immagine', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									echo '<input type="checkbox" name="wccdc-image" value="1"' . ( 1 === intval( $wccdc_image ) ? ' checked="checked"' : '' ) . '>';
									echo '<p class="description">' . wp_kses_post( __( 'Mostra il logo <i>Carta della Cultura</i> nella pagine di checkout.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Controllo prodotti', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
										echo '<input type="checkbox" name="wccdc-items-check" value="1"' . ( 1 === intval( $wccdc_items_check ) ? ' checked="checked"' : '' ) . '>';
									echo '<p class="description">' . wp_kses_post( __( 'Mostra il metodo di pagamento solo se il/ i prodotti a carrello sono acquistabili con buoni <i>Carta della Cultura</i>.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-orders-on-hold">';
								echo '<th scope="row">' . esc_html__( 'Ordini in sospeso', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
										echo '<input type="checkbox" name="wccdc-orders-on-hold" value="1"' . ( 1 === intval( $wccdc_orders_on_hold ) ? ' checked="checked"' : '' ) . '>';
									echo '<p class="description">' . wp_kses_post( __( 'I buoni Carta della Cultura verranno validati con il completamento manuale degli ordini.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '<tr class="wccdc-exclude-shipping">';
								echo '<th scope="row">' . esc_html__( 'Spese di spedizione', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
										echo '<input type="checkbox" name="wccdc-exclude-shipping" value="1"' . ( 1 === intval( $wccdc_exclude_shipping ) ? ' checked="checked"' : '' ) . '>';
									echo '<p class="description">' . wp_kses_post( __( 'Escludi le spese di spedizione dal pagamento con Carta della Cultura.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-coupon">';
								echo '<th scope="row">' . esc_html__( 'Conversione in coupon', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									echo '<input type="checkbox" name="wccdc-coupon" value="1"' . ( 1 === intval( $wccdc_coupon ) ? ' checked="checked"' : '' ) . '>';
									echo '<p class="description">' . wp_kses_post( __( 'Nel caso in cui il buono <i>Carta della Cultura</i> inserito sia inferiore al totale a carrello, viene convertito in <i>Codice promozionale</i> ed applicato all\'ordine.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-email-order-received wccdc-email-details">';
								echo '<th scope="row">' . esc_html__( 'Ordine ricevuto', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									$default_order_received_message = __( 'L\'ordine verrà completato manualmente nei prossimi giorni e, contestualmente, verrà validato il buono Carta della Cultura inserito. Riceverai una notifica email di conferma, grazie!', 'ilghera-carta-della-cultura-for-woocommerce' );
									echo '<textarea cols="6" rows="6" class="regular-text" name="wccdc-email-order-received" placeholder="' . esc_html( $default_order_received_message ) . '" value="' . esc_html( $wccdc_email_order_received ) . '">' . esc_html( $wccdc_email_order_received ) . '</textarea>';
									echo '<p class="description">';
										echo wp_kses_post( __( 'Messaggio della mail inviata all\'utente al ricevimento dell\'ordine.', 'ilghera-carta-della-cultura-for-woocommerce' ) );
									echo '</p>';
									echo '<div class="wccdc-divider"></div>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-email-subject wccdc-email-details">';
								echo '<th scope="row">' . esc_html__( 'Oggetto email', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
										echo '<input type="text" class="regular-text" name="wccdc-email-subject" placeholder="' . esc_attr__( 'Ordine fallito', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" value="' . esc_attr( $wccdc_email_subject ) . '">';
									echo '<p class="description">' . wp_kses_post( __( 'Oggetto della mail inviata all\'utente nel caso in cui la validazione del buono non sia andata a buon fine.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-email-heading wccdc-email-details">';
								echo '<th scope="row">' . esc_html__( 'Intestazione email', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
										echo '<input type="text" class="regular-text" name="wccdc-email-heading" placeholder="' . esc_attr__( 'Ordine fallito', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" value="' . esc_attr( $wccdc_email_heading ) . '">';
									echo '<p class="description">' . wp_kses_post( __( 'Intestazione della mail inviata all\'utente nel caso in cui la validazione del buono non sia andata a buon fine.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-email-order-failed wccdc-email-details">';
								echo '<th scope="row">' . esc_html__( 'Ordine fallito', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
										$default_order_failed_message = __( 'La validazone del buono Carta della Cultura ha restituito un errore e non è stato possibile completare l\'ordine, effettua il pagamento a <a href="[checkout-url]">questo indirizzo</a>.' );
										echo '<textarea cols="6" rows="6" class="regular-text" name="wccdc-email-order-failed" placeholder="' . esc_html( $default_order_failed_message ) . '" value="' . esc_html( $wccdc_email_order_failed ) . '">' . esc_html( $wccdc_email_order_failed ) . '</textarea>';
										echo '<p class="description">';
											echo '<span class="shortcodes">';
												echo '<code>[checkout-url]</code>';
											echo '</span>';
											echo wp_kses_post( __( 'Messaggio della mail inviata all\'utente nel caso in cui la validazione del buono non sia andata a buon fine.', 'ilghera-carta-della-cultura-for-woocommerce' ) );
										echo '</p>';
								echo '</td>';
							echo '</tr>';

						echo '</table>';

						wp_nonce_field( 'wccdc-save-settings', 'wccdc-settings-nonce' );

						echo '<input type="hidden" name="wccdc-settings-hidden" value="1">';
						echo '<input type="submit" class="button-primary" value="' . esc_html__( 'Salva impostazioni', 'ilghera-carta-della-cultura-for-woocommerce' ) . '">';
					echo '</form>';
				echo '</div>';

			echo '</div>';

			echo '<div class="wrap-right">';
            echo '<iframe width="300" height="1300" scrolling="no" src="http://www.ilghera.com/images/wccdc-premium-iframe.html"></iframe>';
			echo '</div>';
			echo '<div class="clear"></div>';

		echo '</div>';
		
		// Aggiungi script JavaScript per gestire il campo ISBN
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Mostra/nascondi campo manuale quando cambia la selezione
			$('select.wccdc-isbn-field').on('change', function() {
				if ($(this).val() === 'custom') {
					$('#wccdc-custom-isbn-field').show();
				} else {
					$('#wccdc-custom-isbn-field').hide();
				}
			});
			
			// Aggiorna il dropdown quando cambia la fonte ISBN
			$('input[name="wccdc-isbn-source"]').on('change', function() {
				var source = $(this).val();
				var $dropdown = $('select.wccdc-isbn-field');
				var currentValue = $dropdown.val();
				
				// Ricarica la pagina per aggiornare il dropdown con i campi corretti
				// Invia il form per salvare la selezione
				$('form.wccdc-options').append('<input type="hidden" name="wccdc-isbn-source-temp" value="' + source + '">');
				$('form.wccdc-options').submit();
			});
			
			// Riesamina campi ISBN
			$('#wccdc-rescan-isbn').on('click', function(e) {
				e.preventDefault();
				
				var $button = $(this);
				var $spinner = $button.next('.spinner');
				var $message = $('#wccdc-rescan-message');
				
				$spinner.addClass('is-active');
				$message.text('');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wccdc_rescan_isbn',
						nonce: '<?php echo wp_create_nonce( 'wccdc-rescan-isbn' ); ?>'
					},
					success: function(response) {
						$spinner.removeClass('is-active');
						if (response.success) {
							$message.html('<span style="color:#46b450;">' + response.data.message + '</span>');
							// Ricarica la pagina dopo 1.5 secondi
							setTimeout(function() {
								location.reload();
							}, 1500);
						} else {
							$message.html('<span style="color:#dc3232;">' + response.data.message + '</span>');
						}
					},
					error: function() {
						$spinner.removeClass('is-active');
						$message.html('<span style="color:#dc3232;">Errore durante la scansione</span>');
					}
				});
			});
		});
		</script>
		<?php

	}

	/**
	 * Mostra un mesaggio d'errore nel caso in cui il certificato non isa valido
	 *
	 * @return void
	 */
	public function not_valid_certificate() {

		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'ATTENZIONE! Il file caricato non sembra essere un certificato valido.', 'ilghera-carta-della-cultura-for-woocommerce' ); ?></p>
		</div>
		<?php

	}

	/**
	 * AJAX callback per riesaminare i campi ISBN
	 *
	 * @return void
	 */
	public function rescan_isbn_callback() {
		if ( ! check_ajax_referer( 'wccdc-rescan-isbn', 'nonce', false ) ) {
			wp_die( 'Nonce verification failed', 403 );
		}
		
		// Forza una nuova scansione eliminando il timestamp
		delete_option( 'wccdc-isbn-scan-timestamp' );
		$this->scan_isbn_fields();
		
		wp_send_json_success( array(
			'message' => __( 'Scansione completata. La pagina verrà ricaricata.', 'ilghera-carta-della-cultura-for-woocommerce' )
		) );
	}
	
	/**
	 * Salvataggio delle impostazioni dell'utente
	 *
	 * @return void
	 */
	public function wccdc_save_settings() {

		if ( isset( $_POST['premium-key-sent'], $_POST['wccdc-premium-key-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccdc-premium-key-nonce'] ) ), 'wccdc-premium-key' ) ) {

			/*Salvataggio Premium Key*/
			$premium_key = isset( $_POST['wccdc-premium-key'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-premium-key'] ) ) : '';

			update_option( 'wccdc-premium-key', $premium_key );

		}

		if ( isset( $_POST['wccdc-gen-certificate-hidden'], $_POST['wccdc-gen-certificate-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccdc-gen-certificate-nonce'] ) ), 'wccdc-generate-certificate' ) ) {

			/*Salvataggio file .cer*/
			if ( isset( $_FILES['wccdc-cert']['name'] ) ) {

				$file_name = sanitize_text_field( wp_unslash( $_FILES['wccdc-cert']['name'] ) );
				$info      = isset( $_FILES['wccdc-cert']['name'] ) ? pathinfo( $file_name ) : null;
				$name      = isset( $info['basename'] ) ? sanitize_file_name( $info['basename'] ) : null;

				if ( $info ) {

					if ( 'cer' === $info['extension'] ) {

						if ( isset( $_FILES['wccdc-cert']['tmp_name'] ) ) {

							$tmp_name = sanitize_text_field( wp_unslash( $_FILES['wccdc-cert']['tmp_name'] ) );

							global $wp_filesystem;
							if ( empty( $wp_filesystem ) ) {
								require_once ABSPATH . 'wp-admin/includes/file.php';
								WP_Filesystem();
							}
							$wp_filesystem->move( $tmp_name, WCCDC_PRIVATE . $name, true );

						}

						/*Conversione da .cer a .pem*/
						$certificate_ca_cer         = WCCDC_PRIVATE . $name;
						$certificate_ca_cer_content = file_get_contents( $certificate_ca_cer );
						$certificate_ca_pem_content = '-----BEGIN CERTIFICATE-----' . PHP_EOL
							. chunk_split( base64_encode( $certificate_ca_cer_content ), 64, PHP_EOL )
							. '-----END CERTIFICATE-----' . PHP_EOL;
						$certificate_ca_pem         = WCCDC_PRIVATE . 'files/wccdc-cert.pem';
						file_put_contents( $certificate_ca_pem, $certificate_ca_pem_content );

						/*Preparo i file necessari*/
						$pem     = openssl_x509_read( file_get_contents( WCCDC_PRIVATE . 'files/wccdc-cert.pem' ) );
						$get_key = file_get_contents( WCCDC_PRIVATE . 'files/key.der' );

						/*Richiamo la passphrase dal db*/
						$wccdc_password = base64_decode( get_option( 'wccdc-password' ) );

						$key = array( $get_key, $wccdc_password );

						openssl_pkcs12_export_to_file( $pem, WCCDC_PRIVATE . 'files/wccdc-cert.p12', $key, $wccdc_password );

						/*Preparo i file necessari*/
						openssl_pkcs12_read( file_get_contents( WCCDC_PRIVATE . 'files/wccdc-cert.p12' ), $p12, $wccdc_password );

						/*Creo il certificato*/
						file_put_contents( WCCDC_PRIVATE . 'wccdc-certificate.pem', $p12['cert'] . $key[0] );

					} else {
						add_action( 'admin_notices', array( $this, 'not_valid_certificate' ) );
					}
				}
			}
		}

		if ( isset( $_POST['wccdc-certificate-hidden'], $_POST['wccdc-certificate-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccdc-certificate-nonce'] ) ), 'wccdc-upload-certificate' ) ) {

			/*Carica certificato*/
			if ( isset( $_FILES['wccdc-certificate'] ) ) {

				$info = isset( $_FILES['wccdc-certificate']['name'] ) ? pathinfo( sanitize_text_field( wp_unslash( $_FILES['wccdc-certificate']['name'] ) ) ) : null;
				$name = isset( $info['basename'] ) ? sanitize_file_name( $info['basename'] ) : null;

				if ( $info ) {

					if ( 'pem' === $info['extension'] ) {

						if ( isset( $_FILES['wccdc-certificate']['tmp_name'] ) ) {

							$tmp_name = sanitize_text_field( wp_unslash( $_FILES['wccdc-certificate']['tmp_name'] ) );

							global $wp_filesystem;
							if ( empty( $wp_filesystem ) ) {
								require_once ABSPATH . 'wp-admin/includes/file.php';
								WP_Filesystem();
							}
							$wp_filesystem->move( $tmp_name, WCCDC_PRIVATE . $name, true );

						}
					} else {

						add_action( 'admin_notices', array( $this, 'not_valid_certificate' ) );

					}
				}
			}

			/*Password*/
			$wccdc_password = isset( $_POST['wccdc-password'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-password'] ) ) : '';

			/*Salvo passw nel db*/
			if ( $wccdc_password ) {

				update_option( 'wccdc-password', base64_encode( $wccdc_password ) );

			}
		}

		// Gestione salvataggio immediato quando si cambia fonte ISBN
		if ( isset( $_POST['wccdc-isbn-source-temp'] ) ) {
			$wccdc_isbn_source_temp = sanitize_text_field( wp_unslash( $_POST['wccdc-isbn-source-temp'] ) );
			update_option( 'wccdc-isbn-source', $wccdc_isbn_source_temp );
			// Reindirizza per evitare doppio invio
			wp_safe_redirect( admin_url( 'admin.php?page=wccdc-settings' ) );
			exit;
		}
		
		if ( isset( $_POST['wccdc-settings-hidden'], $_POST['wccdc-settings-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccdc-settings-nonce'] ) ), 'wccdc-save-settings' ) ) {

			/*Impostazioni categorie per il controllo in fase di checkout*/
			if ( isset( $_POST['wccdc-tot-cats'] ) ) {

				$tot = sanitize_text_field( wp_unslash( $_POST['wccdc-tot-cats'] ) );
				$tot = $tot ? $tot : 1;

				$wccdc_categories = array();
				$used_cat_ids = array(); // Per tracciare le categorie già utilizzate e prevenire duplicati

				// Conta quanti campi categorie sono stati inviati
				$max_index = 0;
				foreach ($_POST as $key => $value) {
					if (strpos($key, 'wccdc-categories-') === 0) {
						$index = intval(str_replace('wccdc-categories-', '', $key));
						if ($index > $max_index) {
							$max_index = $index;
						}
					}
				}

				// Processa tutti i campi categorie trovati
				for ( $i = 1; $i <= $max_index; $i++ ) {

					$bene = 'libri'; // Sempre "libri" poiché è l'unico bene consentito
					$cat  = isset( $_POST[ 'wccdc-categories-' . $i ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'wccdc-categories-' . $i ] ) ) : '';

					if ( $cat ) {
						// Controlla se la categoria è già stata utilizzata
						if ( in_array( $cat, $used_cat_ids ) ) {
							// Salta questa categoria duplicata
							continue;
						}
						$used_cat_ids[] = $cat;
						$wccdc_categories[] = array( $bene => $cat );
					}
				}

				update_option( 'wccdc-categories', $wccdc_categories );
			}

			/*Fonte ISBN*/
			$wccdc_isbn_source = isset( $_POST['wccdc-isbn-source'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-isbn-source'] ) ) : 'meta';
			update_option( 'wccdc-isbn-source', $wccdc_isbn_source );
			
			/*Campo ISBN*/
			$wccdc_isbn_field = isset( $_POST['wccdc-isbn-field'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-isbn-field'] ) ) : 'none';
			update_option( 'wccdc-isbn-field', $wccdc_isbn_field );
			
			// Se è selezionato "custom", salva il valore manuale
			if ( $wccdc_isbn_field === 'custom' ) {
				$custom_field = isset( $_POST['wccdc-custom-isbn-field-value'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-custom-isbn-field-value'] ) ) : '';
				update_option( 'wccdc-custom-isbn-field-value', $custom_field );
				// Usa il valore personalizzato come campo ISBN effettivo
				update_option( 'wccdc-isbn-field', $custom_field );
			}
			
			/*Conversione in coupon*/
			$wccdc_coupon = isset( $_POST['wccdc-coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-coupon'] ) ) : '';
			update_option( 'wccdc-coupon', $wccdc_coupon );

			/*Immagine in pagina di checkout*/
			$wccdc_image = isset( $_POST['wccdc-image'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-image'] ) ) : '';
			update_option( 'wccdc-image', $wccdc_image );

			/*Controllo prodotti a carrello*/
			$wccdc_items_check = isset( $_POST['wccdc-items-check'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-items-check'] ) ) : '';
			update_option( 'wccdc-items-check', $wccdc_items_check );

			/*Ordini in sospeso*/
			$wccdc_orders_on_hold = isset( $_POST['wccdc-orders-on-hold'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-orders-on-hold'] ) ) : '';
			update_option( 'wccdc-orders-on-hold', $wccdc_orders_on_hold );

			/*Spese di spedizione*/
			$wccdc_exclude_shipping = isset( $_POST['wccdc-exclude-shipping'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-exclude-shipping'] ) ) : '';
			update_option( 'wccdc-exclude-shipping', $wccdc_exclude_shipping );

			/*Messaggio email ordine ricevuto*/
			$wccdc_email_order_received = isset( $_POST['wccdc-email-order-received'] ) ? wp_kses_post( wp_unslash( $_POST['wccdc-email-order-received'] ) ) : '';
			update_option( 'wccdc-email-order-received', $wccdc_email_order_received );

			/*Oggetto email*/
			$wccdc_email_subject = isset( $_POST['wccdc-email-subject'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-email-subject'] ) ) : '';
			update_option( 'wccdc-email-subject', $wccdc_email_subject );

			/*Intestazione email*/
			$wccdc_email_heading = isset( $_POST['wccdc-email-heading'] ) ? sanitize_text_field( wp_unslash( $_POST['wccdc-email-heading'] ) ) : '';
			update_option( 'wccdc-email-heading', $wccdc_email_heading );

			/*Messaggio email ordine ricevuto*/
			$wccdc_email_order_failed = isset( $_POST['wccdc-email-order-failed'] ) ? wp_kses_post( wp_unslash( $_POST['wccdc-email-order-failed'] ) ) : '';
			update_option( 'wccdc-email-order-failed', $wccdc_email_order_failed );

		}
	}

}

new WCCDC_Admin();

