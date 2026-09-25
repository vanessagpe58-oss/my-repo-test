<?php
/**
 * Maneja las peticiones AJAX (admin-ajax.php) y expone tambien una API REST
 * equivalente. Todas las operaciones verifican nonce, permisos y propiedad
 * del recurso en el servidor, sin confiar en la validacion de JavaScript.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSA_Ajax_Handler {

	private const NONCE_ACTION = 'hsa_ajax_nonce';

	private HSA_Address_Storage $storage;

	public function __construct( HSA_Address_Storage $storage ) {
		$this->storage = $storage;

		// AJAX para usuarios registrados (nopriv no aplica: se requiere sesion).
		add_action( 'wp_ajax_hsa_save_address', array( $this, 'ajax_save_address' ) );
		add_action( 'wp_ajax_hsa_delete_address', array( $this, 'ajax_delete_address' ) );
		add_action( 'wp_ajax_hsa_set_default_address', array( $this, 'ajax_set_default_address' ) );
		add_action( 'wp_ajax_hsa_get_addresses', array( $this, 'ajax_get_addresses' ) );
		add_action( 'wp_ajax_hsa_select_address', array( $this, 'ajax_select_address' ) );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Verifica que haya un usuario logueado y que el nonce AJAX sea valido.
	 * Detiene la ejecucion con una respuesta JSON de error si algo falla.
	 */
	private function guard_ajax_request(): int {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		HSA_Validation::verify_nonce_or_die( $nonce, self::NONCE_ACTION );

		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Debes iniciar sesion para guardar direcciones.', 'harmony-saved-addresses' ) ),
				401
			);
		}

		return $user_id;
	}

	/**
	 * AJAX: guarda (crea o edita) una direccion.
	 */
	public function ajax_save_address(): void {
		$user_id = $this->guard_ajax_request();

		$result = HSA_Validation::sanitize_and_validate( $_POST );

		if ( ! empty( $result['errors'] ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $result['errors'] ), 'errors' => $result['errors'] ), 422 );
		}

		// Verifica propiedad: si se envia un ID, debe pertenecer al usuario actual.
		if ( ! empty( $result['data']['id'] ) && null === $this->storage->get_address( $user_id, $result['data']['id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permiso para modificar esta direccion.', 'harmony-saved-addresses' ) ), 403 );
		}

		$saved = $this->storage->save_address( $user_id, $result['data'] );

		if ( is_wp_error( $saved ) ) {
			wp_send_json_error( array( 'message' => $saved->get_error_message() ), 400 );
		}

		wc_get_logger()->info(
			sprintf( 'Direccion guardada por el usuario #%d (ID: %s)', $user_id, $saved['id'] ?? '' ),
			array( 'source' => 'harmony-saved-addresses' )
		);

		wp_send_json_success(
			array(
				'address'   => $saved,
				'addresses' => $this->storage->get_addresses( $user_id ),
			)
		);
	}

	/**
	 * AJAX: elimina una direccion.
	 */
	public function ajax_delete_address(): void {
		$user_id = $this->guard_ajax_request();

		$address_id = isset( $_POST['address_id'] ) ? sanitize_text_field( wp_unslash( $_POST['address_id'] ) ) : '';

		if ( '' === $address_id ) {
			wp_send_json_error( array( 'message' => __( 'Direccion no valida.', 'harmony-saved-addresses' ) ), 400 );
		}

		if ( 'yes' !== HSA_Admin_Settings::get_option( 'allow_delete_addresses', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'La eliminacion de direcciones esta deshabilitada.', 'harmony-saved-addresses' ) ), 403 );
		}

		$result = $this->storage->delete_address( $user_id, $address_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wc_get_logger()->info(
			sprintf( 'Direccion eliminada por el usuario #%d (ID: %s)', $user_id, $address_id ),
			array( 'source' => 'harmony-saved-addresses' )
		);

		wp_send_json_success( array( 'addresses' => $this->storage->get_addresses( $user_id ) ) );
	}

	/**
	 * AJAX: marca una direccion como predeterminada.
	 */
	public function ajax_set_default_address(): void {
		$user_id = $this->guard_ajax_request();

		$address_id = isset( $_POST['address_id'] ) ? sanitize_text_field( wp_unslash( $_POST['address_id'] ) ) : '';

		$result = $this->storage->set_default_address( $user_id, $address_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'addresses' => $this->storage->get_addresses( $user_id ) ) );
	}

	/**
	 * AJAX: obtiene la lista actual de direcciones del usuario.
	 */
	public function ajax_get_addresses(): void {
		$user_id = $this->guard_ajax_request();
		wp_send_json_success( array( 'addresses' => $this->storage->get_addresses( $user_id ) ) );
	}

	/**
	 * AJAX: registra que direccion fue seleccionada para el pedido actual
	 * y devuelve los campos WooCommerce equivalentes para actualizar el checkout.
	 */
	public function ajax_select_address(): void {
		$user_id = $this->guard_ajax_request();

		$address_id = isset( $_POST['address_id'] ) ? sanitize_text_field( wp_unslash( $_POST['address_id'] ) ) : '';
		$context    = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'shipping';

		if ( ! in_array( $context, array( 'shipping', 'billing' ), true ) ) {
			$context = 'shipping';
		}

		$address = $this->storage->get_address( $user_id, $address_id );

		if ( ! $address ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permiso para usar esta direccion.', 'harmony-saved-addresses' ) ), 403 );
		}

		if ( WC()->session ) {
			WC()->session->set( 'hsa_selected_' . $context . '_id', $address_id );
			WC()->customer->set_props( $this->storage->map_to_wc_fields( $address, $context ) );
			WC()->customer->save();
		}

		wp_send_json_success(
			array(
				'fields' => $this->storage->map_to_wc_fields( $address, $context ),
			)
		);
	}

	/**
	 * Registra las rutas REST equivalentes, utiles para integraciones con
	 * WooCommerce Checkout Blocks (que suele preferir REST sobre admin-ajax).
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'harmony-addresses/v1',
			'/addresses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_addresses' ),
					'permission_callback' => array( $this, 'rest_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_save_address' ),
					'permission_callback' => array( $this, 'rest_permission_check' ),
				),
			)
		);

		register_rest_route(
			'harmony-addresses/v1',
			'/addresses/(?P<id>[a-zA-Z0-9_\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_address' ),
					'permission_callback' => array( $this, 'rest_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_save_address' ),
					'permission_callback' => array( $this, 'rest_permission_check' ),
				),
			)
		);

		register_rest_route(
			'harmony-addresses/v1',
			'/addresses/(?P<id>[a-zA-Z0-9_\-]+)/default',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'rest_set_default' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);
	}

	/**
	 * Solo usuarios registrados y autenticados pueden usar la API REST del plugin.
	 * WordPress ya valida el nonce X-WP-Nonce automaticamente para peticiones REST
	 * autenticadas por cookie.
	 */
	public function rest_permission_check(): bool {
		return is_user_logged_in();
	}

	public function rest_get_addresses(): WP_REST_Response {
		$user_id = get_current_user_id();
		return new WP_REST_Response( array( 'addresses' => $this->storage->get_addresses( $user_id ) ), 200 );
	}

	public function rest_save_address( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$params  = $request->get_params();

		if ( $request->get_param( 'id' ) && empty( $params['id'] ) ) {
			$params['id'] = $request->get_param( 'id' );
		}

		$result = HSA_Validation::sanitize_and_validate( $params );

		if ( ! empty( $result['errors'] ) ) {
			return new WP_REST_Response( array( 'message' => implode( ' ', $result['errors'] ) ), 422 );
		}

		if ( ! empty( $result['data']['id'] ) && null === $this->storage->get_address( $user_id, $result['data']['id'] ) ) {
			return new WP_REST_Response( array( 'message' => __( 'No tienes permiso para modificar esta direccion.', 'harmony-saved-addresses' ) ), 403 );
		}

		$saved = $this->storage->save_address( $user_id, $result['data'] );

		if ( is_wp_error( $saved ) ) {
			return new WP_REST_Response( array( 'message' => $saved->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'address' => $saved, 'addresses' => $this->storage->get_addresses( $user_id ) ), 200 );
	}

	public function rest_delete_address( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$id      = sanitize_text_field( $request->get_param( 'id' ) );

		$result = $this->storage->delete_address( $user_id, $id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'addresses' => $this->storage->get_addresses( $user_id ) ), 200 );
	}

	public function rest_set_default( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$id      = sanitize_text_field( $request->get_param( 'id' ) );

		$result = $this->storage->set_default_address( $user_id, $id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'addresses' => $this->storage->get_addresses( $user_id ) ), 200 );
	}
}
