<?php

namespace WPMetaOptimizer;

class Tools {
	public static $instance = null;
	protected $Options;

	function __construct() {
		$this->Options = Options::getInstance();
		add_filter( 'wp_dashboard_setup', [ $this, 'disableQuickDraftDashboardWidget' ] );
		add_filter( 'wp_revisions_to_keep', [ $this, 'disableRevisions' ] );
	}

	/**
	 * Disable post revisions
	 *
	 * @param int $num Number of revisions to store.
	 *
	 * @return  int Number of revisions to store.
	 */
	function disableRevisions( int $num ) {
		if ( $this->Options->getOption( 'disable_post_revisions', false ) == 1 )
			return false;

		return $num;
	}

	/**
	 * @return void
	 */
	function disableQuickDraftDashboardWidget() {
		global $wp_meta_boxes;
		if ( $this->Options->getOption( 'disable_quick_draft_widget', false ) == 1 )
			unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press'] );
	}

	/**
	 * Returns an instance of class
	 *
	 * @return Tools
	 */
	static function getInstance(): ?Tools {
		if ( self::$instance == null )
			self::$instance = new Tools();

		return self::$instance;
	}
}