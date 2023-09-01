<?php

namespace WPMetaOptimizer;

// Check run from WP
defined( 'ABSPATH' ) || die();

class Install {
	/**
	 * Install plugin needed
	 * To Create plugin tables
	 * Add default options
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) )
			require_once( ABSPATH . str_replace( '/', DIRECTORY_SEPARATOR, '/wp-admin/includes/upgrade.php' ) );

		$tables = array(
			'post'    => $wpdb->postmeta . '_wpmo',
			'comment' => $wpdb->commentmeta . '_wpmo',
			'user'    => $wpdb->usermeta . '_wpmo',
			'term'    => $wpdb->termmeta . '_wpmo'
		);

		foreach ( $tables as $type => $table ) {
			if ( $wpdb->get_var( "show tables like '$table'" ) != $table ) {
				$sql = "CREATE TABLE `{$table}` (
                  `meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `{$type}_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL,
                   PRIMARY KEY (`meta_id`),
                   UNIQUE KEY `{$type}_id` (`{$type}_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

				dbDelta( $sql );
				dbDelta( "ALTER TABLE `{$table}` ROW_FORMAT=DYNAMIC;" );
			}
		}

		$newPluginOptions = $currentPluginOptions = get_option( 'wp_meta_optimizer', false );
		if ( ! is_array( $currentPluginOptions ) ) {
			$defaultPluginOptions = array(
				'support_wp_query'                       => 0,
				'support_wp_query_active_automatically'  => 1,
				'support_wp_query_deactive_while_import' => 1,
				'meta_save_types'                        => [
					'post'    => 1,
					'comment' => 1,
					'user'    => 1,
					'term'    => 1,
				],
				'import'                                 => [
					'post'    => 1,
					'comment' => 1,
					'user'    => 1,
					'term'    => 1,
				],
				'post_types'                             => [
					'post' => 1,
					'page' => 1
				],
				'import_items_number'                    => 1
			);

			update_option( 'wp_meta_optimizer', $defaultPluginOptions );
		} else {
			$oldVersion = get_option( 'wp_meta_optimizer_version', '1.0' );

			if ( version_compare( $oldVersion, '1.2.1', '<' ) ) {
				$newPluginOptions['import'] = [
					'post'    => 1,
					'comment' => 1,
					'user'    => 1,
					'term'    => 1,
				];
				foreach ( $tables as $type => $table ) {
					$newPluginOptions[ 'import_' . $type . '_latest_id' ] = null;
				}

				update_option( 'wp_meta_optimizer', $newPluginOptions );
			}
		}

		if ( ! function_exists( 'get_plugin_data' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$pluginData = get_plugin_data( WPMETAOPTIMIZER_PLUGIN_FILE_PATH );
		update_option( 'wp_meta_optimizer_version', $pluginData['Version'] );
	}
}
