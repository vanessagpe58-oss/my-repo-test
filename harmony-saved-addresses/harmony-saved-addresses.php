<?php
/**
 * Plugin Name:       Harmony Saved Addresses for WooCommerce
 * Plugin URI:        https://example.com/harmony-saved-addresses
 * Description:       Convierte la seccion de direccion del checkout en un selector visual de direcciones guardadas, similar a la experiencia de Mercado Libre. Compatible con Checkout clasico y Checkout Blocks de WooCommerce.
 * Version:           1.1.8
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Harmony Plugins
 * Text Domain:       harmony-saved-addresses
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 *
 * @package Harmony_Saved_Addresses
 */

// Evita el acceso directo al archivo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constantes principales del plugin.
 * Se usa el prefijo unico "HSA_" para evitar colisiones con otros plugins.
 */
define( 'HSA_VERSION', '1.1.7' );
define( 'HSA_PLUGIN_FILE', __FILE__ );
define( 'HSA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HSA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HSA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'HSA_TEXT_DOMAIN', 'harmony-saved-addresses' );
define( 'HSA_USER_META_KEY', 'harmony_saved_addresses' );
define( 'HSA_OPTIONS_KEY', 'hsa_settings' );

/**
 * Clase principal que arranca el plugin.
 * Todo el codigo vive dentro del plugin: no se modifica el tema,
 * WooCommerce ni el nucleo de WordPress directamente.
 */
final class Harmony_Saved_Addresses {

	/**
	 * Instancia unica (patron singleton).
	 *
	 * @var Harmony_Saved_Addresses|null
	 */
	private static ?Harmony_Saved_Addresses $instance = null;

	/**
	 * Manejador de almacenamiento de direcciones.
	 *
	 * @var HSA_Address_Storage
	 */
	public HSA_Address_Storage $storage;

	/**
	 * Manejador del checkout.
	 *
	 * @var HSA_Checkout_Addresses
	 */
	public HSA_Checkout_Addresses $checkout;

	/**
	 * Manejador de "Mi cuenta".
	 *
	 * @var HSA_Account_Addresses
	 */
	public HSA_Account_Addresses $account;

	/**
	 * Manejador de ajustes de administracion.
	 *
	 * @var HSA_Admin_Settings
	 */
	public HSA_Admin_Settings $admin_settings;

	/**
	 * Manejador de peticiones AJAX / REST.
	 *
	 * @var HSA_Ajax_Handler
	 */
	public HSA_Ajax_Handler $ajax_handler;

	/** @var HSA_Thankyou_Page */
	public HSA_Thankyou_Page $thankyou_page;

	/**
	 * Obtiene la instancia unica del plugin.
	 */
	public static function get_instance(): Harmony_Saved_Addresses {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado (singleton).
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( HSA_PLUGIN_FILE, array( __CLASS__, 'on_activate' ) );
		register_deactivation_hook( HSA_PLUGIN_FILE, array( __CLASS__, 'on_deactivate' ) );
	}

	/**
	 * Inicializa el plugin una vez que todos los plugins estan cargados.
	 * Verifica que WooCommerce este activo antes de continuar.
	 */
	public function init(): void {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_woocommerce' ) );
			return;
		}

		$this->load_textdomain();
		$this->load_dependencies();
		$this->init_modules();
		$this->declare_hpos_and_blocks_compatibility();
	}

	/**
	 * Comprueba si WooCommerce esta activo.
	 */
	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Muestra un aviso si WooCommerce no esta activo.
	 */
	public function notice_missing_woocommerce(): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Harmony Saved Addresses for WooCommerce requiere que WooCommerce este instalado y activo.', 'harmony-saved-addresses' );
		echo '</p></div>';
	}

	/**
	 * Carga el archivo de traducciones.
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain( HSA_TEXT_DOMAIN, false, dirname( HSA_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Incluye todos los archivos de clases del plugin.
	 */
	private function load_dependencies(): void {
		require_once HSA_PLUGIN_DIR . 'includes/class-validation.php';
		require_once HSA_PLUGIN_DIR . 'includes/class-address-storage.php';
		require_once HSA_PLUGIN_DIR . 'includes/class-checkout-addresses.php';
		require_once HSA_PLUGIN_DIR . 'includes/class-account-addresses.php';
		require_once HSA_PLUGIN_DIR . 'includes/class-admin-settings.php';
		require_once HSA_PLUGIN_DIR . 'includes/class-ajax-handler.php';
		require_once HSA_PLUGIN_DIR . 'includes/class-thankyou-page.php';
	}

	/**
	 * Instancia cada modulo del plugin.
	 */
	private function init_modules(): void {
		$this->storage        = new HSA_Address_Storage();
		$this->checkout       = new HSA_Checkout_Addresses( $this->storage );
		$this->account        = new HSA_Account_Addresses( $this->storage );
		$this->admin_settings = new HSA_Admin_Settings();
		$this->ajax_handler   = new HSA_Ajax_Handler( $this->storage );
		$this->thankyou_page  = new HSA_Thankyou_Page();
	}

	/**
	 * Declara compatibilidad con HPOS (Almacenamiento de pedidos de alto rendimiento)
	 * y con WooCommerce Checkout Blocks.
	 */
	private function declare_hpos_and_blocks_compatibility(): void {
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'custom_order_tables',
						HSA_PLUGIN_FILE,
						true
					);
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'cart_checkout_blocks',
						HSA_PLUGIN_FILE,
						true
					);
				}
			}
		);
	}

	/**
	 * Se ejecuta al activar el plugin.
	 */
	public static function on_activate(): void {
		if ( ! get_option( HSA_OPTIONS_KEY ) ) {
			require_once HSA_PLUGIN_DIR . 'includes/class-admin-settings.php';
			update_option( HSA_OPTIONS_KEY, HSA_Admin_Settings::get_default_settings() );
		}
		flush_rewrite_rules();
	}

	/**
	 * Se ejecuta al desactivar el plugin (no elimina datos).
	 */
	public static function on_deactivate(): void {
		flush_rewrite_rules();
	}
}

/**
 * Funcion de acceso rapido a la instancia principal del plugin.
 */
function hsa(): Harmony_Saved_Addresses {
	return Harmony_Saved_Addresses::get_instance();
}

hsa();
