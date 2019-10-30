<?php

class dtbaker_member_stats {

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

		$page = add_submenu_page( 'edit.php?post_type=dtbaker_membership', __( 'Stats' ), __( 'Stats' ), 'edit_pages', 'member_stats', array(
			$this,
			'member_stats'
		) );

	}

	public function member_stats() {
		?>
		<h3>Member Stats:</h3>

		<?php

		$members = get_posts( array(
			'post_type'      => 'dtbaker_membership',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		) );

		$earnings = array();


		$start = strtotime( "2016-07-01" );
		$end   = strtotime( "2019-10-30" );

		$member_count        = count( $members );
		$paying_member_count = array();

		$guest_start_dates  = array();
		$member_start_dates = array();
		$member_end_dates   = array();


		foreach ( $members as $member ) {


			$member_manager = dtbaker_member::get_instance();
			$member_details = $member_manager->get_details( $member->ID );
			if ( $member_details['member_end'] ) {
				$member_end_month = strtotime( date( 'Y-m', strtotime( '+1 month', $member_details['member_end'] ) ) );
				if ( ! isset( $member_end_dates[ $member_end_month ] ) ) {
					$member_end_dates[ $member_end_month ] = array();
				}
				$member_end_dates[ $member_end_month ][] = $member->ID;
			}
			if ( $member_details['member_start'] ) {
				$member_start_month = strtotime( date( 'Y-m', $member_details['member_start'] ) );
				if ( ! isset( $member_start_dates[ $member_start_month ] ) ) {
					$member_start_dates[ $member_start_month ] = array();
				}
				$member_start_dates[ $member_start_month ][] = $member->ID;
			} else {
				$guest_date = strtotime( date( 'Y-m', strtotime( $member->post_date ) ) );
				if ( ! isset( $guest_start_dates[ $guest_date ] ) ) {
					$guest_start_dates[ $guest_date ] = array();
				}
				$guest_start_dates[ $guest_date ][] = $member->ID;
			}

			if ( ! empty( $member_details['invoice_cache'] ) ) {
				$recurring_one = false;
				foreach ( $member_details['invoice_cache'] as $invoice_id => $invoice ) {
					$recurring_one = $invoice_id;
				}
				foreach ( $member_details['invoice_cache'] as $invoice_id => $invoice ) {
					if ( $invoice['status'] == 'PAID' ) {
						if ( $member_details['member_end'] && $member_details['member_end'] > time() ) {
							$paying_member_count[ $member->ID ] = true;
						}
						$expected = false;
						switch ( (int) $invoice['total'] ) {
							case 225;
							case 250;
								$expected = '+12 months';
								break;
							case 25;
								$expected = '+1 month';
								break;
							case 125;
								$expected = '+6 months';
								break;
						}
						$earnings[] = array(
							'type'   => '',
							'amount' => $invoice['total'],
							'time'   => $invoice['paid_time'],
							'month'  => date( 'Y-m', $invoice['paid_time'] ),
							'paid'   => true,
							'period' => $expected,
						);
						if ( $expected && $recurring_one == $invoice_id && $invoice['paid_time'] ) {
							// generate some recurring invoices.
							$this_recurring_start = $invoice['paid_time'];
							while ( $this_recurring_start < $end ) {

								$this_recurring_start = strtotime( $expected, $this_recurring_start );
								$earnings[]           = array(
									'type'   => 'recurring',
									'amount' => $invoice['total'],
									'time'   => $this_recurring_start,
									'month'  => date( 'Y-m', $this_recurring_start ),
									'paid'   => false,
									'period' => $expected,
								);
							}
						}
					}
				}
			}

		}

		$paying_member_count = count( $paying_member_count );

		?>
		* (expected 1 new full paying member per month)
		<style>
			#member-stats th,
			#member-stats td {
				padding: 3px;
				text-align: center;
				gc border-top: 1px solid #CCC;
			}
		</style>
		<table id="member-stats">
			<thead>
			<tr>
				<th>Month</th>
				<th>Total Members + Guests</th>
				<th>Total Paying Members</th>
				<th>Membership Funds Received</th>
				<th>Projected Membership Funds</th>
				<th>Expected Monthly Total</th>
			</tr>
			</thead>
			<tbody>
			<?php
			$time = $start;
			$x    = 1;
			while ( $time < $end ) {

				$this_month = array(
					'earnt'     => 0,
					'projected' => 0,
				);
				$month      = date( 'Y-m', $time );
				foreach ( $earnings as $earning ) {
					if ( $earning['month'] == $month ) {
						if ( $earning['paid'] ) {
							$this_month['earnt'] += $earning['amount'];
						} else {
							$this_month['projected'] += $earning['amount'];
						}
					}
				}


				if ( $time > time() ) {
					$this_month['projected'] += 25;
					$member_count ++;
					$paying_member_count ++;

					$this_month_member_count = $paying_member_count;
					$this_month_guest_count  = $member_count;
				} else {

					$this_month_member_count = 0;
					foreach ( $member_start_dates as $month_time => $started_member_ids ) {
						if ( strtotime( $month ) >= $month_time ) {
							$this_month_member_count += count( $started_member_ids );
						}
					}
					foreach ( $member_end_dates as $month_time => $ended_member_ids ) {
						if ( strtotime( $month ) >= $month_time ) {
							$this_month_member_count -= count( $ended_member_ids );
						}
					}
					$this_month_guest_count = $this_month_member_count;
					foreach ( $guest_start_dates as $month_time => $started_guest_ids ) {
						if ( strtotime( $month ) >= $month_time ) {
							$this_month_guest_count += count( $started_guest_ids );
						}
					}
				}
				?>
				<tr>
					<th>
						<?php echo $month; ?>
					</th>
					<td><?php echo $this_month_guest_count; ?></td>
					<td><?php echo $this_month_member_count; ?></td>
					<td><?php echo '$' . $this_month['earnt']; ?></td>
					<td><?php echo '$' . $this_month['projected'];
						if ( $time > time() ) {
							//I  echo '*';
						}
						?></td>
					<td><?php echo '$' . ( $this_month['earnt'] + $this_month['projected'] ); ?></td>
				</tr>
				<?php

				$time = strtotime( "+$x months", $start );
				$x ++;
			}
			?>
			</tbody>
		</table>
		<?php


		// group logs by month
		?>
		<h3>Chicken/Door Logs</h3>
		<?php

		$start_date = strtotime( '-6 months', strtotime( date( 'Y-m' ) . '-1' ) );

		global $wpdb;
		$history = $wpdb->get_results(
			"SELECT * 
				FROM `" . $wpdb->prefix . "ts_rfid` WHERE access_time >= " . (int) $start_date . " ORDER BY ts_rfid ASC"
			, ARRAY_A
		);

		$monthly   = array();
		$ci_points = array();
		foreach ( $history as $item ) {
			$table_key = date( 'Y-m', $item['access_time'] );
			if ( empty( $monthly[ $table_key ] ) ) {
				$monthly[ $table_key ] = array();
			}
			$day = date( 'j', $item['access_time'] );
			if ( empty( $monthly[ $table_key ][ $day ] ) ) {
				$monthly[ $table_key ][ $day ] = array(
					'access_points' => array(),
					'member_ids'    => array(),
				);
			}

			$access_point = 'unknown';
			if ( $item['access_id'] > 0 ) {
				$available_access = get_terms( 'dtbaker_membership_access', array(
					'hide_empty' => false,
				) );
				foreach ( $available_access as $available_acces ) {
					if ( $available_acces->term_id == $item['access_id'] ) {
						$access_point = $available_acces->slug;
					}
				}
			}

			if ( empty( $monthly[ $table_key ][ $day ]['access_points'][ $access_point ] ) ) {
				$monthly[ $table_key ][ $day ]['access_points'][ $access_point ] = 0;
			}
			if ( empty( $monthly[ $table_key ][ $day ]['member_ids'][ (int) $item['rfid_id'] ] ) ) {
				$monthly[ $table_key ][ $day ]['member_ids'][ (int) $item['rfid_id'] ] = 1;
			}
			//			$monthly[$table_key][$day]['member_ids'][(int)$item['rfid_id']] ++;
			switch ( $access_point ) {
				case 'ci':
					//                    if(!$item['api_result']) {
					$monthly[ $table_key ][ $day ]['access_points'][ $access_point ] ++;
					//                    }
					break;
				default:
					$monthly[ $table_key ][ $day ]['access_points'][ $access_point ] ++;
			}
		}

		?>
		<style>
			#monthly-stats th,
			#monthly-stats td {
				padding: 3px;
				text-align: center;
				gc border-top: 1px solid #CCC;
			}
		</style>
		Total Swipes:
		<table id="monthly-stats">
			<thead>
			<tr>
				<th>Month</th>
				<?php for ( $x = 1; $x <= 31; $x ++ ) { ?>
					<th><?php echo $x; ?></th>
				<?php } ?>
				<th>Total</th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $monthly as $month => $data ) {
				$monthly_total = 0;
				?>
				<tr>
					<td><?php echo $month; ?></td>
					<?php for ( $x = 1; $x <= 31; $x ++ ) {
						?>
						<td><?php
							if ( ! empty( $data[ $x ] ) ) {
								$monthly_total += array_sum( $data[ $x ]['access_points'] );
								echo array_sum( $data[ $x ]['access_points'] );
							}
							?></td>
					<?php } ?>
					<td><?php echo $monthly_total; ?></td>
				</tr>
			<?php }
			?>
			</tbody>
		</table>
		Grouped by person per day:
		<table id="monthly-stats">
			<thead>
			<tr>
				<th>Month</th>
				<?php for ( $x = 1; $x <= 31; $x ++ ) { ?>
					<th><?php echo $x; ?></th>
				<?php } ?>
				<th>Total</th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $monthly as $month => $data ) {
				$monthly_total = 0;
				?>
				<tr>
					<td><?php echo $month; ?></td>
					<?php for ( $x = 1; $x <= 31; $x ++ ) {
						?>
						<td><?php
							if ( ! empty( $data[ $x ] ) ) {
								$monthly_total += array_sum( $data[ $x ]['member_ids'] );
								echo array_sum( $data[ $x ]['member_ids'] );
							}
							?></td>
					<?php } ?>
					<td><?php echo $monthly_total; ?></td>
				</tr>
			<?php }
			?>
			</tbody>
		</table>
		<?php


	}

}

dtbaker_member_stats::get_instance()->init();