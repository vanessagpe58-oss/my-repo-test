<?php
/**
 * Agrega el endpoint "Mis direcciones" dentro de Mi cuenta de WooCommerce.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSA_Account_Addresses {

	public const ENDPOINT = 'mis-direcciones';

	private HSA_Address_Storage $storage;

	public function __construct( HSA_Address_Storage $storage ) {
		$this->storage = $storage;

		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint_content' ) );
	}

	/**
	 * Registra el endpoint de reescritura de "Mi cuenta".
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Registra la query var asociada al endpoint.
	 *
	 * @param array $vars Variables de consulta existentes.
	 * @return array
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Agrega el enlace "Mis direcciones" al menu de Mi cuenta, justo despues
	 * del enlace de "Direcciones" nativo de WooCommerce cuando existe.
	 *
	 * @param array $items Elementos del menu.
	 * @return array
	 */
	public function add_menu_item( array $items ): array {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;
			if ( 'edit-address' === $key ) {
				$new_items[ self::ENDPOINT ] = __( 'Mis direcciones', 'harmony-saved-addresses' );
			}
		}

		if ( ! isset( $new_items[ self::ENDPOINT ] ) ) {
			$new_items[ self::ENDPOINT ] = __( 'Mis direcciones', 'harmony-saved-addresses' );
		}

		return $new_items;
	}

	/**
	 * Renderiza el contenido del endpoint "Mis direcciones".
	 */
	public function render_endpoint_content(): void {
		$user_id   = get_current_user_id();
		$addresses = $this->storage->get_addresses( $user_id );

		wc_get_template(
			'account-addresses.php',
			array(
				'addresses' => $addresses,
				'settings'  => array(
					'show_alias'             => 'yes' === HSA_Admin_Settings::get_option( 'show_alias', 'yes' ),
					'allow_delete_addresses' => 'yes' === HSA_Admin_Settings::get_option( 'allow_delete_addresses', 'yes' ),
				),
			),
			'harmony-saved-addresses/',
			HSA_PLUGIN_DIR . 'templates/'
		);
	}
}
