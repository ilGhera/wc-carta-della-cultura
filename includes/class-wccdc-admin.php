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
		add_action( 'admin_menu', array( $this, 'register_options_page' ) );
		add_action( 'wp_ajax_wccdc-delete-certificate', array( $this, 'delete_certificate_callback' ), 1 );
		add_action( 'wp_ajax_wccdc-add-cat', array( $this, 'add_cat_callback' ) );
		add_action( 'wp_ajax_wccdc-sandbox', array( $this, 'sandbox_callback' ) );
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
	 * Pulsante call to action Premium
	 *
	 * @param bool $no_margin aggiunge la classe CSS con true.
	 *
	 * @return string
	 */
	public function get_go_premium( $no_margin = false ) {

		$output      = '<span class="label label-warning premium' . ( $no_margin ? ' no-margin' : null ) . '">';
			$output .= '<a href="https://www.ilghera.com/product/carta-della-cultura-for-wc-premium" target="_blank">Premium</a>';
		$output     .= '</span>';

		return $output;

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
	 * Pagina opzioni plugin
	 *
	 * @return void
	 */
	public function wccdc_settings() {

		/*Recupero le opzioni salvate nel db*/
		$passphrase                = base64_decode( get_option( 'wccdc-password' ) );
		$categories                = get_option( 'wccdc-categories' );
		$tot_cats                  = $categories ? count( $categories ) : 0;
		$wccdc_image                = get_option( 'wccdc-image' );

		echo '<div class="wrap">';
			echo '<div class="wrap-left">';
				echo '<h1>ilGhera Carta della Cultura for WooCommerce- ' . esc_html( __( 'Impostazioni', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</h1>';

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
            echo '<h3>' . esc_html__( 'Richiedi un certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . wp_kses_post( $this->get_go_premium() ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'Con questo strumento puoi generare un file .der necessario per richiedere il tuo certificato su Carta della Cultura.', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

			echo '<form id="generate-certificate-request" method="post" class="one-of" enctype="multipart/form-data" action="">';
				echo '<table class="form-table wccdc-table">';
					echo '<tr>';
						echo '<th scope="row">' . esc_html__( 'Stato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="countryName" placeholder="IT" disabled>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Provincia', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="stateOrProvinceName" placeholder="Es. Milano" disabled>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Località', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="localityName" placeholder="Es. Legnano" disabled>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Nome azienda', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="organizationName" placeholder="Es. Taldeitali srl" disabled>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Reparto azienda', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="organizationalUnitName" placeholder="Es. Vendite" disabled>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Nome', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="text" name="commonName" placeholder="Es. Franco Bianchi" disabled>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Email', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="email" name="emailAddress" placeholder="Es. franco.bianchi@taldeitali.it" disabled>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row">' . esc_html__( 'Password', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';
							echo '<input type="password" name="wccdc-password" placeholder="**********" disabled>';
						echo '</td>';
					echo '</tr>';

					echo '<th scope="row"></th>';
						echo '<td>';
						wp_nonce_field( 'wccdc-generate-der', 'wccdc-generate-der-nonce' );
						echo '<input type="hidden" name="wccdc-generate-der-hidden" value="1">';
						echo '<input type="submit" name="generate-der" class="button-primary wccdc-button generate-der" value="' . esc_attr__( 'Scarica file .der', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" disabled>';
						echo '</td>';
					echo '</tr>';

				echo '</table>';
			echo '</form>';

			/*Genera certificato .pem*/
            echo '<h3>' . esc_html__( 'Crea il tuo certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . wp_kses_post( $this->get_go_premium() ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'Con questo ultimo passaggio, potrai iniziare a ricevere pagamenti attraverso buoni Carta della Cultura.', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

			echo '<form name="wccdc-generate-certificate" class="wccdc-generate-certificate one-of" method="post" enctype="multipart/form-data" action="">';
				echo '<table class="form-table wccdc-table">';

					/*Carica certificato*/
					echo '<tr>';
						echo '<th scope="row">' . esc_html__( 'Genera certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
						echo '<td>';

							echo '<input type="file" accept=".cer" name="wccdc-cert" class="wccdc-cert" disabled>';
							echo '<p class="description">' . esc_html__( 'Carica il file .cer ottenuto da Carta della Cultura per procedere', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</p>';

							wp_nonce_field( 'wccdc-generate-certificate', 'wccdc-gen-certificate-nonce' );

							echo '<input type="hidden" name="wccdc-gen-certificate-hidden" value="1">';
							echo '<input type="submit" class="button-primary wccdc-button" value="' . esc_html__( 'Genera certificato', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" disabled>';

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
								echo '<th scope="row">' . esc_html__( 'Utilizzo immagine', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									echo '<input type="checkbox" name="wccdc-image" value="1"' . ( 1 === intval( $wccdc_image ) ? ' checked="checked"' : '' ) . '>';
									echo '<p class="description">' . wp_kses_post( __( 'Mostra il logo <i>Carta della Cultura</i> nella pagine di checkout.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr>';
								echo '<th scope="row">' . esc_html__( 'Controllo prodotti', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									echo '<input type="checkbox" name="wccdc-items-check" value="1" disabled>';
									echo '<p class="description">' . wp_kses_post( __( 'Mostra il metodo di pagamento solo se il/ i prodotti a carrello sono acquistabili con buoni <i>Carta della Cultura</i>.<br>Più prodotti dovranno prevedere l\'uso di buoni dello stesso ambito di utilizzo.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
									echo wp_kses_post( $this->get_go_premium( true ) );
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-orders-on-hold">';
								echo '<th scope="row">' . esc_html__( 'Ordini in sospeso', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
                                    echo '<input type="checkbox" name="wccdc-orders-on-hold" value="1" disabled>';
									echo '<p class="description">' . wp_kses_post( __( 'I buoni Carta della Cultura verranno validati con il completamento manuale degli ordini.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
                                    echo wp_kses_post( $this->get_go_premium( true ) );
								echo '</td>';
							echo '<tr class="wccdc-exclude-shipping">';
								echo '<th scope="row">' . esc_html__( 'Spese di spedizione', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
                                    echo '<input type="checkbox" name="wccdc-exclude-shipping" value="1" disabled>';
									echo '<p class="description">' . wp_kses_post( __( 'Escludi le spese di spedizione dal pagamento con Carta della Cultura.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
                                    echo wp_kses_post( $this->get_go_premium( true ) );
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-coupon">';
								echo '<th scope="row">' . esc_html__( 'Conversione in coupon', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									echo '<input type="checkbox" name="wccdc-coupon" value="1" disabled>';
									echo '<p class="description">' . wp_kses_post( __( 'Nel caso in cui il buono <i>Carta della Cultura</i> inserito sia inferiore al totale a carrello, viene convertito in <i>Codice promozionale</i> ed applicato all\'ordine.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
                                    echo wp_kses_post( $this->get_go_premium( true ) );
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-email-order-received wccdc-email-details">';
								echo '<th scope="row">' . esc_html__( 'Ordine ricevuto', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
									$default_order_received_message = __( 'L\'ordine verrà completato manualmente nei prossimi giorni e, contestualmente, verrà validato il buono Carta della Cultura inserito. Riceverai una notifica email di conferma, grazie!', 'ilghera-carta-della-cultura-for-woocommerce' );
									echo '<textarea cols="6" rows="6" class="regular-text" name="wccdc-email-order-received" placeholder="' . esc_html( $default_order_received_message ) . '" disabled></textarea>';
									echo '<p class="description">';
										echo wp_kses_post( __( 'Messaggio della mail inviata all\'utente al ricevimento dell\'ordine.', 'ilghera-carta-della-cultura-for-woocommerce' ) );
									echo '</p>';
									echo '<div class="wccdc-divider"></div>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-email-subject wccdc-email-details">';
								echo '<th scope="row">' . esc_html__( 'Oggetto email', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
                                    echo '<input type="text" class="regular-text" name="wccdc-email-subject" placeholder="' . esc_attr__( 'Ordine fallito', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" disabled>';
									echo '<p class="description">' . wp_kses_post( __( 'Oggetto della mail inviata all\'utente nel caso in cui la validazione del buono non sia andata a buon fine.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-email-heading wccdc-email-details">';
								echo '<th scope="row">' . esc_html__( 'Intestazione email', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
                                    echo '<input type="text" class="regular-text" name="wccdc-email-heading" placeholder="' . esc_attr__( 'Ordine fallito', 'ilghera-carta-della-cultura-for-woocommerce' ) . '" disabled>';
									echo '<p class="description">' . wp_kses_post( __( 'Intestazione della mail inviata all\'utente nel caso in cui la validazione del buono non sia andata a buon fine.', 'ilghera-carta-della-cultura-for-woocommerce' ) ) . '</p>';
								echo '</td>';
							echo '</tr>';

							echo '<tr class="wccdc-email-order-failed wccdc-email-details">';
								echo '<th scope="row">' . esc_html__( 'Ordine fallito', 'ilghera-carta-della-cultura-for-woocommerce' ) . '</th>';
								echo '<td>';
										/* translators: %s: Checkout URL */
										$default_order_failed_message = __( 'La validazione del buono Carta della Cultura ha restituito un errore e non è stato possibile completare l\'ordine, effettua il pagamento a <a href="%s">questo indirizzo</a>.', 'ilghera-carta-della-cultura-for-woocommerce' );
										$placeholder_text = sprintf( $default_order_failed_message, '[checkout-url]' );
										echo '<textarea cols="6" rows="6" class="regular-text" name="wccdc-email-order-failed" placeholder="' . esc_html( $placeholder_text ) . '" disabled></textarea>';
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
			echo '</div>';
			echo '<div class="clear"></div>';

		echo '</div>';

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

