<?php
/**
 * Se ejecuta unicamente cuando el usuario elimina el plugin desde el
 * panel de plugins de WordPress (no en una simple desactivacion).
 * Solo borra datos si el administrador activo la opcion correspondiente
 * en WooCommerce > Ajustes > Direcciones guardadas.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$hsa_options = get_option( 'hsa_settings', array() );
$hsa_should_delete = isset( $hsa_options['delete_data_on_uninstall'] ) && 'yes' === $hsa_options['delete_data_on_uninstall'];

if ( ! $hsa_should_delete ) {
	return;
}

global $wpdb;

// Elimina los metadatos de direcciones y el flag de importacion de todos los usuarios.
delete_metadata( 'user', 0, 'harmony_saved_addresses', '', true );
delete_metadata( 'user', 0, '_hsa_legacy_imported', '', true );

// Elimina las opciones del plugin.
delete_option( 'hsa_settings' );
delete_option( '_hsa_flush_needed' );

// Elimina los metadatos guardados en pedidos (no afecta a los datos historicos
// de los pedidos en si, solo la referencia interna del plugin).
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_hsa_shipping_address_id', '_hsa_shipping_address_snapshot', '_hsa_billing_address_id', '_hsa_billing_address_snapshot', '_hsa_shipping_neighborhood', '_hsa_billing_neighborhood')"
);

flush_rewrite_rules();
