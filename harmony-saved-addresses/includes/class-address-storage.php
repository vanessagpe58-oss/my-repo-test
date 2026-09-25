<?php
/**
 * Maneja el almacenamiento de direcciones guardadas en los metadatos del usuario.
 * Las direcciones se guardan como un arreglo PHP serializado de forma nativa por
 * WordPress (update_user_meta), nunca como texto HTML.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSA_Address_Storage {

	/**
	 * Engancha la importacion inicial de direcciones clasicas de WooCommerce.
	 */
	public function __construct() {
		add_action( 'woocommerce_created_customer', array( $this, 'maybe_import_legacy_address' ) );
	}

	/**
	 * Obtiene todas las direcciones guardadas de un usuario.
	 *
	 * @param int $user_id ID del usuario.
	 * @return array<int, array> Lista de direcciones.
	 */
	public function get_addresses( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$this->maybe_import_legacy_address( $user_id );

		$addresses = get_user_meta( $user_id, HSA_USER_META_KEY, true );

		if ( ! is_array( $addresses ) ) {
			return array();
		}

		return $addresses;
	}

	/**
	 * Obtiene una direccion especifica por su ID, validando que pertenezca al usuario.
	 *
	 * @param int    $user_id     ID del usuario.
	 * @param string $address_id  ID interno de la direccion.
	 * @return array|null
	 */
	public function get_address( int $user_id, string $address_id ): ?array {
		foreach ( $this->get_addresses( $user_id ) as $address ) {
			if ( isset( $address['id'] ) && $address['id'] === $address_id ) {
				return $address;
			}
		}
		return null;
	}

	/**
	 * Obtiene la direccion marcada como predeterminada del usuario.
	 *
	 * @param int $user_id ID del usuario.
	 * @return array|null
	 */
	public function get_default_address( int $user_id ): ?array {
		$addresses = $this->get_addresses( $user_id );

		foreach ( $addresses as $address ) {
			if ( ! empty( $address['is_default'] ) ) {
				return $address;
			}
		}

		return $addresses[0] ?? null;
	}

	/**
	 * Guarda (crea o actualiza) una direccion para un usuario.
	 * Devuelve la direccion final con su ID asignado o WP_Error si excede el limite.
	 *
	 * @param int   $user_id ID del usuario propietario.
	 * @param array $data    Datos ya sanitizados de la direccion.
	 * @return array|WP_Error
	 */
	public function save_address( int $user_id, array $data ) {
		if ( $user_id <= 0 ) {
			return new WP_Error( 'hsa_invalid_user', __( 'Usuario no valido.', 'harmony-saved-addresses' ) );
		}

		$addresses = $this->get_addresses( $user_id );
		$is_update = ! empty( $data['id'] );

		if ( ! $is_update ) {
			$max = HSA_Admin_Settings::get_option( 'max_addresses', 10 );
			if ( $max > 0 && count( $addresses ) >= $max ) {
				return new WP_Error(
					'hsa_max_addresses',
					sprintf(
						/* translators: %d: maximum number of addresses allowed */
						__( 'Has alcanzado el limite de %d direcciones guardadas.', 'harmony-saved-addresses' ),
						$max
					)
				);
			}
			$data['id'] = $this->generate_address_id();
		}

		$found = false;

		foreach ( $addresses as $index => $existing ) {
			if ( isset( $existing['id'] ) && $existing['id'] === $data['id'] ) {
				// Verifica que la direccion pertenezca realmente al usuario actual.
				$addresses[ $index ] = array_merge( $existing, $data );
				$found                = true;
				break;
			}
		}

		if ( ! $found ) {
			$addresses[] = $data;
		}

		// Si es la unica direccion, o se pidio explicitamente, marcarla como predeterminada.
		if ( 1 === count( $addresses ) ) {
			$data['is_default'] = true;
		}

		if ( ! empty( $data['is_default'] ) ) {
			$addresses = $this->set_only_default( $addresses, $data['id'] );
		}

		$saved = update_user_meta( $user_id, HSA_USER_META_KEY, $addresses );

		if ( false === $saved && get_user_meta( $user_id, HSA_USER_META_KEY, true ) !== $addresses ) {
			return new WP_Error( 'hsa_save_failed', __( 'No se pudo guardar la direccion. Intenta de nuevo.', 'harmony-saved-addresses' ) );
		}

		return $this->get_address( $user_id, $data['id'] );
	}

	/**
	 * Elimina una direccion del usuario, verificando la propiedad.
	 * No permite eliminar la direccion predeterminada si existen otras
	 * sin elegir primero una nueva predeterminada.
	 *
	 * @param int    $user_id    ID del usuario.
	 * @param string $address_id ID de la direccion a eliminar.
	 * @return true|WP_Error
	 */
	public function delete_address( int $user_id, string $address_id ) {
		$addresses = $this->get_addresses( $user_id );
		$target    = null;

		foreach ( $addresses as $address ) {
			if ( $address['id'] === $address_id ) {
				$target = $address;
				break;
			}
		}

		if ( null === $target ) {
			return new WP_Error( 'hsa_not_found', __( 'La direccion no existe o no te pertenece.', 'harmony-saved-addresses' ) );
		}

		if ( ! empty( $target['is_default'] ) && count( $addresses ) > 1 ) {
			return new WP_Error(
				'hsa_default_protected',
				__( 'No puedes eliminar tu direccion predeterminada. Elige primero otra direccion como predeterminada.', 'harmony-saved-addresses' )
			);
		}

		$remaining = array_values(
			array_filter(
				$addresses,
				static fn( $address ) => $address['id'] !== $address_id
			)
		);

		// Si queda una sola direccion, se convierte automaticamente en predeterminada.
		if ( 1 === count( $remaining ) ) {
			$remaining[0]['is_default'] = true;
		}

		update_user_meta( $user_id, HSA_USER_META_KEY, $remaining );

		return true;
	}

	/**
	 * Marca una direccion como predeterminada.
	 *
	 * @param int    $user_id    ID del usuario.
	 * @param string $address_id ID de la direccion.
	 * @return true|WP_Error
	 */
	public function set_default_address( int $user_id, string $address_id ) {
		$addresses = $this->get_addresses( $user_id );
		$exists    = false;

		foreach ( $addresses as $address ) {
			if ( $address['id'] === $address_id ) {
				$exists = true;
				break;
			}
		}

		if ( ! $exists ) {
			return new WP_Error( 'hsa_not_found', __( 'La direccion no existe o no te pertenece.', 'harmony-saved-addresses' ) );
		}

		$addresses = $this->set_only_default( $addresses, $address_id );
		update_user_meta( $user_id, HSA_USER_META_KEY, $addresses );

		return true;
	}

	/**
	 * Recorre el arreglo de direcciones y deja una sola marcada como predeterminada.
	 *
	 * @param array  $addresses  Lista de direcciones.
	 * @param string $default_id ID que debe quedar como predeterminado.
	 * @return array
	 */
	private function set_only_default( array $addresses, string $default_id ): array {
		foreach ( $addresses as $index => $address ) {
			$addresses[ $index ]['is_default'] = ( $address['id'] === $default_id );
		}
		return $addresses;
	}

	/**
	 * Genera un identificador unico interno para una direccion.
	 */
	private function generate_address_id(): string {
		return 'hsa_' . wp_generate_password( 12, false, false );
	}

	/**
	 * Si el usuario tiene una direccion "clasica" de WooCommerce guardada
	 * (billing_* / shipping_*) y todavia no tiene ninguna direccion en el
	 * nuevo sistema, la importa automaticamente como su primera direccion.
	 * Se controla con un meta flag para no duplicar en cargas sucesivas.
	 *
	 * @param int $user_id ID del usuario.
	 */
	public function maybe_import_legacy_address( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		if ( get_user_meta( $user_id, '_hsa_legacy_imported', true ) ) {
			return;
		}

		$existing = get_user_meta( $user_id, HSA_USER_META_KEY, true );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			update_user_meta( $user_id, '_hsa_legacy_imported', 1 );
			return;
		}

		$billing_address_1 = get_user_meta( $user_id, 'billing_address_1', true );

		if ( empty( $billing_address_1 ) ) {
			// No hay direccion clasica que importar todavia; no marcamos el flag
			// para poder reintentar la importacion mas adelante.
			return;
		}

		$legacy = array(
			'id'           => $this->generate_address_id(),
			'alias'        => __( 'Casa', 'harmony-saved-addresses' ),
			'first_name'   => get_user_meta( $user_id, 'billing_first_name', true ),
			'last_name'    => get_user_meta( $user_id, 'billing_last_name', true ),
			'company'      => get_user_meta( $user_id, 'billing_company', true ),
			'country'      => get_user_meta( $user_id, 'billing_country', true ),
			'state'        => get_user_meta( $user_id, 'billing_state', true ),
			'city'         => get_user_meta( $user_id, 'billing_city', true ),
			'neighborhood' => '',
			'postcode'     => get_user_meta( $user_id, 'billing_postcode', true ),
			'address_1'    => $billing_address_1,
			'address_2'    => get_user_meta( $user_id, 'billing_address_2', true ),
			'references'   => '',
			'phone'        => get_user_meta( $user_id, 'billing_phone', true ),
			'email'        => get_user_meta( $user_id, 'billing_email', true ),
			'type'         => 'both',
			'is_default'   => true,
		);

		update_user_meta( $user_id, HSA_USER_META_KEY, array( $legacy ) );
		update_user_meta( $user_id, '_hsa_legacy_imported', 1 );
	}

	/**
	 * Convierte una direccion guardada al formato de campos de WooCommerce
	 * (billing_* o shipping_*) para poder inyectarla en el objeto de sesion/pedido.
	 *
	 * @param array  $address Direccion guardada.
	 * @param string $prefix  "billing" o "shipping".
	 * @return array<string, string>
	 */
	public function map_to_wc_fields( array $address, string $prefix ): array {
		$fields = array(
			$prefix . '_first_name' => $address['first_name'] ?? '',
			$prefix . '_last_name'  => $address['last_name'] ?? '',
			$prefix . '_company'    => $address['company'] ?? '',
			$prefix . '_country'    => $address['country'] ?? '',
			$prefix . '_state'      => $address['state'] ?? '',
			$prefix . '_city'       => $address['city'] ?? '',
			$prefix . '_postcode'   => $address['postcode'] ?? '',
			$prefix . '_address_1'  => $address['address_1'] ?? '',
			// Solo se concatena el interior real; el neighborhood se guarda
			// aparte como meta del pedido para no contaminar address_2 de WC.
			$prefix . '_address_2'  => trim( (string) ( $address['address_2'] ?? '' ) ),
		);

		if ( 'billing' === $prefix ) {
			$fields['billing_phone'] = $address['phone'] ?? '';
			$fields['billing_email'] = $address['email'] ?? '';
		}

		return $fields;
	}
}
