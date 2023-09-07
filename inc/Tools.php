<?php

namespace WPMetaOptimizer;

class Tools {
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
}