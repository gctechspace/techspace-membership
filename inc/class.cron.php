<?php

class dtbaker_member_cron {


	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_action( 'techspace_member_cron_event', [ $this, 'server_cron' ] );
		if ( ! wp_next_scheduled( 'techspace_member_cron_event' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'techspace_member_cron_event' );
		}
	}

	public function admin_menu() {

		$page = add_submenu_page( 'edit.php?post_type=dtbaker_membership', __( 'Check Updates' ), __( 'Check Updates' ), 'edit_pages', 'member_cron', array(
			$this,
			'member_cron'
		) );

	}

	public function server_cron() {
		$this->import_data_from_square();
		$this->notify_about_freeloaders();
		$this->run_cron_job();
		$this->run_slack_job();
		$this->invite_paid_members_to_lounge();
	}

	public function member_cron() {
		?>
		<h3>Cron Job:</h3>
		<pre><?php $this->import_data_from_square( true ); ?></pre>
		<pre><?php $this->notify_about_freeloaders( true ); ?></pre>
		<pre><?php $this->run_cron_job( true ); ?></pre>
		<pre><?php $this->run_slack_job( true ); ?></pre>
		<pre><?php $this->invite_paid_members_to_lounge( true ); ?></pre>
		<?php
	}

	public function import_data_from_square( $debug = false ) {
		$member_manager = dtbaker_member::get_instance();

		// loop over all members.
		$members = get_posts( array(
			'post_type'      => 'dtbaker_membership',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		) );
		ini_set( 'display_errors', true );
		ini_set( 'error_reporting', E_ALL );

		foreach ( $members as $member ) {
			$membership_details = $member_manager->get_details( $member->ID );
			if ( ! empty( $membership_details['square_id'] ) ) {

				if ( $debug ) {
					echo "\n\nImporting square data for member (" . $member->ID . ") " . $member->post_title . "\n";
				}

				$square_manual_data = TechSpace_Square::get_instance()->get_contact_metadata( $membership_details['square_id'] );
				if ( $square_manual_data['slackid'] ) {
					if ( $debug ) {
						echo "\n - got slackid " . $square_manual_data['slackid'] . " \n";
						$existing_slackid = get_post_meta( $member->ID, 'membership_details_' . 'slackid', true );
					}
					update_post_meta( $member->ID, 'membership_details_' . 'slackid', $square_manual_data['slackid'] );
				}
				if ( ! empty( $square_manual_data['rfid_codes'] ) ) {
					if ( $debug ) {
						echo "\n - got rfid codes: " . implode( ", ", $square_manual_data['rfid_codes'] ) . " \n";
					}
					// todo write to member field
				}
			}
		}
		exit;
	}

	public function run_slack_job( $debug = false ) {

		$setting = trim( get_option( 'techspace_membership_slack_api' ) );
		if ( $setting ) {
			$ch   = curl_init( "https://slack.com/api/users.list" );
			$data = http_build_query( [
				"token" => $setting,
				//"as_user" => "true"
			] );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 4 );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			$result = curl_exec( $ch );
			$data   = @json_decode( $result, true );
			curl_close( $ch );

			$all_members    = array();
			$members        = get_posts( array(
				'post_type'      => 'dtbaker_membership',
				'post_status'    => 'publish',
				'posts_per_page' => - 1,
			) );
			$member_manager = dtbaker_member::get_instance();
			foreach ( $members as $member ) {
				$all_members[ $member->ID ] = $member_manager->get_details( $member->ID );
			}


			if ( $data && ! empty( $data['members'] ) ) {
				foreach ( $data['members'] as $member ) {
					if ( $member['profile']['email'] ) {
						// find a matching member
						echo $member['profile']['email'] . ' has username ' . $member['name'];
						$email        = strtolower( $member['profile']['email'] );
						$member_match = false;
						foreach ( $all_members as $member_id => $member_data ) {
							if ( ! empty( $member_data['email'] ) && strtolower( $member_data['email'] ) == $email ) {
								$member_match = $member_id;
								break;
							}
						}
						if ( ! $member_match ) {
							// see if we've got a match on slack username.
							foreach ( $all_members as $member_id => $member_data ) {
								$member_slack_username = get_post_meta( $member_id, 'membership_details_' . 'slack', true );
								if ( $member_slack_username && strtolower( $member_slack_username ) == strtolower( $member['name'] ) ) {
									$member_match = $member_id;
									break;
								}
							}
						}
						if ( $member_match ) {
							echo ' <br>  - match found: ' . $member_match . ' (' . get_the_title( $member_match ) . ')';
							update_post_meta( $member_match, 'membership_details_' . 'slack', $member['name'] );
							update_post_meta( $member_match, 'membership_details_' . 'slackid', $member['id'] );

						}

					}

					echo '<br>';
				}
			}
		}


	}

	public function invite_paid_members_to_lounge( $debug = false ) {
		$member_manager = dtbaker_member::get_instance();

		// loop over all members.
		// grab an updated list of invoices for those members.
		// update membership expiry date for any paid invoices.
		// check its association to a member.
		$members = get_posts( array(
			'post_type'      => 'dtbaker_membership',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		) );
		ini_set( 'display_errors', true );
		ini_set( 'error_reporting', E_ALL );

		$slack_invite_history = get_transient( 'ts_slack_invites' );
		if ( ! is_array( $slack_invite_history ) ) {
			$slack_invite_history = array();
		}

		$setting = trim( get_option( 'techspace_membership_slack_api' ) );

		$ch   = curl_init( "https://slack.com/api/conversations.members" );
		$data = http_build_query( [
			"token"   => trim( get_option( 'techspace_membership_slack_real_token' ) ),
			"channel" => "G7WBTF9QW",
		] );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 4 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$result          = curl_exec( $ch );
		$channel_members = @json_decode( $result, true );
		curl_close( $ch );

		//todo: https://api.slack.com/methods/channels.kick
		foreach ( $members as $member ) {

			//		    if($member->ID != 485965)continue; // steve test.
			$membership_details = $member_manager->get_details( $member->ID );

			$membership_details['member_end'] = (int) $membership_details['member_end'];

			$slack_username    = get_post_meta( $member->ID, 'membership_details_' . 'slack', true );
			$slack_username_id = get_post_meta( $member->ID, 'membership_details_' . 'slackid', true );

			if ( $slack_username_id && ! in_array( $slack_username_id, $channel_members['members'] ) ) {
				unset( $slack_invite_history[ $member->ID ] );
			}

			if ( $membership_details['member_end'] > time() && ! isset( $slack_invite_history[ $member->ID ] ) ) {
				// invite this user to slack!

				$slack_invite_history[ $member->ID ] = true;


				if ( ! $slack_username || ! $slack_username_id ) {

					$this->send_notification( 'Tried to invite member ' . $member->post_title . ' to the Members Lounge, but I dont know their slack username/id :( ', 'cry', $member->ID );

				} else {

					if ( ! in_array( $slack_username_id, $channel_members['members'] ) ) {

						$setting = trim( get_option( 'techspace_membership_slack_api' ) );
						if ( $setting ) {

							$successfully_added = false;
							$ch                 = curl_init( "https://slack.com/api/conversations.invite" );
							$data               = http_build_query( [
								"token"   => trim( get_option( 'techspace_membership_slack_real_token' ) ),
								"channel" => "G7WBTF9QW",
								"users"   => $slack_username_id,
							] );
							curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
							curl_setopt( $ch, CURLOPT_TIMEOUT, 4 );
							curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
							curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
							curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
							curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
							$result = curl_exec( $ch );
							$data   = @json_decode( $result, true );
							print_r( $data );
							curl_close( $ch );

							if ( $data && ! empty( $data['ok'] ) ) {
								$successfully_added = true;
							} else {
								$this->send_notification( 'Failed to add paid member ' . $member->post_title . ' to the Members Lounge :( ' . "\n" . $result, 'cry', $member->ID );
							}


							if ( $successfully_added ) {
								$this->send_notification( 'Added paid member ' . $member->post_title . ' to the Members Lounge!', 'tada', $member->ID );
								$ch              = curl_init( "https://slack.com/api/chat.postMessage" );
								$welcome_message = "Everyone welcome " . $member->post_title . " to the lounge! ";
								$interests       = get_post_meta( $member->ID, 'membership_details_interests', true );
								if ( $interests ) {
									$welcome_message .= "\n@$slack_username's interests are: $interests ";
								}
								$data = http_build_query( [
									"token"      => $setting,
									"channel"    => 'members-lounge',
									"text"       => $welcome_message,
									"username"   => 'Welcome',
									"icon_emoji" => ':tada:',
									//"as_user" => "true"
								] );
								curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
								curl_setopt( $ch, CURLOPT_TIMEOUT, 4 );
								curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
								curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
								curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
								curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
								$result = curl_exec( $ch );
								curl_close( $ch );
							}

						}
					}
				}
			}
		}
		set_transient( 'ts_slack_invites', $slack_invite_history, 604800 ); // 1 week.
	}

	public function notify_about_freeloaders( $debug = false ) {
		$member_manager = dtbaker_member::get_instance();

		// loop over all members.
		// grab an updated list of invoices for those members.
		// update membership expiry date for any paid invoices.
		// check its association to a member.
		$members = get_posts( array(
			'post_type'      => 'dtbaker_membership',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		) );
		ini_set( 'display_errors', true );
		ini_set( 'error_reporting', E_ALL );

		$last_notifications = get_transient( 'ts_freeloader_notifications' );
		if ( ! is_array( $last_notifications ) ) {
			$last_notifications = array();
		}
		foreach ( $members as $member ) {
			$membership_details = $member_manager->get_details( $member->ID );

			$membership_details['member_end'] = (int) $membership_details['member_end'];
			if ( $membership_details['member_end'] < time() && ! isset( $last_notifications[ $member->ID ] ) ) {
				// expired membership. see if they have checked in recently.
				$rfid_keys       = get_posts( array(
					'post_type'   => 'dtbaker_rfid',
					'post_status' => 'publish',
					'meta_key'    => 'rfid_details_member_id',
					'meta_value'  => $member->ID,
				) );
				$last_rfid_usage = 0;
				if ( $rfid_keys ) {
					foreach ( $rfid_keys as $rfid_key ) {
						//echo str_pad(substr($rfid_code,0,5), strlen($rfid_code), '*', STR_PAD_RIGHT);
						$last_access = (int) get_post_meta( $rfid_key->ID, 'rfid_details_last_access', true );
						if ( $last_access && $last_access > $membership_details['member_end'] && $last_access > strtotime( '-2 weeks' ) ) {

							global $wpdb;
							$history      = $wpdb->get_results(
								"SELECT * 
				FROM `" . $wpdb->prefix . "ts_rfid` WHERE rfid_id = " . (int) $rfid_key->ID . ' AND access_time > ' . (int) $membership_details['member_end']
								, ARRAY_A
							);
							$history_days = array();
							foreach ( $history as $h ) {
								$history_days[ date( 'Y-m-d', $h['access_time'] ) ] = true;
							}

							$notification = "Freeloader alert:\nMember " . $member->post_title . " expired on " . ( ! $membership_details['member_end'] ? '(No Expiry Date Found)' : date( 'Y-m-d', $membership_details['member_end'] ) ) . " however they have checked in " . count( $history_days ) . " times after this (latest was " . date( 'Y-m-d', $last_access ) . ")";
							$this->send_notification( $notification, 'free', $member->ID );
							$last_notifications[ $member->ID ] = true;
						}
						//						printf('<a href="%s">%s</a><br> ', esc_url(get_edit_post_link($rfid_key->ID)), esc_html($rfid_key->post_title));
					}
				}
			}
		}


		set_transient( 'ts_freeloader_notifications', $last_notifications, 604800 ); // 1 week.
	}

	public function run_cron_job( $debug = false ) {
		$member_manager = dtbaker_member::get_instance();
		$member_types   = TechSpace_Cpt::get_instance()->get_member_types();

		// loop over all members.
		// grab an updated list of invoices for those members.
		// update membership expiry date for any paid invoices.
		// check its association to a member.
		$members = get_posts( array(
			'post_type'      => 'dtbaker_membership',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		) );
		ini_set( 'display_errors', true );
		ini_set( 'error_reporting', E_ALL );

		foreach ( $members as $member ) {
			// check for any new invoices for this member.
			$membership_details = $member_manager->get_details( $member->ID );

			if ( $debug ) {
				echo "\nChecking status for member (" . $member->ID . ") " . $member->post_title . "\n";
				if ( $membership_details['member_start'] ) {
					echo " Start: " . date( 'Y-m-d', $membership_details['member_start'] );
				}
				if ( $membership_details['member_end'] ) {
					echo " End: " . date( 'Y-m-d', $membership_details['member_end'] ) . "\n";
				}
			}

			$invoices = [];
			if ( ! empty( $membership_details['square_id'] ) ) {
				$invoices = $invoices + TechSpace_Square::get_instance()->get_contact_invoices( $membership_details['square_id'], true );
			}

			if ( $invoices ) {
				if ( $debug ) {
					echo " Found " . count( $invoices ) . " invoices, saving to cache. \n";
				}
				/*Array
				(
						[number] => INV-0069
						[time] => 1467072000
						[paid_time] => 1467072000
						[status] => PAID
						[total] => 123.75
						[due] => 0.00
						[emailed] => 1
				)*/
				$member_manager->cache_member_invoices( $member->ID, $invoices );
				foreach ( $invoices as $invoice_id => $invoice ) {
					if ( ! empty( $invoice['paid_time'] ) && $invoice['status'] == 'PAID' ) {
						echo " - Found a $" . $invoice['total'] . " paid invoice (paid on " . date( 'Y-m-d', $invoice['paid_time'] ) . "): $invoice_id \n";
						// check how many days this invoice payment will get the user.
						foreach ( $member_types as $member_type ) {
							if ( $member_type['price'] && floor( $member_type['price'] / 100 ) === floor( $invoice['total'] ) ) {
								if ( $debug ) {
									echo " -- this invoice is for membership type: " . $member_type['name'] . " for " . $member_type['months'] . " months. \n";
								}
								//									$new_member_date = strtotime('+'.$member_type['months'].' months',$invoice['paid_time']);
								$new_member_date = strtotime( '+' . $member_type['months'] . ' months', $invoice['time'] );
								if ( $debug ) {
									echo " -- this invoice will extend the member date until " . date( 'Y-m-d', $new_member_date ) . " \n";
								}
								if ( empty( $membership_details['member_start'] ) ) {
									//										if($debug)echo " -- will set the membership start date to " . date('Y-m-d',$invoice['paid_time']) ." \n";
									if ( $debug ) {
										echo " -- will set the membership start date to " . date( 'Y-m-d', $invoice['time'] ) . " \n";
									}
									//										$membership_details['member_start'] = $invoice['paid_time'];
									$membership_details['member_start'] = $invoice['time'];
									update_post_meta( $member->ID, 'membership_details_member_start', $membership_details['member_start'] );
								}
								if ( empty( $membership_details['member_end'] ) || $membership_details['member_end'] < $new_member_date ) {
									if ( $debug ) {
										echo " -- will set the membership end date to " . date( 'Y-m-d', $new_member_date ) . " \n";
									}
									$membership_details['member_end'] = $new_member_date;
									update_post_meta( $member->ID, 'membership_details_member_end', $membership_details['member_end'] );
									$this->send_notification( "Member " . $member->post_title . ' extended to ' . date( 'Y-m-d', $new_member_date ) . ' after receiving a ' . $member_type['months'] . ' month payment for $' . $invoice['total'] . ' (invoice: ' . $invoice['number'] . ')', 'moneybag', $member->ID );
								}

							}
						}
					}
				}
			} else {
				if ( $debug ) {
					echo "No invoices found \n";
				}
			}

			if ( ! empty( $membership_details['member_end'] ) && $membership_details['member_end'] < strtotime( '+10 days' ) ) {
				// check if expiry is coming up
				if ( $debug ) {
					echo " - membership is about to expire on " . date( 'Y-m-d', $membership_details['member_end'] ) . "\n";
				}
				// check if we have already sent an invoice for this member date.
				$found_invoice = false;
				if ( $invoices ) {
					foreach ( $invoices as $invoice ) {
						if ( date( 'Y-m-d', $invoice['time'] ) == date( 'Y-m-d', $membership_details['member_end'] )
						     ||
						     $invoice['time'] >= $membership_details['member_end']
						) {
							if ( $debug ) {
								echo " --- found an invoice for this renewal already! not sending a new one \n";
							}
							$found_invoice = true;
						}
					}
				}
				if ( ! $found_invoice ) {
					if ( $debug ) {
						echo " --- Generating a new invoice for this renewal! \n";
					}

					if ( ! $membership_details['square_id'] ) {
						if ( $debug ) {
							echo " --- no square contact assigned, unable to generate invoice! \n";
						}
					} else {
						// what membership type do they have.
						$current_member_types = wp_get_post_terms( $member->ID, 'dtbaker_membership_type' );
						foreach ( $current_member_types as $current_member_type ) {
							foreach ( $member_types as $member_type ) {
								if ( $current_member_type->term_id === $member_type['term_id'] ) {
									// Generate an invoice for this member type (maybe)
									if ( $member_type['square_invoices'] ) {
										if ( $debug ) {
											echo " --- Membership type: " . $member_type['name'] . " \n";
										}

										if ( ! empty( $membership_details['automatic_type'] ) && $membership_details['automatic_type'] == 'ignore' ) {
											if ( $debug ) {
												echo "\nIgnoring generating an invoice for member (" . $member->ID . ") " . $member->post_title . "\n";
											}
										} else if ( ! empty( $membership_details['automatic_type'] ) && $membership_details['automatic_type'] == 'manual_invoice' ) {
											if ( $debug ) {
												echo "\nmember settings to manual invoice creation\n";
											}

											$this->send_notification( "TODO: Please manually create a new invoice for " . $member->post_title . ' (' . $member_type['months'] . " months). \n Not automatically creating due to member settings.", 'moneybag', $member->ID );
										} else {

											if ( $debug ) {
												echo "\nwanting to create a " . ( $member_type['price'] / 100 ) . " invoice for this member \n";
											}
											//										$new_invoice_id = TechSpace_Square::get_instance()->create_invoice($member->ID, $membership_details['square_id'], [
											//											'name' => $member_type['name'],
											//											'money' => $member_type['price'],
											//											'due_date'    => date( 'Y-m-d' ),
											//										]);
											//										if ( $new_invoice_id ) {
											//											$this->send_notification( "New Invoice for " . $member->post_title . ' generated in Square (' . $member_type['months'] . " months). \n https://squareup.com/dashboard/invoices/$new_invoice_id ", 'moneybag', $member->ID );
											//										} else {
											//											$this->send_notification( "Error: Failed to make invoice in Square for " . $member->post_title . ' - not sure why - check with dave?', 'moneybag', $member->ID );
											//										}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	public function send_notification( $message, $emoji = 'moneybag', $member_id = false ) {

		$channel = 'sc-membership';
		$botname = 'MemberBot';

		if ( $member_id ) {
			$message .= "\n" . "Member Link: `https://gctechspace.org/wp-admin/post.php?post=$member_id&action=edit`";
		}

		$setting = trim( get_option( 'techspace_membership_slack_api' ) );
		if ( $setting ) {
			$ch   = curl_init( "https://slack.com/api/chat.postMessage" );
			$data = http_build_query( [
				"token"      => $setting,
				"channel"    => $channel,
				"text"       => $message,
				"username"   => $botname,
				"icon_emoji" => ':' . $emoji . ':',
				//"as_user" => "true"
			] );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 4 );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			$result = curl_exec( $ch );
			curl_close( $ch );
		}
	}

}

dtbaker_member_cron::get_instance()->init();
