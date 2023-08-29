<?php

namespace WPMetaOptimizer;

// Check run from WP
defined( 'ABSPATH' ) || die();

/**
 * Taxonomy API: TermQueries class.
 *
 * @package    WPMetaOptimizer
 * @subpackage Taxonomy
 * @since      1.0
 */
class TermQueries {
	public static $instance = null;
	private $Queries;

	function __construct( $Queries ) {
		$this->Queries = $Queries;

		add_action( 'pre_get_terms', [ $this, 'changeTermQuery' ], 9999, 3 );
		add_filter( 'terms_clauses', [ $this, 'changeTermsClauses' ], 9999, 3 );
	}

	/**
	 * Change term meta query class
	 *
	 * @param \WP_Term_Query $termQuery Term query
	 */
	function changeTermQuery( $termQuery ) {
		$this->Queries->metaQuery = $termQuery->meta_query = new MetaQuery();
		$termQuery->meta_query->parse_query_vars( $termQuery->query_vars );
		$this->Queries->queryVars = $termQuery->query_vars;
	}

	/**
	 * Change term clauses query
	 *
	 * @param string[] $clauses    {
	 *                             Associative array of the clauses for the query.
	 *
	 * @type string    $fields     The SELECT clause of the query.
	 * @type string    $join       The JOIN clause of the query.
	 * @type string    $where      The WHERE clause of the query.
	 * @type string    $distinct   The DISTINCT clause of the query.
	 * @type string    $orderby    The ORDER BY clause of the query.
	 * @type string    $order      The ORDER clause of the query.
	 * @type string    $limits     The LIMIT clause of the query.
	 *                             }
	 *
	 * @param string[] $taxonomies An array of taxonomy names.
	 * @param array    $args       An array of term query arguments.
	 *
	 * @return array
	 * @copyright Base on WP_Term_Query:get_terms method.
	 *
	 */
	function changeTermsClauses( $clauses, $taxonomies, $args ) {
		$this->Queries->queryVars['order'] = isset( $this->Queries->queryVars['order'] ) ? strtoupper( $this->Queries->queryVars['order'] ) : '';
		$order                             = $this->Queries->parseOrder( $this->Queries->queryVars['order'] );
		// 'term_order' is a legal sort order only when joining the relationship table.
		$_orderby = $this->Queries->queryVars['orderby'];

		if ( empty( $this->Queries->queryVars['orderby'] ) || 'term_order' === $_orderby && empty( $this->Queries->queryVars['object_ids'] ) ) {
			$ordersby = [ 'term_id' ];
		} elseif ( is_array( $_orderby ) ) {
			$ordersby = $_orderby;
		} else {
			// 'orderby' values may be a comma- or space-separated list.
			$ordersby = preg_split( '/[,\s]+/', $_orderby );
		}

		$orderby_array = array();
		foreach ( $ordersby as $_key => $_value ) {
			if ( is_int( $_key ) ) {
				// Integer key means this is a flat array of 'orderby' fields.
				$_orderby = $_value;
				$_order   = $order;
			} else {
				// Non-integer key means this the key is the field and the value is ASC/DESC.
				$_orderby = $_key;
				$_order   = $_value;
			}

			$parsed = $this->termParseOrderby( $_orderby );

			if ( ! $parsed )
				continue;

			$orderby_array[] = $parsed . ' ' . $this->Queries->parseOrder( $_order );
		}

		if ( ! empty( $orderby_array ) ) {
			$clauses['orderby'] = 'ORDER BY ' . implode( ', ', $orderby_array );
			$clauses['order']   = '';
		}

		// $clauses['distinct'] = '';

		return $clauses;
	}

