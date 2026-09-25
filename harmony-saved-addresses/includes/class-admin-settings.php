<?php
/**
 * Agrega la seccion de configuracion en WooCommerce > Ajustes > Direcciones guardadas.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSA_Admin_Settings {

	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_hsa_addresses', array( $this, 'render_settings_page' ) );
		add_action( 'woocommerce_update_options_hsa_addresses', array( $this, 'save_settings' ) );
	}

	/**
	 * Valores por defecto de todos los ajustes del plugin.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {
		return array(
			'enable_multiple_addresses'   => 'yes',
			'max_addresses'                => 10,
			'allow_delete_addresses'       => 'yes',
			'allow_separate_billing'       => 'yes',
			'show_alias'                   => 'yes',
			'enable_modal'                 => 'yes',
			'primary_color'                => '#2271b1',
			'continue_button_text'         => __( 'Usar esta direccion', 'harmony-saved-addresses' ),
			'enable_guest_support'         => 'yes',
			'delete_data_on_uninstall'     => 'no',
		);
	}

	/**
	 * Obtiene un ajuste especifico con valor por defecto de respaldo.
	 *
	 * @param string $key     Clave del ajuste.
	 * @param mixed  $default Valor por defecto si no existe.
	 * @return mixed
	 */
	public static function get_option( string $key, $default = '' ) {
		$settings = get_option( HSA_OPTIONS_KEY, self::get_default_settings() );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Registra la pestana "Direcciones guardadas" dentro de WooCommerce > Ajustes.
	 *
	 * @param array $tabs Pestanas existentes.
	 * @return array
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs['hsa_addresses'] = __( 'Direcciones guardadas', 'harmony-saved-addresses' );
		return $tabs;
	}

	/**
	 * Define los campos de configuracion mostrados por WooCommerce Settings API.
	 *
	 * @return array
	 */
	private function get_settings_fields(): array {
		return array(
			array(
				'title' => __( 'Harmony Saved Addresses', 'harmony-saved-addresses' ),
				'type'  => 'title',
				'desc'  => __( 'Configura como se comporta el selector visual de direcciones en el checkout.', 'harmony-saved-addresses' ),
				'id'    => 'hsa_settings_title',
			),
			array(
				'title'   => __( 'Multiples direcciones', 'harmony-saved-addresses' ),
				'desc'    => __( 'Permitir que cada cliente guarde varias direcciones', 'harmony-saved-addresses' ),
				'id'      => 'hsa_settings[enable_multiple_addresses]',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'             => __( 'Numero maximo de direcciones', 'harmony-saved-addresses' ),
				'desc'              => __( 'Cantidad maxima de direcciones que puede guardar cada usuario.', 'harmony-saved-addresses' ),
				'id'                => 'hsa_settings[max_addresses]',
				'default'           => 10,
				'type'              => 'number',
				'custom_attributes' => array(
					'min' => 1,
					'max' => 50,
				),
			),
			array(
				'title'   => __( 'Permitir eliminar direcciones', 'harmony-saved-addresses' ),
				'desc'    => __( 'Mostrar la opcion de eliminar direcciones guardadas', 'harmony-saved-addresses' ),
				'id'      => 'hsa_settings[allow_delete_addresses]',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'Facturacion independiente', 'harmony-saved-addresses' ),
				'desc'    => __( 'Permitir elegir una direccion de facturacion distinta a la de envio', 'harmony-saved-addresses' ),
				'id'      => 'hsa_settings[allow_separate_billing]',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'Mostrar alias', 'harmony-saved-addresses' ),
				'desc'    => __( 'Mostrar el alias (Casa, Trabajo, etc.) en las tarjetas de direccion', 'harmony-saved-addresses' ),
				'id'      => 'hsa_settings[show_alias]',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'Ventana modal', 'harmony-saved-addresses' ),
				'desc'    => __( 'Abrir el formulario de direccion en una ventana modal (si se desactiva, se muestra en linea)', 'harmony-saved-addresses' ),
				'id'      => 'hsa_settings[enable_modal]',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'    => __( 'Color principal', 'harmony-saved-addresses' ),
				'desc'     => __( 'Color de botones y bordes del selector', 'harmony-saved-addresses' ),
				'id'       => 'hsa_settings[primary_color]',
				'default'  => '#2271b1',
				'type'     => 'color',
				'desc_tip' => true,
			),
			array(
				'title'   => __( 'Texto del boton continuar', 'harmony-saved-addresses' ),
				'id'      => 'hsa_settings[continue_button_text]',
				'default' => __( 'Usar esta direccion', 'harmony-saved-addresses' ),
				'type'    => 'text',
			),
			array(
				'title'   => __( 'Compatibilidad con invitados', 'harmony-saved-addresses' ),
				'desc'    => __( 'Mostrar el selector tambien a usuarios que compran como invitados (sin cuenta)', 'harmony-saved-addresses' ),
				'id'      => 'hsa_settings[enable_guest_support]',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'Eliminar datos al desinstalar', 'harmony-saved-addresses' ),
				'desc'    => __( 'Eliminar todas las direcciones guardadas y ajustes cuando se desinstale el plugin', 'harmony-saved-addresses' ),
				'id'      => 'hsa_settings[delete_data_on_uninstall]',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hsa_settings_end',
			),
		);
	}

	/**
	 * Renderiza la pagina de ajustes usando la API nativa de WooCommerce.
	 */
	public function render_settings_page(): void {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Guarda los ajustes enviados desde el formulario.
	 * WooCommerce ya se encarga de la sanitizacion y verificacion de nonce
	 * dentro de woocommerce_update_options().
	 */
	public function save_settings(): void {
		woocommerce_update_options( $this->get_settings_fields() );
	}
}
