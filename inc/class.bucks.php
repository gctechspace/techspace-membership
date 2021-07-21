<?php


class TechSpace_Bucks {

	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	public function admin_menu() {
		$page = add_submenu_page( 'edit.php?post_type=dtbaker_membership', __( 'Buck History Logs' ), __( 'Buck History Logs' ), 'edit_pages', 'buck_history', array(
			$this,
			'show_buck_history'
		) );
	}

	public function show_buck_history() {
		?>
		<div class="wrap">
			<h1>Member Buck History Log</h1>
			<?php
			$myListTable = new TechSpaceCustomTable( array(
				'screen' => 'buck_history'
			) );
			global $wpdb;
			$history = $wpdb->get_results(
				"SELECT * FROM `" . $wpdb->prefix . "ts_buck` ORDER BY ts_buck DESC LIMIT 100",
				ARRAY_A
			);
			$myListTable->set_columns(
				array(
					'timestamp'       => __( 'Time' ),
					'member_id'  => __( 'Member' ),
					'amount'    => __( 'Amount' ),
					'comment'    => __( 'Comment' ),
					'state' => __( 'State' ),
				)
			);
			$myListTable->set_data( $history );
			$myListTable->set_callback( function ( $item, $column_name ) {
				switch ( $column_name ) {
					case 'member_id':
						if ( $item['member_id'] ) {
							$member = get_post( $item['member_id'] );
							if ( $member ) {
								return sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $member->ID ) ), esc_html( $member->post_title ) );
							}
						}

						return 'N/A';
					case 'timestamp':
						return date( 'Y-m-d H:i:s', $item['timestamp'] );
					case 'amount':
						return $item['amount'];
					case 'state':
						return $item['state'];
				}
			} );
			$myListTable->prepare_items();
			$myListTable->display();
			?>
		</div>
		<?php
	}

	public function get_single_member_available_bucks( $member_id, $recalculate = false ) {
		// check cached value first, or recalculate.
		$member_bucks = get_post_meta( $member_id, 'cached_member_bucks_value', true );
		if ( $member_bucks === false || $recalculate ) {
			// recalculate cached value from memberbucks history table.
			global $wpdb;
			$users_buck_history = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `" . $wpdb->prefix . "ts_buck` WHERE member_id = %d AND `verified` = 1 ORDER BY ts_buck ASC", $member_id ), ARRAY_A );
			$member_bucks       = 0;
			foreach ( $users_buck_history as $history ) {
				$member_bucks += $history['amount'];
			}
			update_post_meta( $member_id, 'cached_member_bucks_value', $member_bucks );
		}

		return $member_bucks;
	}

	public function manually_add_member_bucks( $member_id, $amount, $comment = '' ) {
		if($amount > 0 || $amount < 0 ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "INSERT INTO `" . $wpdb->prefix . "ts_buck` SET member_id = %d, verified = 1, state = 'pending', amount = %f, timestamp = %d, comment = %s", $member_id, $amount, time(), $comment ) );
			$this->get_single_member_available_bucks( $member_id, true );
		}
	}
}

TechSpace_Bucks::get_instance()->init();
