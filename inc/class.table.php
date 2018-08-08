<?php

// basic class file for displaying RFID checkin history from its own database table.

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class TechSpaceMembersTable extends WP_List_Table {

	public $action_key = 'ID';
	public $table_data = array();
	public $columns = array();
	public $_sortable_columns = array();
	public $found_data = array();

	public $items_per_page = 0;

	public $pagination_has_more = false;

	function __construct( $args = array() ) {
		global $status, $page;

		$args = wp_parse_args( $args, array(
			'plural'   => __( 'accounts' ),
			'singular' => __( 'account' ),
			'ajax'     => false,
		) );

		parent::__construct( $args );

		$this->set_columns( array(
			'account'      => __( 'Account' ),
			'last_checked' => __( 'Last Checked' ),
		) );

	}

	function no_items() {
		_e( 'No accounts found.' );
	}

	function column_default( $item, $column_name ) {
		if ( $this->row_callback !== false ) {
			$res = call_user_func( $this->row_callback, $item, $column_name );
			if ( $res ) {
				return $res;
			}
		}

		return isset( $item[ $column_name ] ) ? $item[ $column_name ] : 'N/A';
	}


	function set_data( $data ) {
		$this->items = $data;
	}

	private $row_callback = false;

	function set_callback( $function ) {
		$this->row_callback = $function;
	}

	function set_sortable_columns( $columns ) {
		$this->_sortable_columns = $columns;
	}

	function set_columns( $columns ) {
		$this->columns = $columns;
	}

	function get_columns() {
		return $this->columns;
	}

	function column_account( $item ) {
		if ( isset( $item['edit_link'] ) ) {
			$actions = array(
				'edit' => '<a href="' . $item['edit_link'] . '">' . __( 'Edit', 'support_hub' ) . '</a>',
				//'delete' => sprintf( '<a href="?page=%s&action=%s&book=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['ID'] ),
			);

			return sprintf( '%1$s %2$s', $item['title'], $this->row_actions( $actions ) );
		}/*else {
			$actions = array(
				'edit' => sprintf( '<a href="?page=%s&' . $this->action_key . '=%s">'.__('Edit','support_hub').'</a>', htmlspecialchars( $_REQUEST['page'] ), $item[ $this->action_key ] ),
				//'delete' => sprintf( '<a href="?page=%s&action=%s&book=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['ID'] ),
			);
		}*/


	}

	function set_bulk_actions( $actions ) {
		$this->bulk_actions = $actions;
	}

	function get_bulk_actions() {
		return isset( $this->bulk_actions ) ? $this->bulk_actions : array();
	}


	function prepare_items() {

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->_sortable_columns; //
		$this->_column_headers = array( $columns, $hidden, $sortable );
		//usort( $this->example_data, array( $this, 'usort_reorder' ) );

		$current_page = $this->get_pagenum();

		$total_items = count( $this->items );

		// only ncessary because we have sample data
		if ( $this->items_per_page ) {
			$this->found_data = array_slice( $this->items, ( ( $current_page - 1 ) * $this->items_per_page ), $this->items_per_page );
			if ( ! $this->found_data ) {
				$this->found_data = $this->items;
			} // hack to stop the page overflow bug
			$this->set_pagination_args( array(
				'total_items' => $total_items, //WE have to calculate the total number of items
				'per_page'    => $this->items_per_page //WE have to determine how many items to show on a page
			) );
		} else {
			$this->found_data = $this->items;
		}

		$this->items = $this->found_data;
		unset( $this->found_data );

	}


} //class


class TechSpaceRFIDHistoryTable extends WP_List_Table {

	public $action_key = 'ID';
	public $table_data = array();
	public $columns = array();
	public $_sortable_columns = array();
	public $found_data = array();

	public $items_per_page = 0;

	public $pagination_has_more = false;

	function __construct( $args = array() ) {
		global $status, $page;

		$args = wp_parse_args( $args, array(
			'plural'   => __( 'RFID History' ),
			'singular' => __( 'RFID History' ),
			'ajax'     => false,
		) );

		parent::__construct( $args );

		$this->set_columns( array(
			'ts_rfid'    => __( 'History ID' ),
			'member_id'  => __( 'Member' ),
			'rfid_id'    => __( 'RFID' ),
			'time'       => __( 'Time' ),
			'access'     => __( 'Access Point' ),
			'ip_address' => __( 'IP Address' ),
			'api_result' => __( 'API Result' ),
		) );

	}

	function no_items() {
		_e( 'No history found.' );
	}

	function column_default( $item, $column_name ) {
		if ( $this->row_callback !== false ) {
			$res = call_user_func( $this->row_callback, $item, $column_name );
			if ( $res ) {
				return $res;
			}
		}

		return isset( $item[ $column_name ] ) ? $item[ $column_name ] : 'N/A';
	}

	function set_data( $data ) {
		$this->items = $data;
	}

	private $row_callback = false;

	function set_callback( $function ) {
		$this->row_callback = $function;
	}

	function set_sortable_columns( $columns ) {
		$this->_sortable_columns = $columns;
	}

	function set_columns( $columns ) {
		$this->columns = $columns;
	}

	function get_columns() {
		return $this->columns;
	}


	function set_bulk_actions( $actions ) {
		$this->bulk_actions = $actions;
	}

	function get_bulk_actions() {
		return isset( $this->bulk_actions ) ? $this->bulk_actions : array();
	}

	function prepare_items() {

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->_sortable_columns; //
		$this->_column_headers = array( $columns, $hidden, $sortable );
		//usort( $this->example_data, array( $this, 'usort_reorder' ) );

		$current_page = $this->get_pagenum();

		$total_items = count( $this->items );

		// only ncessary because we have sample data
		if ( $this->items_per_page ) {
			$this->found_data = array_slice( $this->items, ( ( $current_page - 1 ) * $this->items_per_page ), $this->items_per_page );
			if ( ! $this->found_data ) {
				$this->found_data = $this->items;
			} // hack to stop the page overflow bug
			$this->set_pagination_args( array(
				'total_items' => $total_items, //WE have to calculate the total number of items
				'per_page'    => $this->items_per_page //WE have to determine how many items to show on a page
			) );
		} else {
			$this->found_data = $this->items;
		}

		$this->items = $this->found_data;
		unset( $this->found_data );

	}


} //class