<?php
/**
 * Integra el selector visual de direcciones en el checkout clasico de WooCommerce
 * y ofrece soporte de sincronizacion para WooCommerce Checkout Blocks.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSA_Checkout_Addresses {

	/**
	 * @var HSA_Address_Storage
	 */
	private HSA_Address_Storage $storage;

	public function __construct( HSA_Address_Storage $storage ) {
		$this->storage = $storage;

		add_filter( 'woocommerce_locate_template', array( $this, 'locate_form_checkout_template' ), 20, 3 );
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_review_order_template' ), 20, 3 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Reemplaza los bloques nativos .woocommerce-billing-fields y
		// .woocommerce-shipping-fields del checkout clasico por el selector
		// visual de Harmony, renderizado en la misma columna.
		add_action( 'wp', array( $this, 'remove_wc_default_form_callbacks' ), 20 );
		add_action( 'woocommerce_checkout_billing', array( $this, 'render_billing_replacement' ), 5 );

		// Marca el <body> cuando el reemplazo esta activo para que CSS/tema
		// pueda adaptar el layout (ocultar columna vacia, expandir el selector).
		add_filter( 'body_class', array( $this, 'add_body_class_for_selector' ) );

		// Precarga los campos de checkout con la direccion predeterminada del usuario
		// para que WooCommerce calcule envio/impuestos correctamente desde el primer render.
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'prefill_checkout_field' ), 10, 2 );

		// Guarda la direccion seleccionada dentro del pedido al finalizar la compra.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_selected_address_to_order' ), 10, 2 );

		// Compatibilidad best-effort con Checkout Blocks: expone datos via wp_localize
		// y deja que el JS sincronice el store de @woocommerce/blocks-checkout.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_integration' ) );
	}

	/**
	 * Quita los callbacks nativos de WooCommerce que renderizan
	 * .woocommerce-billing-fields y .woocommerce-shipping-fields para que el
	 * selector visual de Harmony pueda ocupar su lugar en el checkout clasico.
	 */
	/**
	 * Sustituye el template checkout/form-checkout.php del nucleo de
	 * WooCommerce por la version del plugin, que envuelve los
	 * bloques de facturacion/envio y el #order_review en una sola
	 * estructura de dos columnas en la misma fila.
	 */
	public function locate_form_checkout_template( $template, $template_name, $template_path ) {
		if ( 'checkout/form-checkout.php' !== $template_name ) {
			return $template;
		}

		$custom = HSA_PLUGIN_DIR . 'templates/checkout/form-checkout.php';
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Sustituye el template checkout/review-order.php de WooCommerce por la
	 * version del plugin, que anade la feature image del producto a la
	 * izquierda del titulo en cada fila del review order.
	 */
	public function locate_review_order_template( $template, $template_name, $template_path ) {
		if ( 'checkout/review-order.php' !== $template_name ) {
			return $template;
		}

		$custom = HSA_PLUGIN_DIR . 'templates/checkout/review-order.php';
		return file_exists( $custom ) ? $custom : $template;
	}

	public function remove_wc_default_form_callbacks(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$checkout = WC()->checkout;
		if ( ! $checkout ) {
			return;
		}

		remove_action( 'woocommerce_checkout_billing', array( $checkout, 'checkout_form_billing' ), 10 );
		remove_action( 'woocommerce_checkout_shipping', array( $checkout, 'checkout_form_shipping' ), 10 );
	}

	/**
	 * Renderiza el selector visual de Harmony en el lugar que antes ocupaba
	 * .woocommerce-billing-fields. Para que WooCommerce pueda validar, recalcular
	 * envio/impuestos y persistir el pedido, tambien dibuja los campos nativos
	 * billing/shipping en un contenedor visualmente oculto al que el JS sigue
	 * escribiendo con los valores elegidos por el selector.
	 */
	public function render_billing_replacement(): void {
		// Los invitados (no logueados) conservan el formulario nativo de WC
		// intacto; el selector aparece arriba y el flujo original no cambia.
		if ( ! is_user_logged_in() ) {
			if ( function_exists( 'WC' ) && WC()->checkout ) {
				WC()->checkout->checkout_form_billing();
			}
			return;
		}

		if ( ! $this->should_render_selector() ) {
			// Selector deshabilitado en este contexto: fallback al formulario nativo.
			if ( function_exists( 'WC' ) && WC()->checkout ) {
				WC()->checkout->checkout_form_billing();
			}
			return;
		}

		// Campos nativos off-screen para que WC valide, recalcule envio/impuestos
		// y guarde el pedido con los datos que el selector eligio.
		echo '<div class="hsa-hidden-fields">';
		echo '<div class="woocommerce-billing-fields">';
		$this->render_native_wc_fields( 'billing' );
		echo '</div>';

		// Solo renderizamos shipping_* cuando el envio a direccion distinta
		// esta habilitado en la configuracion de WooCommerce. Ademas
		// enviamos ship_to_different_address=1 en un input oculto para que
		// WC siempre use los shipping_* (que el selector mantiene
		// sincronizados) y no los ignore. Si no, WC ignorara estos campos y
		// el navegador podria autocompletarlos con valores no deseados.
		$ship_to_different_allowed = ( 'billing' !== get_option( 'woocommerce_ship_to_destination' ) );
		if ( $ship_to_different_allowed ) {
			echo '<div class="woocommerce-shipping-fields">';
			$this->render_native_wc_fields( 'shipping' );
			echo '</div>';
			echo '<input type="hidden" name="ship_to_different_address" value="1" />';
		}
		echo '</div>';

		// Selector visible que reemplaza visualmente a .woocommerce-billing-fields.
		$this->render_selector();
	}

	/**
	 * Dibuja los campos nativos de checkout de WooCommerce (billing_* /
	 * shipping_*) usando el markup estandar que produce woocommerce_form_field().
	 */
	private function render_native_wc_fields( string $type ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->checkout ) {
			return;
		}

		foreach ( WC()->checkout->get_checkout_fields( $type ) as $key => $field ) {
			woocommerce_form_field( $key, $field, WC()->checkout->get_value( $key ) );
		}
	}

	/**
	 * Agrega una clase al <body> cuando el selector visual esta reemplazando
	 * .woocommerce-billing-fields en el checkout clasico, para que CSS/tema
	 * pueda ocultar la columna vacia y expandir el ancho del selector.
	 *
	 * @param array $classes Clases existentes del body.
	 * @return array
	 */
	public function add_body_class_for_selector( array $classes ): array {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return $classes;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return $classes;
		}
		if ( ! is_user_logged_in() ) {
			return $classes;
		}
		// En Checkout Blocks el reemplazo no aplica: el layout lo controla el bloque.
		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) ) {
			return $classes;
		}
		if ( ! $this->should_render_selector() ) {
			return $classes;
		}

		$classes[] = 'hsa-selector-active';
		return $classes;
	}

	/**
	 * Determina si el selector debe mostrarse para el usuario/sesion actual.
	 */
	private function should_render_selector(): bool {
		if ( is_user_logged_in() ) {
			return true;
		}

		return 'yes' === HSA_Admin_Settings::get_option( 'enable_guest_support', 'yes' );
	}

	/**
	 * Carga CSS/JS unicamente en checkout y en Mi cuenta, no en el resto del sitio.
	 */
	public function enqueue_assets(): void {
		if ( ! is_checkout() && ! is_account_page() ) {
			return;
		}

		wp_enqueue_style(
			'hsa-frontend',
			HSA_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			HSA_VERSION
		);

		wp_enqueue_script(
			'hsa-frontend',
			HSA_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'wp-util' ),
			HSA_VERSION,
			true
		);

		$user_id   = get_current_user_id();
		$addresses = $user_id ? $this->storage->get_addresses( $user_id ) : array();

		wp_localize_script(
			'hsa-frontend',
			'HSA_DATA',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'rest_url'       => esc_url_raw( rest_url( 'harmony-addresses/v1/' ) ),
				'nonce'          => wp_create_nonce( 'hsa_ajax_nonce' ),
				'rest_nonce'     => wp_create_nonce( 'wp_rest' ),
				'is_logged_in'   => is_user_logged_in(),
				'is_checkout'    => is_checkout(),
				'addresses'      => $addresses,
				'settings'       => array(
					'allow_delete_addresses' => 'yes' === HSA_Admin_Settings::get_option( 'allow_delete_addresses', 'yes' ),
					'allow_separate_billing' => 'yes' === HSA_Admin_Settings::get_option( 'allow_separate_billing', 'yes' ),
					'show_alias'             => 'yes' === HSA_Admin_Settings::get_option( 'show_alias', 'yes' ),
					'enable_modal'           => 'yes' === HSA_Admin_Settings::get_option( 'enable_modal', 'yes' ),
					'primary_color'          => HSA_Admin_Settings::get_option( 'primary_color', '#2271b1' ),
					'continue_button_text'   => HSA_Admin_Settings::get_option( 'continue_button_text', __( 'Usar esta direccion', 'harmony-saved-addresses' ) ),
					'max_addresses'          => (int) HSA_Admin_Settings::get_option( 'max_addresses', 10 ),
				),
				'countries'      => function_exists( 'WC' ) ? WC()->countries->get_countries() : array(),
				'states'         => function_exists( 'WC' ) ? WC()->countries->get_states() : array(),
				'default_country' => function_exists( 'WC' ) ? WC()->countries->get_base_country() : 'MX',
				'i18n'           => $this->get_i18n_strings(),
			)
		);
	}

	/**
	 * Cadenas de texto usadas por el JavaScript, ya traducidas.
	 *
	 * @return array<string, string>
	 */
	private function get_i18n_strings(): array {
		return array(
			'chooseAddressTitle'   => __( 'Elige la direccion de entrega', 'harmony-saved-addresses' ),
			'newAddressTitle'      => __( 'Ingresa tu direccion de entrega', 'harmony-saved-addresses' ),
			'loginPrompt'          => __( '¿Ya tienes una cuenta? Inicia sesion para utilizar tus direcciones guardadas.', 'harmony-saved-addresses' ),
			'modify'               => __( 'Modificar direccion', 'harmony-saved-addresses' ),
			'chooseAnother'        => __( 'Elegir otra direccion', 'harmony-saved-addresses' ),
			'addNew'               => __( 'Agregar nueva direccion', 'harmony-saved-addresses' ),
			'addAnother'           => __( '+ Agregar otra direccion', 'harmony-saved-addresses' ),
			'selected'             => __( 'Direccion seleccionada', 'harmony-saved-addresses' ),
			'useSameForBilling'    => __( 'Utilizar esta misma direccion para facturacion', 'harmony-saved-addresses' ),
			'save'                 => __( 'Guardar direccion', 'harmony-saved-addresses' ),
			'cancel'               => __( 'Cancelar', 'harmony-saved-addresses' ),
			'delete'               => __( 'Eliminar', 'harmony-saved-addresses' ),
			'deleteConfirm'        => __( '¿Seguro que deseas eliminar esta direccion? Esta accion no se puede deshacer.', 'harmony-saved-addresses' ),
			'makeDefault'          => __( 'Marcar como predeterminada', 'harmony-saved-addresses' ),
			'defaultBadge'         => __( 'Predeterminada', 'harmony-saved-addresses' ),
			'aliasLabel'           => __( 'Nombre de la direccion (Casa, Trabajo, etc.)', 'harmony-saved-addresses' ),
			'firstNameLabel'       => __( 'Nombre', 'harmony-saved-addresses' ),
			'lastNameLabel'        => __( 'Apellidos', 'harmony-saved-addresses' ),
			'companyLabel'         => __( 'Empresa (opcional)', 'harmony-saved-addresses' ),
			'countryLabel'         => __( 'Pais', 'harmony-saved-addresses' ),
			'stateLabel'           => __( 'Estado', 'harmony-saved-addresses' ),
			'cityLabel'            => __( 'Municipio o ciudad', 'harmony-saved-addresses' ),
			'neighborhoodLabel'    => __( 'Colonia', 'harmony-saved-addresses' ),
			'postcodeLabel'        => __( 'Codigo postal', 'harmony-saved-addresses' ),
			'address1Label'        => __( 'Calle y numero exterior', 'harmony-saved-addresses' ),
			'address2Label'        => __( 'Numero interior (opcional)', 'harmony-saved-addresses' ),
			'referencesLabel'      => __( 'Referencias (opcional)', 'harmony-saved-addresses' ),
			'phoneLabel'           => __( 'Telefono', 'harmony-saved-addresses' ),
			'emailLabel'           => __( 'Correo electronico', 'harmony-saved-addresses' ),
			'genericError'         => __( 'Ocurrio un error. Intenta de nuevo.', 'harmony-saved-addresses' ),
			'requiredField'        => __( 'Este campo es obligatorio.', 'harmony-saved-addresses' ),
			'applyingChange'       => __( 'Aplicando tu direccion...', 'harmony-saved-addresses' ),
			'continueButton'       => HSA_Admin_Settings::get_option( 'continue_button_text', __( 'Usar esta direccion', 'harmony-saved-addresses' ) ),
		);
	}

	/**
	 * Renderiza el bloque del selector antes del formulario de checkout clasico.
	 */
	public function render_selector(): void {
		if ( ! $this->should_render_selector() ) {
			return;
		}

		$user_id   = get_current_user_id();
		$addresses = $user_id ? $this->storage->get_addresses( $user_id ) : array();

		wc_get_template(
			'checkout-address-selector.php',
			array(
				'addresses'    => $addresses,
				'is_logged_in' => is_user_logged_in(),
				'settings'     => array(
					'show_alias'             => 'yes' === HSA_Admin_Settings::get_option( 'show_alias', 'yes' ),
					'allow_separate_billing' => 'yes' === HSA_Admin_Settings::get_option( 'allow_separate_billing', 'yes' ),
					'allow_delete_addresses' => 'yes' === HSA_Admin_Settings::get_option( 'allow_delete_addresses', 'yes' ),
				),
			),
			'harmony-saved-addresses/',
			HSA_PLUGIN_DIR . 'templates/'
		);
	}

	/**
	 * Precarga un campo del checkout clasico con el valor de la direccion
	 * predeterminada del cliente, para que el primer calculo de envio/impuestos
	 * ya sea correcto antes de cualquier interaccion del usuario.
	 *
	 * @param mixed  $value Valor actual.
	 * @param string $input Nombre del campo (billing_city, shipping_postcode, etc).
	 * @return mixed
	 */
	public function prefill_checkout_field( $value, $input ) {
		if ( ! empty( $value ) || ! is_user_logged_in() ) {
			return $value;
		}

		$user_id = get_current_user_id();
		$address = $this->storage->get_default_address( $user_id );

		if ( ! $address ) {
			return $value;
		}

		foreach ( array( 'billing', 'shipping' ) as $prefix ) {
			$mapped = $this->storage->map_to_wc_fields( $address, $prefix );
			if ( isset( $mapped[ $input ] ) && '' !== $mapped[ $input ] ) {
				return $mapped[ $input ];
			}
		}

		return $value;
	}

	/**
	 * Copia el identificador y el resumen de la direccion seleccionada dentro
	 * de los metadatos del pedido, para que quede registrada aunque el cliente
	 * despues modifique o elimine esa direccion de su perfil.
	 *
	 * @param WC_Order $order Pedido en creacion.
	 * @param array    $data  Datos del formulario de checkout.
	 */
	public function attach_selected_address_to_order( $order, $data ): void {
		if ( ! isset( $_POST['hsa_selected_shipping_id'] ) && ! isset( $_POST['hsa_selected_billing_id'] ) ) {
			return;
		}

		$user_id = get_current_user_id();

		$shipping_id = isset( $_POST['hsa_selected_shipping_id'] )
			? sanitize_text_field( wp_unslash( $_POST['hsa_selected_shipping_id'] ) )
			: '';
		$billing_id  = isset( $_POST['hsa_selected_billing_id'] )
			? sanitize_text_field( wp_unslash( $_POST['hsa_selected_billing_id'] ) )
			: '';

		if ( $shipping_id ) {
			$address = $this->storage->get_address( $user_id, $shipping_id );
			if ( $address ) {
				$order->update_meta_data( '_hsa_shipping_address_id', $shipping_id );
				$order->update_meta_data( '_hsa_shipping_address_snapshot', wp_json_encode( $address ) );
				if ( ! empty( $address['neighborhood'] ) ) {
					$order->update_meta_data( '_hsa_shipping_neighborhood', $address['neighborhood'] );
				}
			}
		}

		if ( $billing_id ) {
			$address = $this->storage->get_address( $user_id, $billing_id );
			if ( $address ) {
				$order->update_meta_data( '_hsa_billing_address_id', $billing_id );
				$order->update_meta_data( '_hsa_billing_address_snapshot', wp_json_encode( $address ) );
				if ( ! empty( $address['neighborhood'] ) ) {
					$order->update_meta_data( '_hsa_billing_neighborhood', $address['neighborhood'] );
				}
			}
		}
	}

	/**
	 * Integracion best-effort con WooCommerce Checkout Blocks.
	 * Se expone la data necesaria para que el script frontend pueda
	 * sincronizar el store de datos del bloque de checkout (wc/store/cart)
	 * cuando el sitio usa el checkout basado en bloques en vez del shortcode.
	 */
	public function register_blocks_integration(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Package' ) ) {
			return;
		}

		add_action(
			'wp_footer',
			function () {
				if ( ! function_exists( 'has_block' ) || ! is_checkout() ) {
					return;
				}
				if ( ! has_block( 'woocommerce/checkout' ) ) {
					return;
				}
				if ( ! $this->should_render_selector() ) {
					return;
				}

				$user_id   = get_current_user_id();
				$addresses = $user_id ? $this->storage->get_addresses( $user_id ) : array();

				echo '<div id="hsa-blocks-mount-point" style="display:none" data-hsa-blocks="1"></div>';
				wc_get_template(
					'checkout-address-selector.php',
					array(
						'addresses'    => $addresses,
						'is_logged_in' => is_user_logged_in(),
						'is_blocks'    => true,
						'settings'     => array(
							'show_alias'             => 'yes' === HSA_Admin_Settings::get_option( 'show_alias', 'yes' ),
							'allow_separate_billing' => 'yes' === HSA_Admin_Settings::get_option( 'allow_separate_billing', 'yes' ),
							'allow_delete_addresses' => 'yes' === HSA_Admin_Settings::get_option( 'allow_delete_addresses', 'yes' ),
						),
					),
					'harmony-saved-addresses/',
					HSA_PLUGIN_DIR . 'templates/'
				);
			},
			5
		);
	}
}