	/**
	 * Parse and sanitize 'orderby' keys passed to the term query.
	 *
	 * @param string $orderby_raw Alias for the field to order by.
	 *
	 * @return string|false Value to used in the ORDER clause. False otherwise.
	 * @global \wpdb $wpdb        WordPress database abstraction object.
	 *
	 * @copyright Base on WP_Term_Query:parse_orderby method.
	 *
	 * @since     4.6.0
	 *
	 */
	protected function termParseOrderby( $orderby_raw ) {
		$orderbyKeys = is_array( $orderby_raw ) ? array_keys( $orderby_raw ) : [];
		$_orderby    = is_array( $orderby_raw ) ? reset($orderbyKeys) : strtolower( $orderby_raw );

		$maybe_orderby_meta = false;

		if ( in_array( $_orderby, array( 'term_id', 'name', 'slug', 'term_group' ), true ) ) {
			$orderby = "t.$_orderby";
		} elseif ( in_array( $_orderby, array(
			'count',
			'parent',
			'taxonomy',
			'term_taxonomy_id',
			'description'
		), true ) ) {
			$orderby = "tt.$_orderby";
		} elseif ( 'term_order' === $_orderby ) {
			$orderby = 'tr.term_order';
		} elseif ( 'include' === $_orderby && ! empty( $this->Queries->queryVars['include'] ) ) {
			$include = implode( ',', wp_parse_id_list( $this->Queries->queryVars['include'] ) );
			$orderby = "FIELD( t.term_id, $include )";
		} elseif ( 'slug__in' === $_orderby && ! empty( $this->Queries->queryVars['slug'] ) && is_array( $this->Queries->queryVars['slug'] ) ) {
			$slugs   = implode( "', '", array_map( 'sanitize_title_for_query', $this->Queries->queryVars['slug'] ) );
			$orderby = "FIELD( t.slug, '" . $slugs . "')";
		} elseif ( 'none' === $_orderby ) {
			$orderby = '';
		} elseif ( empty( $_orderby ) || 'id' === $_orderby || 'term_id' === $_orderby ) {
			$orderby = 't.term_id';
		} else {
			$orderby = 't.name';

			// This may be a value of orderby related to meta.
			$maybe_orderby_meta = true;
		}

		/**
		 * Filters the ORDERBY clause of the terms query.
		 *
		 * @param string   $orderby    `ORDERBY` clause of the terms query.
		 * @param array    $args       An array of term query arguments.
		 * @param string[] $taxonomies An array of taxonomy names.
		 *
		 * @since 2.8.0
		 *
		 */
		$orderby = apply_filters( 'get_terms_orderby', $orderby, $this->Queries->queryVars, $this->Queries->queryVars['taxonomy'] );

		// Run after the 'get_terms_orderby' filter for backward compatibility.
		if ( $maybe_orderby_meta ) {
			$maybe_orderby_meta = $this->termParseOrderbyMeta( $_orderby );
			if ( $maybe_orderby_meta ) {
				$orderby = $maybe_orderby_meta;
			}
		}

		return $orderby;
	}

	/**
	 * Generate the ORDER BY clause for an 'orderby' param that is potentially related to a meta query.
	 *
	 * @param string $orderby_raw Raw 'orderby' value passed to WP_Term_Query.
	 *
	 * @return string ORDER BY clause.
	 * @copyright Base on WP_Term_Query:parse_orderby_meta method.
	 *
	 * @since     4.6.0
	 *
	 */
	protected function termParseOrderbyMeta( $orderby_raw ) {
		$orderby = '';

		// Tell the meta query to generate its SQL, so we have access to table aliases.
		$this->Queries->metaQuery->get_sql( 'term', 't', 'term_id' );
		$meta_clauses = $this->Queries->metaQuery->get_clauses();
		if ( ! $meta_clauses || ! $orderby_raw ) {
			return $orderby;
		}

		$allowed_keys     = array();
		$primary_meta_key = null;
		// $primary_meta_query = reset($meta_clauses);
		$primary_meta_query = isset( $meta_clauses[ $orderby_raw ] ) ? $meta_clauses[ $orderby_raw ] : reset( $meta_clauses );
		if ( ! empty( $primary_meta_query['key'] ) ) {
			$primary_meta_key = $primary_meta_query['key'];
			$allowed_keys[]   = $primary_meta_key;
		}

		$allowed_keys[] = 'meta_value';
		$allowed_keys[] = 'meta_value_num';
		$allowed_keys   = array_merge( $allowed_keys, array_keys( $meta_clauses ) );

		if ( ! in_array( $orderby_raw, $allowed_keys, true ) ) {
			return $orderby;
		}

		switch ( $orderby_raw ) {
			case $primary_meta_key:
			case 'meta_value':
				/* if (!empty($primary_meta_query['type'])) {
					$orderby = "CAST({$primary_meta_query['alias']}.meta_value AS {$primary_meta_query['cast']})";
				} else {
					$orderby = "{$primary_meta_query['alias']}.meta_value";
				} */
				if ( ! empty( $primary_meta_query['type'] ) ) {
					$orderby = "CAST({$primary_meta_query['alias']}.{$primary_meta_key} AS {$primary_meta_query['cast']})";
				} else {
					$orderby = "{$primary_meta_query['alias']}.{$primary_meta_key}";
				}

				break;

			case 'meta_value_num':
				// $orderby = "{$primary_meta_query['alias']}.meta_value+0";
				$orderby = "{$primary_meta_query['alias']}.{$primary_meta_key}+0";
				break;

			default:
				if ( array_key_exists( $orderby_raw, $meta_clauses ) ) {
					// $orderby corresponds to a meta_query clause.
					$meta_clause = $meta_clauses[ $orderby_raw ];
					// $orderby     = "CAST({$meta_clause['alias']}.meta_value AS {$meta_clause['cast']})";
					$orderby = "CAST({$meta_clause['alias']}.{$primary_meta_key} AS {$meta_clause['cast']})";
				}
				break;
		}

		return $orderby;
	}

	/**
	 * Returns an instance of class
	 *
	 * @return TermQueries
	 */
	static function getInstance( $Queries ) {
		if ( self::$instance == null )
			self::$instance = new TermQueries( $Queries );

		return self::$instance;
	}
}
