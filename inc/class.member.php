<?php

class dtbaker_member {


	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public $detail_fields = array();

	public function init() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_action_member_email', array( $this, 'admin_send_email' ) );
		add_filter( 'manage_dtbaker_membership_posts_columns', array( $this, 'manage_dtbaker_membership_posts_columns' ) );
		add_action( 'manage_dtbaker_membership_posts_custom_column', array(
			$this,
			'manage_dtbaker_membership_posts_custom_column'
		), 10, 2 );


		is_admin() && add_action( 'pre_get_posts', function ( $query ) {

		} );

		function extranet_orderby( $query ) {
			// Nothing to do
			if ( ! $query->is_main_query() || 'clientarea' != $query->get( 'post_type' ) ) {
				return;
			}

			//-------------------------------------------
			// Modify the 'orderby' and 'meta_key' parts
			//-------------------------------------------
			$orderby = strtolower( $query->get( 'orderby' ) );
			$mods    = [
				'office' => [ 'meta_key' => 'extranet_sort_office', 'orderby' => 'meta_value_num' ],
				'date'   => [ 'meta_key' => 'extranet_appointment_date', 'orderby' => 'meta_value' ],
				''       => [ 'meta_key' => 'extranet_appointment_date', 'orderby' => 'meta_value' ],
				'type'   => [ 'meta_key' => 'extranet_sort_type', 'orderby' => 'meta_value_num' ],
				'ip'     => [ 'meta_key' => 'extranet_insolvency_practioner', 'orderby' => 'meta_value_num' ],
			];
			$key     = 'extranet_sort_' . $orderby;
			if ( isset( $mods[ $key ] ) ) {
				$query->set( 'meta_key', $mods[ $key ]['meta_key'] );
				$query->set( 'orderby', $mods[ $key ]['orderby'] );
			}
		}

		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );

		add_shortcode( 'membership_listing', array( $this, 'membership_listing' ) );

		$this->detail_fields = apply_filters( 'dtbaker_membership_detail_fields', array(
			'interests'      => array(
				'title' => 'Your Interests',
				'type'  => 'text',
				'eg'    => 'e.g. Programming, Electronics, 3D Printing, Drones'
			),
			'role'           => array(
				'title' => 'Committee Position',
				'type'  => 'text',
				'eg'    => 'e.g. Treasurer'
			),
			'rfid'           => 'RFID Keys',
			'square_id'      => 'Square Contact',
			'locker_number'  => 'Locker Number',
			'member_start'   => array(
				'title' => 'Member Start',
				'type'  => 'date',
			),
			'member_end'     => array(
				'title' => 'Member End',
				'type'  => 'date',
			),
			'phone'          => 'Phone Number',
			'emergency'      => array(
				'title' => 'Emergency Contact',
				'type'  => 'text',
				'eg'    => 'Please enter the name and number of an emergency contact'
			),
			'favfood'        => array(
				'title' => 'Favorite Food?',
				'type'  => 'text',
				'eg'    => 'Pizza? BBQ? Subway? Sushi?'
			),
			'facebook'       => 'Facebook',
			'twitter'        => 'Twitter',
			'google_plus'    => 'Google+',
			'email'          => 'Email Address',
			'slack'          => array(
				'title' => 'Slack Username',
				'type'  => 'text',
				'eg'    => 'What is your gctechspace.org Slack username?'
			),
			'linkedin'       => 'LinkedIn',
			'notifications'  => array(
				'title'   => 'Notifications',
				'type'    => 'select',
				'options' => array(
					'0' => 'Publish notifications on door openenings (e.g. to Slack)',
					'1' => 'No public notifications',
				)
			),
			'nickname'       => 'Nickname',
			'public_profile' => array(
				'title'   => 'Public Profile',
				'type'    => 'select',
				'options' => array(
					'0' => 'Hidden',
					'1' => 'Shown',
				)
			),
			'valid_times'    => array(
				'title' => 'Valid Access Times',
				'type'  => 'text',
				'eg'    => 'mon8-13|wed9-11 for Monday 8am to 1pm access, plus Wed 9-11am access only.'
			),
			'automatic_type' => array(
				'title'   => 'Automatic Type',
				'type'    => 'select',
				'options' => array(
					'invoice_email'    => '(default) Generate Invoice, Automatic Email',
					'invoice_no_email' => 'Generate Invoice, No Email',
					'manual_invoice'   => 'Prompt for manual invoice generation',
					'ignore'           => 'Ignore All Automatic Operations',
				)
			),
		) );

	}

	public function admin_menu() {


	}

	public function trigger_email( $member_id, $force = false ) {

		if ( $member_id ) {
			$details = $this->get_details( $member_id );
			if ( ! empty( $details['email'] ) && $email = filter_var( trim( $details['email'] ), FILTER_VALIDATE_EMAIL ) ) {

				$last_email_sent = get_post_meta( $member_id, 'checkin_email_sent', true );
				if ( $force || ! $last_email_sent || $last_email_sent < time() - 3600 ) {


					$show_wifi_password = false;
					if ( $details['expiry_days'] && $details['expiry_days'] > 0 ) {
						$wifipassword       = get_option( 'techspace_membership_wifi_password' );
						$show_wifi_password = true;
					}

					$email_history = get_post_meta( $member_id, 'email_hist', true );
					if ( ! is_array( $email_history ) ) {
						$email_history = array();
					}
					$email_history[] = array(
						'time' => time(),
						'type' => 'checkin',
					);
					update_post_meta( $member_id, 'email_hist', $email_history );


					update_post_meta( $member_id, 'checkin_email_sent', time() );
					$name    = ! empty( $details['nickname'] ) ? $details['nickname'] : get_the_title( $member_id );
					$subject = 'Welcome to TechSpace!';
					$headers = array(
						'From: GC TechSpace <dtbaker@gctechspace.org>',
						'Content-Type: text/html; charset=UTF-8'
					);
					ob_start();
					include( 'email/checkin.html' );
					$body = ob_get_clean();

					wp_mail( $email, $subject, $body, $headers );
				}

				return true;
			}
		}

		return false;

	}

	public function send_member_email( $member_id ) {

		if ( $member_id ) {
			$details = $this->get_details( $member_id );
			if ( ! empty( $details['email'] ) && $email = filter_var( trim( $details['email'] ), FILTER_VALIDATE_EMAIL ) ) {

				$email_history = get_post_meta( $member_id, 'email_hist', true );
				if ( ! is_array( $email_history ) ) {
					$email_history = array();
				}
				$email_history[] = array(
					'time' => time(),
					'type' => 'profile',
				);
				update_post_meta( $member_id, 'email_hist', $email_history );

				$name = ! empty( $details['nickname'] ) ? $details['nickname'] : get_the_title( $member_id );

				// hash from class.submit.php
				$expiry_time = strtotime( '+48 hours' );
				$member_url  = 'https://gctechspace.org/members/your-details/?hash=' . $member_id . '.' . $expiry_time . '.' . md5( "Techspace " . AUTH_KEY . " Membership Link for member $member_id at timestamp $expiry_time " );
				$subject     = 'TechSpace Membership';
				$headers     = array(
					'From: GC TechSpace <dtbaker@gctechspace.org>',
					'Content-Type: text/html; charset=UTF-8'
				);
				ob_start();
				include( 'email/profile.php' );
				$body = ob_get_clean();

				wp_mail( $email, $subject, $body, $headers );

				return true;
			}
		}

		return false;

	}

	public function send_become_member_email( $member_id ) {

		if ( $member_id ) {
			$details = $this->get_details( $member_id );
			if ( ! empty( $details['email'] ) && $email = filter_var( trim( $details['email'] ), FILTER_VALIDATE_EMAIL ) ) {

				$email_history = get_post_meta( $member_id, 'email_hist', true );
				if ( ! is_array( $email_history ) ) {
					$email_history = array();
				}
				$email_history[] = array(
					'time' => time(),
					'type' => 'become-member',
				);
				update_post_meta( $member_id, 'email_hist', $email_history );

				$name = ! empty( $details['nickname'] ) ? $details['nickname'] : get_the_title( $member_id );

				// hash from class.submit.php
				$expiry_time = strtotime( '+4 days' );
				$member_url  = 'https://gctechspace.org/members/your-details/?hash=' . $member_id . '.' . $expiry_time . '.' . md5( "Techspace " . AUTH_KEY . " Membership Link for member $member_id at timestamp $expiry_time " );
				$subject     = 'Become a TechSpace Member';
				$headers     = array(
					'From: GC TechSpace <dtbaker@gctechspace.org>',
					'Content-Type: text/html; charset=UTF-8'
				);
				ob_start();
				include( 'email/become-member.php' );
				$body = ob_get_clean();

				wp_mail( $email, $subject, $body, $headers );

				return true;
			}
		}

		return false;

	}

	public function membership_listing( $atts = array() ) {
		$members        = get_posts( array(
			'post_type'      => 'dtbaker_membership',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		) );
		$member_details = array();
		// get all member details.
		foreach ( $members as $member ) {
			$details = $this->get_details( $member->ID );
			//if ( $details['expiry_days'] > 0 ) {
			$details['member_id'] = $member->ID;
			//isset( $details['public_profile'] ) && $details['public_profile'] &&
			$member_details[ $member->ID ] = $details;
			//}
		}

		$members_sorted = array();
		// grab steve first:
		foreach ( $member_details as $member_id => $member_detail ) {
			if ( $member_id == 485965 ) {
				$members_sorted[] = $member_detail;
				unset( $member_details[ $member_id ] );
				break;
			}
		}
		// grab other committe members.
		foreach ( $member_details as $member_id => $member_detail ) {
			if ( ! empty( $member_detail['role'] ) && $member_detail['public_profile'] ) {
				$members_sorted[] = $member_detail;
				unset( $member_details[ $member_id ] );
			}
		}
		// grab other public members.
		foreach ( $member_details as $member_id => $member_detail ) {
			if ( $member_detail['public_profile'] ) {
				$members_sorted[] = $member_detail;
				unset( $member_details[ $member_id ] );
			}
		}
		// add everyone else as private.
		foreach ( $member_details as $member_id => $member_detail ) {
			$members_sorted[] = $member_detail;
			unset( $member_details[ $member_id ] );
		}

		$per_row = 3;
		ob_start();
		?>
		<section class="tb-info-box tb-tax-info">
			<div class="inner"><p>Below is a list of some of our members. Come say hi at one of our Wednesday night
					meetings.</p>
			</div>
		</section>

		<?php
		while ( count( $members_sorted ) ) {
			?>
			<div class="row techspace-members">
				<?php for ( $x = 0; $x <= $per_row; $x ++ ) {
					$member_details = array_shift( $members_sorted );
					?>
					<div class="col col-sm-3">
						<div class="profile-wrapper">
							<?php if ( $member_details['public_profile'] ) { ?>
								<div class="profile-photo">
									<?php echo get_the_post_thumbnail( $member_details['member_id'], array( 200, 200 ) ); ?>
								</div>
								<h2 class="profile-name">
									<?php echo esc_html( $member_details['nickname'] ); ?>
								</h2>
								<?php if ( ! empty( $member_details['role'] ) ) { ?>
									<div class="profile-highlight">
										<?php echo esc_html( $member_details['role'] ); ?>
									</div>
								<?php } ?>
								<div class="profile-details">
									<?php echo esc_html( $member_details['interests'] ); ?>
								</div>
								<div class="profile-contact">
									<?php if ( ! empty( $member_details['slack'] ) ) { ?>
										<a href="https://gctechspace.slack.com/team/<?php echo esc_attr( $member_details['slack'] ); ?>"
										   target="_blank" class="fa fa-slack">&nbsp;</a>
									<?php } ?>
									<?php if ( ! empty( $member_details['twitter'] ) ) { ?>
										<a href="https://twitter.com/<?php echo esc_attr( $member_details['twitter'] ); ?>" target="_blank"
										   class="fa fa-twitter">&nbsp;</a>
									<?php } ?>
									<?php if ( ! empty( $member_details['linkedin'] ) ) { ?>
										<a href="https://linkedin.com/in/<?php echo esc_attr( $member_details['linkedin'] ); ?>"
										   target="_blank" class="fa fa-linkedin">&nbsp;</a>
									<?php } ?>
								</div>
							<?php } else { ?>
								<div class="profile-photo">
									<img src="https://gctechspace.org/wp-content/uploads/2016/09/member-unknown.jpg" width="200"
									     height="200">
								</div>
								<h2 class="profile-name">
									Member
								</h2>
								<div class="profile-details">
									Public Profile Hidden
								</div>
							<?php } ?>
						</div>
					</div>
				<?php } ?>
			</div>
			<?php
		}

		return ob_get_clean();
	}


	public function manage_dtbaker_membership_posts_columns( $columns ) {
		unset( $columns['author'] );
		unset( $columns['date'] );
		$columns['rfid']      = __( 'RFID' );
		$columns['square_id'] = __( 'Square Contact' );
		$columns['invoices']  = __( 'Invoices' );
		//$columns['member_start'] = __( 'Membership Start' );
		$columns['slack']      = __( 'Slack' );
		$columns['phone']      = __( 'Phone' );
		$columns['email']      = __( 'Email' );
		$columns['member_end'] = __( 'Membership Expiry' );

		return $columns;
	}

	public function manage_dtbaker_membership_posts_sortable_columns( $columns ) {
		$columns['member_end'] = 'member_end';

		return $columns;
	}

	public function manage_dtbaker_membership_posts_custom_column( $column, $post_id ) {
		$membership_details = $this->get_details( $post_id );
		switch ( $column ) {
			case 'rfid':
				// look up linked rfid keys.

				$rfid_keys = get_posts( array(
					'post_type'   => 'dtbaker_rfid',
					'post_status' => 'publish',
					'meta_key'    => 'rfid_details_member_id',
					'meta_value'  => $post_id,
				) );
				if ( $rfid_keys ) {
					foreach ( $rfid_keys as $rfid_key ) {
						//echo str_pad(substr($rfid_code,0,5), strlen($rfid_code), '*', STR_PAD_RIGHT);
						printf( '<a href="%s">%s</a> ', esc_url( get_edit_post_link( $rfid_key->ID ) ), esc_html( $rfid_key->post_title ) );
					}
				} else {
					echo 'None';
				}
				break;
			case 'square_id':
				if ( ! empty( $membership_details['square_member_cache'] ) ) {
					echo esc_html( implode( ' / ', $membership_details['square_member_cache'] ) );
				}
				break;
			case 'member_start':
				if ( ! empty( $membership_details[ $column ] ) ) {
					echo date( 'Y-m-d', $membership_details[ $column ] );
				}
				break;
			case 'member_end':
				if ( ! empty( $membership_details[ $column ] ) ) {
					echo date( 'Y-m-d', $membership_details[ $column ] );
					$member_status = 'member_status_';
					if ( $membership_details[ $column ] <= time() ) {
						$member_status .= 'expired';
					} else if ( $membership_details[ $column ] <= strtotime( '+3 weeks' ) ) {
						$member_status .= 'expiring';
					} else {
						$member_status .= 'good';
					}
					echo ' <span class="member_status ' . $member_status . '">' . DtbakerMembershipManager::get_instance()->fuzzy_date( $membership_details[ $column ] ) . '</span>';
				}
				break;
			case 'invoices':
				if ( ! empty( $membership_details['invoice_cache'] ) && is_array( $membership_details['invoice_cache'] ) && count( $membership_details['invoice_cache'] ) ) {
					end( $membership_details['invoice_cache'] );
					$this->_print_invoice_details( key( $membership_details['invoice_cache'] ), current( $membership_details['invoice_cache'] ) );
					/*foreach($membership_details['invoice_cache'] as $invoice_id => $invoice){
						$this->_print_invoice_details($invoice_id, $invoice);
					}*/
				}
				break;
			default:
				if ( ! empty( $membership_details[ $column ] ) ) {
					echo esc_html( $membership_details[ $column ] );
				} else {
					echo 'N/A';
				}
				break;

		}
	}


	public function get_details( $post_id ) {
		$detail_fields = array();
		foreach ( $this->detail_fields as $key => $val ) {
			$detail_fields[ $key ] = get_post_meta( $post_id, 'membership_details_' . $key, true );
		}
		$detail_fields['expiry_days']   = ( ! empty( $detail_fields['member_end'] ) && $detail_fields['member_end'] > time() + 86400 ) ? round( ( $detail_fields['member_end'] - time() ) / 86400 ) : 0;
		$detail_fields['invoice_cache'] = get_post_meta( $post_id, 'membership_details_invoice_cache', true );

		return $detail_fields;
	}

	public function admin_send_email() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'member_email' ) || empty( $_GET['member_id'] ) ) {
			die( 'failed to send email' );
		} else {
			$member_id = (int) $_GET['member_id'];
			if ( isset( $_GET['type'] ) && $_GET['type'] == 'checkin-welcome' ) {
				$this->trigger_email( $member_id, true );
			} else if ( isset( $_GET['type'] ) && $_GET['type'] == 'become-member' ) {
				$this->send_become_member_email( $member_id );
			} else {
				$this->send_member_email( $member_id );
			}
			echo 'sent';
		}
	}

	public function meta_box_emails_callback( $post ) {
		wp_nonce_field( 'dtbaker_membership_metabox_email_nonce', 'dtbaker_membership_metabox_email_nonce' );

		?>
		<div class="dtbaker_member_email_history">
			<?php
			$email_history = get_post_meta( $post->ID, 'email_hist', true );
			if ( ! is_array( $email_history ) ) {
				$email_history = array();
			}
			$email_history = array_slice( array_reverse( $email_history ), 0, 10 );
			foreach ( $email_history as $history ) {
				?>
				<?php echo date( 'Y-m-d H:i:s', $history['time'] ); ?> = <?php echo $history['type']; ?> <br/>
				<?php
			}
			?>
			<br>

			<a
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=member_email&member_id=' . $post->ID ), 'member_email', 'nonce' ) ); ?>"
				target="_blank">Send a "Update Your Details" email</a> <br/>
			<a
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=member_email&type=become-member&member_id=' . $post->ID ), 'member_email', 'nonce' ) ); ?>"
				target="_blank">Send a "Become a TechSpace Member" email</a> <br/>
			<a
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=member_email&type=checkin-welcome&member_id=' . $post->ID ), 'member_email', 'nonce' ) ); ?>"
				target="_blank">Send the "Chicken Wifi Password" welcome email</a> <br/>

		</div>
		<?php

	}

	public function meta_box_callback( $post ) {

		wp_nonce_field( 'dtbaker_membership_metabox_nonce', 'dtbaker_membership_metabox_nonce' );

		$membership_details = $this->get_details( $post->ID );
		foreach ( $this->detail_fields as $field_id => $field_data ) {


			if ( ! is_array( $field_data ) ) {
				$field_data = array(
					'title' => $field_data,
					'type'  => 'text'
				);
			}

			?>
			<div class="dtbaker_member_form_field">
				<label
					for="member_detail_<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field_data['title'] ); ?></label>
				<?php switch ( $field_id ) {
					case 'rfid':
						//DtbakerMembershipManager::get_instance()->generate_post_select( 'membership_details[rfid]', 'dtbaker_rfid', $membership_details['rfid']);
						// look up linked rfid keys.
						$rfid_keys = get_posts( array(
							'post_type'   => 'dtbaker_rfid',
							'post_status' => 'publish',
							'meta_key'    => 'rfid_details_member_id',
							'meta_value'  => $post->ID,
						) );
						if ( $rfid_keys ) {
							foreach ( $rfid_keys as $rfid_key ) {
								//echo str_pad(substr($rfid_code,0,5), strlen($rfid_code), '*', STR_PAD_RIGHT);
								printf( '<a href="%s">%s</a> ', esc_url( get_edit_post_link( $rfid_key->ID ) ), esc_html( $rfid_key->post_title ) );
							}
						} else {
							echo 'None';
						}
						break;
					case 'square_id':
						// lookup square contacts from api. uses the ContactID field from square
						$contacts = TechSpace_Square::get_instance()->get_all_contacts( isset( $_REQUEST['square_refresh'] ) );
						if ( empty( $membership_details['square_id'] ) ) {
							$membership_details['square_id'] = 0;
						}
						?>
						<select name="membership_details[square_id]">
							<option value=""> - Please Select -</option>
							<option value="CreateNew">Create New Square Contact</option>
							<?php if ( is_array( $contacts ) && count( $contacts ) ) {
								foreach ( $contacts as $contact_id => $contact ) { ?>
									<option
										value="<?php echo esc_attr( $contact_id ); ?>" <?php echo selected( $membership_details['square_id'], $contact_id ); ?>><?php echo esc_attr( $contact['name'] . ' (' . $contact['email'] . ')' ); ?></option>
								<?php }
							} else {
								?>
								<option value=""> failed to get square listing</option><?php
							} ?>
						</select>
						<a
							href="https://squareup.com/dashboard/customers/directory/customer/<?php echo ! empty( $membership_details['square_id'] ) ? $membership_details['square_id'] : ''; ?>"
							target="_blank">Open Square</a>
						<a href="<?php echo add_query_arg( 'square_refresh', 1, get_edit_post_link( $post->ID ) ); ?>"
						   class="member_inline_button">Refresh List</a>
						<?php
						// look up square invoices for this contact
						if ( ! empty( $membership_details['square_id'] ) ) {
							?>
							<br/>
							<br/>
							Square Invoices:
							<a href="<?php echo add_query_arg( 'square_invoice_refresh', 1, get_edit_post_link( $post->ID ) ); ?>"
							   class="member_inline_button">Refresh</a>
							<a
								class="member_inline_button"
								href="https://squareup.com/dashboard/customers/directory/customer/<?php echo ! empty( $membership_details['square_id'] ) ? $membership_details['square_id'] : ''; ?>"
								target="_blank">Create New Invoice</a>
							<br/>
							<?php
							$invoices = TechSpace_Square::get_instance()->get_contact_invoices( $membership_details['square_id'], isset( $_REQUEST['square_invoice_refresh'] ) );
							if ( $invoices ) {
								$this->cache_member_invoices( $post->ID, $invoices );
								?>
								<ul class="dtbaker-square-invoices">
									<?php foreach ( $invoices as $invoice_id => $invoice ) { ?>
										<li>
											<?php $this->_print_invoice_details( $invoice_id, $invoice ); ?>
										</li>
									<?php } ?>
								</ul>
								<?php
							}
						}

						break;
					default:
						switch ( $field_data['type'] ) {
							case 'select':
								if ( empty( $membership_details[ $field_id ] ) ) {
									$membership_details[ $field_id ] = 0;
								}
								?>
								<select name="membership_details[<?php echo esc_attr( $field_id ); ?>]"
								        id="member_detail_<?php echo esc_attr( $field_id ); ?>">
									<?php foreach ( $field_data['options'] as $value => $label ) { ?>
										<option
											value="<?php echo esc_attr( $value ); ?>" <?php echo selected( $membership_details[ $field_id ], $value ); ?>><?php echo esc_attr( $label ); ?></option>
									<?php } ?>
								</select>
								<?php
								break;
							case 'text':
								?>
								<input type="text" name="membership_details[<?php echo esc_attr( $field_id ); ?>]"
								       id="member_detail_<?php echo esc_attr( $field_id ); ?>"
								       value="<?php echo esc_attr( isset( $membership_details[ $field_id ] ) ? $membership_details[ $field_id ] : '' ); ?>">
								<?php
								break;
							case 'date':
								?>
								<input type="text" name="membership_details[<?php echo esc_attr( $field_id ); ?>]"
								       id="member_detail_<?php echo esc_attr( $field_id ); ?>"
								       value="<?php echo esc_attr( ! empty( $membership_details[ $field_id ] ) ? date( 'Y-m-d', $membership_details[ $field_id ] ) : '' ); ?>"
								       class="dtbaker-datepicker">
								<?php
								break;
						}
				}
				?>
			</div>
			<?php
		}

	}

	private function _print_invoice_details( $invoice_id, $invoice ) {
		?>
		<a href="https://go.xero.com/AccountsReceivable/View.aspx?InvoiceID=<?php echo esc_attr( $invoice_id ); ?>"
		   target="_blank"><?php echo esc_html( $invoice['number'] . ' ' . date( 'Y-m-d', $invoice['time'] ) . ' ' . $invoice['status'] . ' $' . $invoice['total'] ); ?></a> <?php
		if ( $invoice['due'] > 0 ) {
			?>
			<span class="dtbaker-invoice-due">$<?php echo esc_html( $invoice['due'] ); ?> due.</span>
			<?php
		} else if ( $invoice['status'] == 'PAID' ) {
			?>
			<span class="dtbaker-invoice-paid">$<?php echo esc_html( $invoice['total'] ); ?> paid</span>
			<?php
		}
		if ( ! $invoice['emailed'] ) {
			?>
			<span class="dtbaker-invoice-emailed">Invoice not emailed!</span>
			<?php
		}
	}

	public function add_meta_box() {

		$screens = array( 'dtbaker_membership' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'dtbaker_membership_page_meta_price',
				__( 'Member Details' ),
				array( $this, 'meta_box_callback' ),
				$screen,
				'normal',
				'high'
			);
			add_meta_box(
				'dtbaker_membership_page_meta_emails',
				__( 'Recent Member Emails' ),
				array( $this, 'meta_box_emails_callback' ),
				$screen,
				'normal',
				'high'
			);
		}


	}

	public function save_meta_box( $post_id ) {
		// Check if our nonce is set.
		if ( ! isset( $_POST['dtbaker_membership_metabox_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['dtbaker_membership_metabox_nonce'], 'dtbaker_membership_metabox_nonce' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( isset( $_POST['membership_details'] ) && is_array( $_POST['membership_details'] ) ) {
			$membership_details = $_POST['membership_details'];


			if ( ! empty( $membership_details['square_id'] ) ) {

				// create new contact if missing
				if ( $membership_details['square_id'] == "CreateNew" ) {
					$new_square_contact_id = TechSpace_Square::get_instance()->create_contact( array(
						'name'  => get_the_title( $post_id ),
						'email' => $membership_details['email'],
						'phone' => $membership_details['phone'],
					) );
					$all_contacts     = TechSpace_Square::get_instance()->get_all_contacts( true );
					if ( $new_square_contact_id && isset( $all_contacts[ $new_square_contact_id ] ) ) {
						$membership_details['square_id'] = $new_square_contact_id;
					} else {
						echo 'Failed to create new contact in square';
						exit;
					}
				}
			}
			foreach ( $this->detail_fields as $key => $val ) {
				// format date fields as timestamps for easier querying.
				if ( isset( $membership_details[ $key ] ) ) {
					if ( is_array( $val ) && isset( $val['type'] ) && $val['type'] == 'date' ) {
						$membership_details[ $key ] = strtotime( $membership_details[ $key ] );
					}
					update_post_meta( $post_id, 'membership_details_' . $key, $membership_details[ $key ] );
				}
			}

		}

	}

	public function cache_member_invoices( $member_post_id, $invoices ) {
		if ( $invoices && is_array( $invoices ) ) {
			update_post_meta( $member_post_id, 'membership_details_invoice_cache', $invoices );
		}
	}

	public function register_custom_post_type() {


		$labels = array(
			'name'              => _x( 'Access Points', 'taxonomy general name', 'techspace-membership' ),
			'singular_name'     => _x( 'Access Point', 'taxonomy singular name', 'techspace-membership' ),
			'search_items'      => __( 'Search Access', 'techspace-membership' ),
			'all_items'         => __( 'All Access', 'techspace-membership' ),
			'parent_item'       => __( 'Parent Access', 'techspace-membership' ),
			'parent_item_colon' => __( 'Parent Access:', 'techspace-membership' ),
			'edit_item'         => __( 'Edit Access', 'techspace-membership' ),
			'update_item'       => __( 'Update Access', 'techspace-membership' ),
			'add_new_item'      => __( 'Add New Access', 'techspace-membership' ),
			'new_item_name'     => __( 'New Access Name', 'techspace-membership' ),
			'menu_name'         => __( 'Access Points', 'techspace-membership' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'member-access' ),
		);

		register_taxonomy( 'dtbaker_membership_access', array( 'dtbaker_membership' ), $args );


		$labels = array(
			'name'              => _x( 'Membership Type', 'taxonomy general name', 'techspace-membership' ),
			'singular_name'     => _x( 'Membership Type', 'taxonomy singular name', 'techspace-membership' ),
			'search_items'      => __( 'Search Membership Type', 'techspace-membership' ),
			'all_items'         => __( 'All Membership Type', 'techspace-membership' ),
			'parent_item'       => __( 'Parent Membership Type', 'techspace-membership' ),
			'parent_item_colon' => __( 'Parent Membership Type:', 'techspace-membership' ),
			'edit_item'         => __( 'Edit Membership Type', 'techspace-membership' ),
			'update_item'       => __( 'Update Membership Type', 'techspace-membership' ),
			'add_new_item'      => __( 'Add New Membership Type', 'techspace-membership' ),
			'new_item_name'     => __( 'New Membership Type Name', 'techspace-membership' ),
			'menu_name'         => __( 'Membership Types', 'techspace-membership' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'member-membership-type' ),
		);

		register_taxonomy( 'dtbaker_membership_type', array( 'dtbaker_membership' ), $args );


		$labels = array(
			'name'               => 'Members',
			'singular_name'      => 'Member',
			'menu_name'          => 'Members',
			'parent_item_colon'  => 'Parent Member:',
			'all_items'          => 'All Members',
			'view_item'          => 'View Member',
			'add_new_item'       => 'Add New Member',
			'add_new'            => 'New Member',
			'edit_item'          => 'Edit Member',
			'update_item'        => 'Update Member',
			'search_items'       => 'Search Members',
			'not_found'          => 'No Members found',
			'not_found_in_trash' => 'No Members found in Trash',
		);

		$rewrite = array(
			'slug'       => 'membership',
			'with_front' => false,
			'pages'      => true,
			'feeds'      => true,
		);
		$args    = array(
			'label'               => 'dtbaker_membership_item',
			'description'         => 'Members',
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'revisions' ),
			'taxonomies'          => array(),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 36,
			'menu_icon'           => 'dashicons-admin-users',
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => $rewrite,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type( 'dtbaker_membership', $args );


	}

	public function get_member_types() {

		// todo: config utility to select which line items to store.
		$member_types = array(
			'5bc6f27f-4a9a-4e24-9ee9-7ca75e4b0496' => array(
				'code'   => 'mem12MonthsFor10',
				'name'   => '12 Months Membership',
				'months' => '12',
				'price'  => '250',
			),
			'b5f3cfc-6f3e-4311-978f-e36069929067'  => array(
				'code'   => 'mem12Months',
				'name'   => '12 Months Membership (Grandfathered $225)',
				'months' => '12',
				'price'  => '225',
			),
			/*'07860992-c84a-4633-9805-065aba35401b' => array(
				'code'   => 'mem6Monthly',
				'name'   => '6 Months Membership',
				'months' => '6',
				'price'  => '125',
			),*/
			'e11ddd97-062e-408f-abaa-2cdd1214a2aa' => array(
				'code'   => 'memMonthly2016',
				'name'   => '1 Month Membership',
				'months' => '1',
				'price'  => '25',
			),
		);

		return $member_types;
	}

}

dtbaker_member::get_instance()->init();

