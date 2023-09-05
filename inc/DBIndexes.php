<?php

namespace WPMetaOptimizer;

class DBIndexes {
	/**
	 * Get DB table indexes
	 *
	 * @param string $table         Table name
	 * @param array  $ignoreColumns Ignore table index columns
	 *
	 * @return array|mixed
	 */
	public static function get( $table, $ignoreColumns = [] ) {
		global $wpdb;
		$indexes = wp_cache_get( 'db_table_indexes_' . $table, WPMETAOPTIMIZER_PLUGIN_KEY );

		if ( $indexes !== false )
			return $indexes;

		$ignoreColumns = "'" . implode( "','", $ignoreColumns ) . "'";
		$results       = $wpdb->get_results( "SHOW INDEXES FROM $table WHERE Column_name NOT IN ($ignoreColumns)" );

		$indexes = [];
		if ( is_array( $results ) ) {
			foreach ( $results as $result ) {
				$indexes[] = array(
					'unique' => $result->Non_unique == 0,
					'name'   => $result->Key_name,
					'index'  => intval( $result->Seq_in_index ),
					'column' => $result->Column_name
				);
			}
		}

		wp_cache_set( 'db_table_indexes_' . $table, $indexes, WPMETAOPTIMIZER_PLUGIN_KEY, WPMETAOPTIMIZER_CACHE_EXPIRE );

		return $indexes;
	}

	/**
	 * Add DB table index
	 *
	 * @param string $table  Table name
	 * @param string $column Column Name
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public static function add( $table, $column ) {
		global $wpdb;

		$result = $wpdb->query( "CREATE INDEX $column ON $table($column);" );

		if ( $result )
			self::clearCache( $table );

		return $result;
	}

	/**
	 * Remove DB table index
	 *
	 * @param string $table  Table name
	 * @param string $column Column Name
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public static function remove( $table, $column ) {
		global $wpdb;

		$tableIndexes = self::get( $table );

		$result = false;
		foreach ( $tableIndexes as $index ) {
			if ( $index['column'] == $column )
				$result = $wpdb->query( "DROP INDEX " . $index['name'] . " ON $table;" );
		}

		self::clearCache( $table );

		return $result;
	}

	/**
	 * check exists DB table index
	 *
	 * @param string $table         Table name
	 * @param string $column        Column Name
	 * @param array  $ignoreColumns Ignore table index columns
	 *
	 * @return bool
	 */
	public static function checkExists( $table, $column, $ignoreColumns = [] ) {
		$tableIndexes      = self::get( $table, $ignoreColumns );
		$tableIndexColumns = array_unique( wp_list_pluck( $tableIndexes, 'column' ) );

		return in_array( $column, $tableIndexColumns );
	}

	public static function clearCache( $table ) {
		wp_cache_delete( 'db_table_indexes_' . $table, WPMETAOPTIMIZER_PLUGIN_KEY );
	}
}