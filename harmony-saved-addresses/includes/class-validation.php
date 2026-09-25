<?php
/**
 * Validacion y sanitizacion de datos de direcciones.
 * IMPORTANTE: nunca se debe confiar unicamente en la validacion de JavaScript.
 * Toda peticion pasa por esta clase en el servidor.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSA_Validation {

	/**
	 * Campos obligatorios de una direccion.
	 *
	 * @var string[]
	 */
	private const REQUIRED_FIELDS = array(
		'first_name',
		'last_name',
		'country',
		'state',
		'city',
		'postcode',
		'address_1',
		'phone',
	);

	/**
	 * Sanitiza y valida un arreglo de datos de direccion enviado por el cliente.
	 *
	 * @param array $raw Datos crudos ($_POST o payload REST).
	 * @return array{data: array, errors: string[]} Datos limpios y lista de errores.
	 */
	public static function sanitize_and_validate( array $raw ): array {
		$errors = array();

		$clean = array(
			'id'           => isset( $raw['id'] ) ? sanitize_text_field( wp_unslash( $raw['id'] ) ) : '',
			'alias'        => isset( $raw['alias'] ) ? sanitize_text_field( wp_unslash( $raw['alias'] ) ) : '',
			'first_name'   => isset( $raw['first_name'] ) ? sanitize_text_field( wp_unslash( $raw['first_name'] ) ) : '',
			'last_name'    => isset( $raw['last_name'] ) ? sanitize_text_field( wp_unslash( $raw['last_name'] ) ) : '',
			'company'      => isset( $raw['company'] ) ? sanitize_text_field( wp_unslash( $raw['company'] ) ) : '',
			'country'      => isset( $raw['country'] ) ? sanitize_text_field( wp_unslash( $raw['country'] ) ) : '',
			'state'        => isset( $raw['state'] ) ? sanitize_text_field( wp_unslash( $raw['state'] ) ) : '',
			'city'         => isset( $raw['city'] ) ? sanitize_text_field( wp_unslash( $raw['city'] ) ) : '',
			'neighborhood' => isset( $raw['neighborhood'] ) ? sanitize_text_field( wp_unslash( $raw['neighborhood'] ) ) : '',
			'postcode'     => isset( $raw['postcode'] ) ? sanitize_text_field( wp_unslash( $raw['postcode'] ) ) : '',
			'address_1'    => isset( $raw['address_1'] ) ? sanitize_text_field( wp_unslash( $raw['address_1'] ) ) : '',
			'address_2'    => isset( $raw['address_2'] ) ? sanitize_text_field( wp_unslash( $raw['address_2'] ) ) : '',
			'references'   => isset( $raw['references'] ) ? sanitize_textarea_field( wp_unslash( $raw['references'] ) ) : '',
			'phone'        => isset( $raw['phone'] ) ? sanitize_text_field( wp_unslash( $raw['phone'] ) ) : '',
			'email'        => isset( $raw['email'] ) ? sanitize_email( wp_unslash( $raw['email'] ) ) : '',
			'type'         => isset( $raw['type'] ) ? sanitize_text_field( wp_unslash( $raw['type'] ) ) : 'both',
			'is_default'   => ! empty( $raw['is_default'] ),
		);

		if ( ! in_array( $clean['type'], array( 'shipping', 'billing', 'both' ), true ) ) {
			$clean['type'] = 'both';
		}

		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( '' === trim( (string) $clean[ $field ] ) ) {
				$errors[] = self::get_required_message( $field );
			}
		}

		if ( '' !== $clean['email'] && ! is_email( $clean['email'] ) ) {
			$errors[] = __( 'El correo electronico no es valido.', 'harmony-saved-addresses' );
		}

		if ( '' !== $clean['phone'] && ! self::is_valid_phone( $clean['phone'] ) ) {
			$errors[] = __( 'El telefono no es valido. Usa solo numeros, espacios, guiones o el simbolo +.', 'harmony-saved-addresses' );
		}

		if ( '' !== $clean['postcode'] && ! self::is_valid_postcode( $clean['postcode'], $clean['country'] ) ) {
			$errors[] = __( 'El codigo postal no es valido.', 'harmony-saved-addresses' );
		}

		if ( '' !== $clean['country'] && function_exists( 'WC' ) ) {
			$countries = WC()->countries->get_countries();
			if ( ! isset( $countries[ $clean['country'] ] ) ) {
				$errors[] = __( 'El pais seleccionado no es valido.', 'harmony-saved-addresses' );
			} elseif ( '' !== $clean['state'] ) {
				$states = WC()->countries->get_states( $clean['country'] );
				if ( is_array( $states ) && ! empty( $states ) && ! isset( $states[ $clean['state'] ] ) ) {
					$errors[] = __( 'El estado seleccionado no es valido para el pais elegido.', 'harmony-saved-addresses' );
				}
			}
		}

		if ( '' === $clean['alias'] ) {
			$clean['alias'] = __( 'Mi direccion', 'harmony-saved-addresses' );
		}

		return array(
			'data'   => $clean,
			'errors' => $errors,
		);
	}

	/**
	 * Devuelve el mensaje de error para un campo obligatorio.
	 */
	private static function get_required_message( string $field ): string {
		$labels = array(
			'first_name' => __( 'El nombre es obligatorio.', 'harmony-saved-addresses' ),
			'last_name'  => __( 'Los apellidos son obligatorios.', 'harmony-saved-addresses' ),
			'country'    => __( 'El pais es obligatorio.', 'harmony-saved-addresses' ),
			'state'      => __( 'El estado es obligatorio.', 'harmony-saved-addresses' ),
			'city'       => __( 'El municipio o ciudad es obligatorio.', 'harmony-saved-addresses' ),
			'postcode'   => __( 'El codigo postal es obligatorio.', 'harmony-saved-addresses' ),
			'address_1'  => __( 'La calle y el numero exterior son obligatorios.', 'harmony-saved-addresses' ),
			'phone'      => __( 'El telefono es obligatorio.', 'harmony-saved-addresses' ),
		);

		return $labels[ $field ] ?? sprintf(
			/* translators: %s: field name */
			__( 'El campo %s es obligatorio.', 'harmony-saved-addresses' ),
			$field
		);
	}

	/**
	 * Valida un numero de telefono de forma flexible (formatos internacionales).
	 */
	private static function is_valid_phone( string $phone ): bool {
		return (bool) preg_match( '/^[+0-9\s\-()]{7,20}$/', $phone );
	}

	/**
	 * Valida el codigo postal. Para Mexico exige 5 digitos, para otros paises
	 * exige un formato alfanumerico generico razonable.
	 */
	private static function is_valid_postcode( string $postcode, string $country ): bool {
		if ( 'MX' === $country ) {
			return (bool) preg_match( '/^\d{5}$/', $postcode );
		}
		return (bool) preg_match( '/^[A-Za-z0-9\s\-]{2,12}$/', $postcode );
	}

	/**
	 * Verifica un nonce de WordPress y detiene la ejecucion si es invalido.
	 *
	 * @param string $nonce  Valor del nonce recibido.
	 * @param string $action Accion del nonce.
	 */
	public static function verify_nonce_or_die( string $nonce, string $action ): void {
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error(
				array( 'message' => __( 'La sesion ha expirado. Recarga la pagina e intenta de nuevo.', 'harmony-saved-addresses' ) ),
				403
			);
		}
	}
}
