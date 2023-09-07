<?php

namespace WPMetaOptimizer;

class Tools {

	/**
	 * Get orphaned meta count (post, comment, user, term)
	 *
	 * @param string $type Type name (post, comment, user, term)
	 *
	 * @return int Orphaned meta count
	 */
	public static function getOrphanedMetaCount( $type ) {
		global $wpdb;
		$metaTable = Helpers::getInstance()->getWPMetaTableName( $type );
		$mainTable = Helpers::getInstance()->getWPPrimaryTableName( $type );

		if ( ! $metaTable || ! $mainTable )
			return 0;

		$primaryColumn = 'ID';
		if ( in_array( $type, [ 'term', 'comment' ] ) )
			$primaryColumn = $type . '_ID';

		$metaColumn = $type . '_id';

		return intval( $wpdb->get_var( "SELECT COUNT(*) FROM $metaTable LEFT JOIN $mainTable ON $mainTable.$primaryColumn = $metaTable.$metaColumn WHERE $mainTable.$primaryColumn IS NULL" ) );
	}

	/**
	 * Delete orphaned meta (post, comment, user, term)
	 *
	 * @param string $type Type name (post, comment, user, term)
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public static function deleteOrphanedMeta( $type ) {
		global $wpdb;
		$metaTable = Helpers::getInstance()->getWPMetaTableName( $type );
		$mainTable = Helpers::getInstance()->getWPPrimaryTableName( $type );

		if ( ! $metaTable || ! $mainTable )
			return false;

		$primaryColumn = 'ID';
		if ( in_array( $type, [ 'term', 'comment' ] ) )
			$primaryColumn = $type . '_ID';

		$metaColumn = $type . '_id';

		return $wpdb->query( "DELETE $metaTable FROM $metaTable LEFT JOIN $mainTable ON $mainTable.$primaryColumn = $metaTable.$metaColumn WHERE $mainTable.$primaryColumn IS NULL" );
	}

	/**
	 * get posts count base on post type or post status
	 *
	 * @param string $postType   Post type name
	 * @param string $postStatus Post status
	 *
	 * @return int Posts count
	 */
	public static function getPostsCount( $postType = null, $postStatus = null ) {
		global $wpdb;

		if ( is_null( $postType ) && is_null( $postStatus ) )
			return 0;

		$wheres = [];

		if ( ! empty( $postType ) )
			$wheres[] = "post_type = '$postType'";

		if ( ! empty( $postStatus ) )
			$wheres[] = "post_status = '$postStatus'";

		$wheres = implode( ' AND ', $wheres );

		return $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE $wheres" );
	}

	/**
	 * Delete posts base on post type or post status
	 *
	 * @param string $postType   Post type name
	 * @param string $postStatus Post status
	 *
	 * @return false|void
	 */
	public static function deletePosts( $postType = null, $postStatus = null ) {
		global $wpdb;
		if ( is_null( $postType ) && is_null( $postStatus ) )
			return false;

		$wheres = [];

		if ( ! empty( $postType ) )
			$wheres[] = "post_type = '$postType'";

		if ( ! empty( $postStatus ) )
			$wheres[] = "post_status = '$postStatus'";

		$wheres = implode( ' AND ', $wheres );

		$posts = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE $wheres" );

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	/**
	 * Get expired transients count
	 *
	 * @return int expired transients count
	 */
	public static function getExpiredTransientsCount() {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(option_id) FROM $wpdb->options
			WHERE option_name LIKE '_transient_timeout_%' AND option_value <= %d",
			time()
		) );

		return intval( $count );
	}

	/**
	 * Delete expired transients
	 *
	 * @return int|true Delete transient count
	 */
	public static function deleteExpiredTransients() {
		global $wpdb;

		if ( function_exists( 'delete_expired_transients' ) ) {
			delete_expired_transients();

			return true;
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT option_name FROM $wpdb->options
			WHERE option_name LIKE '_transient_timeout_%' AND option_value <= %d",
			time()
		) );

		$count = 0;
		foreach ( $results as $transient ) {
			$transientName = str_replace( '_transient_timeout_', '', $transient->option_name );
			delete_transient( $transientName );
			$count ++;
		}

		return $count;
	}

	/**
	 * Get database tables count
	 *
	 * @return int database tables count
	 */
	public static function getDatabaseTablesCount() {
		global $wpdb;

		return intval( $wpdb->get_var( "SELECT COUNT(TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$wpdb->dbname}'" ) );
	}

	/**
	 * Optimize database tables
	 *
	 * @return int Return optimized tables count
	 */
	public static function optimizeDatabaseTables() {
		global $wpdb;

		$tables = $wpdb->get_results( "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$wpdb->dbname}'" );
		$count  = 0;
		foreach ( $tables as $table ) {
			$wpdb->query( "OPTIMIZE TABLE " . $table->TABLE_NAME );
			$count ++;
		}

		return $count;
	}
}