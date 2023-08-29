<?php

namespace WPMetaOptimizer;

// Check run from WP
defined( 'ABSPATH' ) || die();

/**
 * Query API: PostQueries class.
 *
 * @package    WPMetaOptimizer
 * @subpackage Query
 * @since      1.0
 */
class PostQueries {
	public static $instance = null;
	private $Queries;

	function __construct( $Queries ) {
		$this->Queries = $Queries;
		add_filter( 'posts_groupby', [ $this, 'changePostsGroupBy' ], 9999, 2 );
		add_filter( 'posts_orderby', [ $this, 'changePostsOrderBy' ], 9999, 2 );
	}

	/**
	 * Filters the ORDER BY clause of the query.
	 *
	 * @param string    $orderBy The ORDER BY clause of the query.
	 * @param \WP_Query $query   The WP_Query instance (passed by reference).
	 *
	 * @global wpdb     $wpdb    WordPress database abstraction object.
	 *
	 * @copyright Base on WP_Query:get_posts method.
	 *
	 * @since     1.5.1
	 *
	 */
	function changePostsOrderBy( $orderBy, $query ) {
		global $wpdb;

		if ( is_array( $query->get( 'orderby' ) ) || in_array( $query->get( 'orderby' ), [
				'meta_value',
				'meta_value_num'
			] ) ) {
			$queryVars = $this->Queries->getQueryVars( 'post', $query->query );
			$this->Queries->metaQuery->parse_query_vars( $queryVars );

			$query->set( 'orderby', $queryVars['orderby'] );

			$orderByQuery = $query->get( 'orderby' );
			$orderQuery   = $query->get( 'order' );

			// Order by.
			if ( ! empty( $orderByQuery ) && 'none' !== $orderByQuery ) {
				$orderby_array = array();

				if ( is_array( $orderByQuery ) ) {
					foreach ( $orderByQuery as $_orderby => $order ) {
						$orderby = addslashes_gpc( urldecode( $_orderby ) );
						$parsed  = $this->postParseOrderby( $orderby );

						if ( ! $parsed )
							continue;

						$orderby_array[] = $parsed . ' ' . $this->Queries->parseOrder( $order );
					}
					$orderBy_ = implode( ', ', $orderby_array );
				} else {
					$orderByQuery = urldecode( $orderByQuery );
					$orderByQuery = addslashes_gpc( $orderByQuery );

					foreach ( explode( ' ', $orderByQuery ) as $orderby ) {
						$parsed = $this->postParseOrderby( $orderby );
						// Only allow certain values for safety.
						if ( ! $parsed ) {
							continue;
						}

						$orderby_array[] = $parsed;
					}
					$orderBy_ = implode( ' ' . $orderQuery . ', ', $orderby_array );

					if ( empty( $orderBy_ ) ) {
						$orderBy_ = "{$wpdb->posts}.post_date " . $orderQuery;
					} elseif ( ! empty( $orderQuery ) ) {
						$orderBy_ .= " {$orderQuery}";
					}
				}

				return $orderBy_;
			}
		}

		return $orderBy;
	}

