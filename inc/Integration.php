<?php

namespace WPMetaOptimizer;

class Integration extends Base {
	public static $instance = null;

	function __construct() {
		add_filter( 'acf/pre_load_metadata', [ $this, 'acfGetMeta' ], 10, 4 );
	}

	/**
	 * Advanced custom field
	 *
	 * @param null    $check   Default check value (null)
	 * @param int     $post_id Post ID
	 * @param string  $name    Meta name
	 * @param boolean $hidden  Meta key is hidden
	 *
	 * @return null|string|array
	 */
	function acfGetMeta( $check, $post_id, $name, $hidden ) {
		if ( ! function_exists( 'acf_decode_post_id' ) )
			return $check; // null

		// Decode $post_id for $type and $id.
		$decoded = acf_decode_post_id( $post_id );
		$id      = $decoded['id'];
		$type    = $decoded['type'];

		// Hidden meta uses an underscore prefix.
		$prefix = $hidden ? '_' : '';

		// Bail early if no $id (possible during new acf_form).
		if ( ! $id )
			return $check; // null

		if ( $type !== 'option' ) {
			$metaValue = get_metadata( $type, $id, "{$prefix}{$name}", true );

			return is_array( $metaValue ) && isset( $metaValue[0] ) ? $metaValue[0] : $metaValue;
		}

		return $check; // null
	}

	/**
	 * Returns an instance of class
	 *
	 * @return Integration
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new Integration();

		return self::$instance;
	}
}