	/**
	 * Converts the given orderby alias (if allowed) to a properly-prefixed value.
	 *
	 * @param string $orderby Alias for the field to order by.
	 *
	 * @return string|false Table-prefixed value to used in the ORDER clause. False otherwise.
	 * @global \wpdb  $wpdb    WordPress database abstraction object.
	 *
	 * @copyright Base on WP_Query:parse_orderby method.
	 *
	 * @since     4.0.0
	 *
	 */
	protected function postParseOrderby( $orderby ) {
		global $wpdb;

		// Used to filter values.
		$allowed_keys = array(
			'post_name',
			'post_author',
			'post_date',
			'post_title',
			'post_modified',
			'post_parent',
			'post_type',
			'name',
			'author',
			'date',
			'title',
			'modified',
			'parent',
			'type',
			'ID',
			'menu_order',
			'comment_count',
			'rand',
			'post__in',
			'post_parent__in',
			'post_name__in',
		);

		$primary_meta_key   = '';
		$primary_meta_query = false;
		$meta_clauses       = $this->Queries->metaQuery->get_clauses();

		if ( ! empty( $meta_clauses ) ) {
			$primary_meta_query = $meta_clauses[ $orderby ] ?? reset( $meta_clauses );

			if ( ! empty( $primary_meta_query['key'] ) ) {
				$primary_meta_key = $primary_meta_query['key'];
				$allowed_keys[]   = $primary_meta_key;
			}

			$allowed_keys[] = 'meta_value';
			$allowed_keys[] = 'meta_value_num';
			$allowed_keys   = array_merge( $allowed_keys, array_keys( $meta_clauses ) );
		}

		// If RAND() contains a seed value, sanitize and add to allowed keys.
		$rand_with_seed = false;
		if ( preg_match( '/RAND\(([0-9]+)\)/i', $orderby, $matches ) ) {
			$orderby        = sprintf( 'RAND(%s)', (int) $matches[1] );
			$allowed_keys[] = $orderby;
			$rand_with_seed = true;
		}

		if ( ! in_array( $orderby, $allowed_keys, true ) ) {
			return false;
		}

		$orderby_clause = '';

		switch ( $orderby ) {
			case 'post_name':
			case 'post_author':
			case 'post_date':
			case 'post_title':
			case 'post_modified':
			case 'post_parent':
			case 'post_type':
			case 'ID':
			case 'menu_order':
			case 'comment_count':
				$orderby_clause = "{$wpdb->posts}.{$orderby}";
				break;
			case 'rand':
				$orderby_clause = 'RAND()';
				break;
			case $primary_meta_key:
			case 'meta_value':
				if ( ! empty( $primary_meta_query['type'] ) ) {
					$orderby_clause = "CAST({$primary_meta_query['alias']}.{$primary_meta_key} AS {$primary_meta_query['cast']})";
				} else {
					$orderby_clause = "{$primary_meta_query['alias']}.{$primary_meta_key}";
				}
				break;
			case 'meta_value_num':
				$orderby_clause = "{$primary_meta_query['alias']}.{$primary_meta_key}+0";
				break;
			case 'post__in':
				if ( ! empty( $this->queryVars['post__in'] ) ) {
					$orderby_clause = "FIELD({$wpdb->posts}.ID," . implode( ',', array_map( 'absint', $this->queryVars['post__in'] ) ) . ')';
				}
				break;
			case 'post_parent__in':
				if ( ! empty( $this->queryVars['post_parent__in'] ) ) {
					$orderby_clause = "FIELD( {$wpdb->posts}.post_parent," . implode( ', ', array_map( 'absint', $this->queryVars['post_parent__in'] ) ) . ' )';
				}
				break;
			case 'post_name__in':
				if ( ! empty( $this->queryVars['post_name__in'] ) ) {
					$post_name__in        = array_map( 'sanitize_title_for_query', $this->queryVars['post_name__in'] );
					$post_name__in_string = "'" . implode( "','", $post_name__in ) . "'";
					$orderby_clause       = "FIELD( {$wpdb->posts}.post_name," . $post_name__in_string . ' )';
				}
				break;
			default:
				if ( array_key_exists( $orderby, $meta_clauses ) ) {
					// $orderby corresponds to a meta_query clause.
					$meta_clause    = $meta_clauses[ $orderby ];
					$orderby_clause = "CAST({$meta_clause['alias']}.{$primary_meta_key} AS {$meta_clause['cast']})";
				} elseif ( $rand_with_seed ) {
					$orderby_clause = $orderby;
				} else {
					// Default: order by post field.
					$orderby_clause = "{$wpdb->posts}.post_" . sanitize_key( $orderby );
				}

				break;
		}

		return $orderby_clause;
	}

	/**
	 * Filters the GROUP BY clause of the query.
	 *
	 * @param string    $groupby The GROUP BY clause of the query.
	 * @param \WP_Query $query   The WP_Query instance (passed by reference).
	 *
	 * @since 2.0.0
	 *
	 */
	function changePostsGroupBy( $groupby, $query ) {
		return "";
	}

	/**
	 * Returns an instance of class
	 *
	 * @return PostQueries
	 */
	static function getInstance( $Queries ) {
		if ( self::$instance == null )
			self::$instance = new PostQueries( $Queries );

		return self::$instance;
	}
}
