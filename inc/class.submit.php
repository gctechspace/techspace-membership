<?php

// handle frontend form submission for membership applications


class TechSpace_Frontend_Submit {

	/** Hook WordPress
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'handle_submit' ), 100 );
		add_shortcode( 'membership_signup_form', array( $this, 'membership_signup_form' ) );
		add_shortcode( 'membership_update_form', array( $this, 'membership_update_form' ) );
	}

	public $details = array( 'interests' => '', 'email' => '',
	                         //'phone' => '',
	'emergency' => '' );
	public $update_details = array(
		'interests' => '',
		'email'     => '',
		//'phone'     => '',
		'emergency' => '',
		'favfood'   => '',
		'twitter'   => '',
		'slack'     => '',
		'linkedin'  => '',
		'nickname'  => '',
	);

	public function handle_submit() {
		try {

			if ( ! empty( $_POST ) && ! empty( $_POST['techspace_member_update'] ) && wp_verify_nonce( $_POST['techspace_submit'], 'techspace_update_member' ) ) {


				$hash = $_GET['hash'];
				$bits = explode( '.', $hash );
				if ( count( $bits ) !== 3 ) {
					throw new Exception( '(link error )' );
				}
				$member_id = (int) $bits[0];
				$time      = (int) $bits[1];
				$hashcheck = $bits[2];

				$correct_hash = md5( "Techspace " . AUTH_KEY . " Membership Link for member $member_id at timestamp $time " );

				if ( $correct_hash !== $hashcheck ) {
					throw new Exception( '(link hash error )' );
				}

				if ( time() > $time ) {
					throw new Exception( '(expire error )' );
				}

				$title = '';
				if ( ! empty ( $_POST['your_name'] ) ) {
					$title = trim( wp_strip_all_tags( $_POST['your_name'] ) );
				}
				$details = array();
				foreach ( $this->update_details as $key => $val ) {
					// format date fields as timestamps for easier querying.
					if ( ! empty( $_POST[ $key ] ) ) {
						if ( $key == 'email' ) {
							$valid_email = filter_var( trim( $_POST[ $key ] ), FILTER_VALIDATE_EMAIL );
							if ( $valid_email ) {
								$details[ $key ] = $valid_email;
							}
						} else {
							$details[ $key ] = wp_strip_all_tags( $_POST[ $key ] );
						}
					}
				}

				$post_data = array(
					'ID' => $member_id,
				);

				if ( strlen( trim( $title ) ) ) {
					$post_data['post_title'] = trim( $title );
					wp_update_post( $post_data );
				}

				$membership_category      = 0;
				$membership_category_name = 0;
				$available_categories     = get_terms( 'dtbaker_membership_type', array(
					'hide_empty' => false,
				) );
				foreach ( $available_categories as $available_category ) {
					if ( $available_category->term_id == $_POST['membership_category'] ) {
						$membership_category      = $available_category->term_id;
						$membership_category_name = $available_category->name;
					}
				}

				wp_set_object_terms( $member_id, $membership_category, 'dtbaker_membership_type' );

				//print_r($post);print_r($details);exit;
				foreach ( $details as $key => $val ) {
					// format date fields as timestamps for easier querying.
					if ( $val ) {
						update_post_meta( $member_id, 'membership_details_' . $key, $val );
					}
				}
				// use the cron class to send slack notification.
				$cron              = dtbaker_member_cron::get_instance();
				$notification_text = 'Member "' . get_the_title( $member_id ) . '" just updated their details and has chosen a membership level of: "' . $membership_category_name . '"';
				if ( ! empty( $_POST['member_comments'] ) ) {
					$notification_text .= "\nThe member left some comments/suggestions: " . $_POST['member_comments'];
				}
				$cron->send_notification( $notification_text, 'memo' );
				wp_mail( "dtbaker@gmail.com", "TechSpace Membership Update (" . get_the_title( $member_id ) . ")", "Member update : $title. Link: " . get_edit_post_link( $member_id ) . ' ' . var_export( $_POST, true ) );


				wp_redirect( 'https://gctechspace.org/members/your-details/?updated' );
				exit;

			}
			if ( ! empty( $_POST ) && ! empty( $_POST['techspace_member_submit'] ) && wp_verify_nonce( $_POST['techspace_submit'], 'techspace_submit_member' ) ) {

				$errors_for_user = [];
				// Do some minor form validation to make sure there is content
				$title = '';
				if ( isset ( $_POST['your_name'] ) ) {
					$title = trim( wp_strip_all_tags( $_POST['your_name'] ) );
				}
				if ( ! $title ) {
					$errors_for_user[] = 'Please enter your name.';
				}
				if ( ! $_POST['membership_category'] ) {
					$errors_for_user[] = 'Please select a membership type.';
				}

				$details = $this->details;
				foreach ( $details as $key => $val ) {
					// format date fields as timestamps for easier querying.
					if ( ! empty( $_POST[ $key ] ) ) {
						if ( $key == 'email' ) {
							$valid_email = filter_var( trim( $_POST[ $key ] ), FILTER_VALIDATE_EMAIL );
							if ( $valid_email ) {
								$details[ $key ] = $valid_email;
							} else {
								$errors_for_user[] = 'Please enter a valid email.';
							}
						} else {
							$details[ $key ] = wp_strip_all_tags( $_POST[ $key ] );
						}
					} else {
						$errors_for_user[] = 'Please ensure all fields are filled in.';
					}
				}

				// check if existing member first:
				if(!empty($details['email']) ) {
					$existing_member_check_args = array(
						'posts_per_page' => 2,
						'post_type'      => 'dtbaker_membership',
						'meta_query'     => array(
							array(
								'key'     => 'membership_details_email',
								'value'   => $details['email'],
								'compare' => '=',
							),
						),
					);

					$existing_members = get_posts( $existing_member_check_args );
					if ( count( $existing_members ) > 0 ) {
						$errors_for_user[] = 'Your email address already exists in our membership system. Please contact us for help with membership.';
					}
				}

				if ( $errors_for_user ) {
					echo '<h2>Error</h2>';
					echo implode( '<br/>', $errors_for_user );
					echo '<br/> <br/> Please <strong>Go Back</strong> and try again.';
					exit;
				}

				$member_types                       = TechSpace_Cpt::get_instance()->get_member_types();
				$membership_category                = 0;
				$membership_category_name           = 0;
				$membership_category_price          = 0;
				$membership_category_square_enabled = false;
				foreach ( $member_types as $member_type ) {
					if ( $member_type['term_id'] == $_POST['membership_category'] ) {
						$membership_category                = $member_type['term_id'];
						$membership_category_name           = $member_type['name'];
						$membership_category_price          = $member_type['price'];
						$membership_category_square_enabled = $member_type['square_invoices'];
					}
				}


				// Add the content of the form to $post as an array
				$post    = array(
					'post_title'   => wp_strip_all_tags( $title ),
					'post_content' => "$membership_category_name Signup from website. IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n\n\n" . wp_strip_all_tags( isset( $_POST['member_comments'] ) ? $_POST['member_comments'] : '' ),
					'post_status'  => 'publish',            // Choose: publish, preview, future, etc.
					'post_type'    => 'dtbaker_membership'  // Use a custom post type if you want to
				);
				$post_id = wp_insert_post( $post );  // http://codex.wordpress.org/Function_Reference/wp_insert_post

				if ( $post_id ) {
					wp_set_object_terms( $post_id, $membership_category, 'dtbaker_membership_type' );

					// use the cron class to send slack notification.
					$cron = dtbaker_member_cron::get_instance();

					foreach ( $details as $key => $val ) {
						// format date fields as timestamps for easier querying.
						if ( $val ) {
							update_post_meta( $post_id, 'membership_details_' . $key, $val );
						}
					}

					$new_square_contact_id = TechSpace_Square::get_instance()->create_contact( array(
						'name'  => wp_strip_all_tags( $title ),
						'email' => $details['email'],
						//'phone' => $details['phone'],
					) );

					if ( $new_square_contact_id ) {
						update_post_meta( $post_id, 'membership_details_square_id', $new_square_contact_id );

						if ( $membership_category && $membership_category_square_enabled ) {
							$new_invoice_id = TechSpace_Square::get_instance()->create_invoice( $post_id, $new_square_contact_id, [
								'name'     => $membership_category_name,
								'money'    => $membership_category_price,
								'due_date' => date( 'Y-m-d' ),
							] );
							if ( $new_invoice_id ) {
								$cron->send_notification( 'New member signed up on the website: "' . $title . '" for "' . $membership_category_name . '".\n An invoice has been automatically generated for this member:\n https://squareup.com/dashboard/invoices/' . $new_invoice_id, 'memo', $post_id );
							} else {
								$cron->send_notification( "Error: Failed to make invoice in Square for new member " . $title . ' - not sure why - check with dave?', 'moneybag', $post_id );
							}
						} else {
							$cron->send_notification( 'New free member signed up on the website: "' . $title . '" for "' . $membership_category_name . '".', 'memo', $post_id );
						}
					} else {
						$cron->send_notification( "Error: Failed to create a square contact for new signed up member " . $title . ' - not sure why :( ', 'moneybag', $post_id );
						echo 'Sorry failed to create account, please contact support';
						exit;
					}

					wp_mail( "dtbaker@gmail.com", "TechSpace Membership Signup ($title)", "Member signup: $title. Type: $membership_category_name Square: $new_square_contact_id  Link: " . get_edit_post_link( $post_id ) );
				}

				$location = get_permalink( get_page_by_title( 'Signup Success' ) );

				echo "<meta http-equiv='refresh' content='0;url=" . esc_url( $location ) . "' />";
				exit;
			} // end IF
		}catch(Exception $e){
			wp_mail( "dtbaker@gmail.com", "TechSpace Membership Error", var_export($e, true) . var_export($_REQUEST,true));
			echo "Membership submission error, please go back and try again (or contact us for support).";
			exit;
		}
	}

	public function membership_signup_form( $atts = array() ) {
		ob_start();
		?>
		<form method="post" action="" class="techspace_signup" id="techspace_signup_form">
			<input type="hidden" name="techspace_member_submit" id="techspace_member_submit" value="">
			<?php wp_nonce_field( 'techspace_submit_member', 'techspace_submit' ); ?>

			<div class="membership_form_element"><label for="your_name">Your Name:</label><br/>
				<input type="text" id="your_name" value="" tabindex="1" size="20" name="your_name"/>
			</div>

			<div class="membership_form_element"><label for="membership_category">Membership Type:</label><br/>
				<?php
				$member_types = TechSpace_Cpt::get_instance()->get_member_types();
				foreach ( $member_types as $member_type ) {
					if ( $member_type['public_signup'] ) {
						?>
						<div>
							<input type="radio"
							       id="membership_category_<?php echo (int) $member_type['term_id']; ?>"
							       value="<?php echo (int) $member_type['term_id']; ?>"
							       name="membership_category"/> <?php echo esc_html( $member_type['name'] ) . ' - ' . esc_html( $member_type['description'] ); ?>
						</div>
						<?php
					}
				}
				?>
			</div>

			<?php foreach ( $this->details as $key => $val ) {
				$field = dtbaker_member::get_instance()->detail_fields[ $key ];
				if ( ! is_array( $field ) ) {
					$field = array( 'title' => $field );
				}
				?>
				<div class="membership_form_element"><label
						for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['title'] ); ?>:</label><br/>
					<?php if ( isset( $field['eg'] ) ) { ?>
						<small><?php echo $field['eg']; ?></small> <br/> <?php } ?>
					<input type="text" id="<?php echo esc_attr( $key ); ?>" value="" size="20"
					       name="<?php echo esc_attr( $key ); ?>"/>

				</div>
			<?php } ?>

			<div class="membership_form_element"><label for="member_comments">Comments or Suggestions:</label><br/>
				<textarea id="member_comments" rows="7" cols="80" name="member_comments"></textarea>
			</div>

			<div class="membership_form_element"><label for="recaptcha_answer">Answer this puzzle:</label><br/>
				<p>What is the value of this resistor in ohms? hint: it's between 60 and 70:</p>
				<img src="https://gctechspace.org/wp-content/uploads/2021/07/resistor-quiz.png" />
				<br/>
				Answer: <input id="recaptcha_answer" value=""/>
			</div>

			<script>
				function super_dooper_unhackable_sophisticated_recaptcha(button) {
				  var answer = document.getElementById('recaptcha_answer').value;
				  if(answer !== '62'){
				    alert('Sorry please enter the correct answer to the puzzle');
				    return false;
				  }
          document.getElementById('techspace_member_submit').value = 'true';
          setTimeout(function(){ button.value='Loading please wait....';}, 30);
          document.getElementById('techspace_signup_form').submit();
				  return true;
				}
			</script>


			<div class="membership_form_element">
				<input type="button" value="Submit" tabindex="6" onclick="super_dooper_unhackable_sophisticated_recaptcha(this)">
			</div>

		</form>
		<?php
		return ob_get_clean();
	}

	public function hide( $string ) {

		if ( ! $string ) {
			return '(nothing on file)';
		}

		return substr( $string, 0, 3 ) . '*************';
	}

	public function membership_update_form( $atts = array() ) {

		nocache_headers();

		if ( isset( $_GET['updated'] ) ) {
			ob_start();
			?>
			<div style="padding: 10px; background:#47a8f5; color: #FFF; margin: 0 0 20px;">
				Thank you for updating your details.
			</div>
			<?php
			return ob_get_clean();
		}

		if ( empty( $_GET['hash'] ) ) {
			return '(link error)';
		}
		$hash = $_GET['hash'];
		$bits = explode( '.', $hash );
		if ( count( $bits ) !== 3 ) {
			return '(link error )';
		}
		$member_id = (int) $bits[0];
		$time      = (int) $bits[1];
		$hashcheck = $bits[2];

		$correct_hash = md5( "Techspace " . AUTH_KEY . " Membership Link for member $member_id at timestamp $time " );

		if ( $correct_hash !== $hashcheck ) {
			return '(link error)';
		}

		if ( time() > $time ) {

			return 'Sorry, this link has expired. Please ask Dave from TechSpace to send the email to you again. ';
		}

		$time_to_expiry = $time - time();

		$member_details = dtbaker_member::get_instance()->get_details( $member_id );

		ob_start();
		?>
		<style type="text/css">
        .change-field {
            display: inline-block;
            width: 100%;
            padding: 12px 12px;
            font-size: 14px;
            line-height: 1.428571429;
            margin-bottom: 10px;
            color: #666;
            color: rgba(26, 26, 26, .7);
            background-color: #fff;
            background-image: none;
            border: 1px solid #f2f2f2;
            border-color: rgba(200, 200, 200, .4);
            border-radius: 0;
            -webkit-box-shadow: none;
            box-shadow: none;
            -webkit-transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
            transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
        }

        .change-field .change-link {

        }

        .hidden-clicker {
            display: none !important;
        }

        .toggled-on .change-field {
            display: none !important;
        }

        .toggled-on .hidden-clicker {
            display: inline-block !important;
        }
		</style>
		<script type="text/javascript">
      (function ($) {
        $('body').on('click', '.change-link', function (e) {
          e.preventDefault();
          $p = $(this).parents('.membership_form_element').first();
          $p.addClass('toggled-on');
          $p.find('input')[0].focus();
          return false;
        });
      }(jQuery));
		</script>
		<?php $hours = round( $time_to_expiry / 3600 );
		if ( $hours < 48 ) { ?>
			<div style="padding: 10px; background:#47a8f5; color: #FFF; margin: 0 0 20px;">
				Notice: this page will expire in <?php echo $hours; ?> hours. Please update your details before then.
			</div>
		<?php } ?>
		<form method="post" action="" class="techspace_signup">
			<input type="hidden" name="techspace_member_update" value="<?php echo htmlspecialchars( $hash ); ?>">
			<?php wp_nonce_field( 'techspace_update_member', 'techspace_submit' ); ?>

			<div class="membership_form_element"><label for="your_name">Your Name:</label><br/>
				<span class="change-field"><?php echo $this->hide( get_the_title( $member_id ) ); ?> <a href="#"
				                                                                                        class="change-link">change</a> </span>
				<input type="text" id="your_name" value="" tabindex="1" size="20" name="your_name" class="hidden-clicker"
				       placeholder="Enter New Value"/>
			</div>

			<div class="membership_form_element"><label for="membership_category">Membership Status:</label><br/>
				<p>Current Membership Expiry
					Date: <?php echo ! empty( $member_details['member_end'] ) ? date( 'Y-m-d', $member_details['member_end'] ) : 'No Paid Membership On File'; ?></p>
			</div>
			<div class="membership_form_element"><label for="membership_category">Desired Membership Type:</label><br/>
				<?php
				// TODO: fix this edit member thing
				$current_types        = wp_get_post_terms( $member_id, 'dtbaker_membership_type' );
				$available_categories = get_terms( 'dtbaker_membership_type', array(
					'hide_empty' => false,
				) );
				$types_order          = array( 94, 157 ); //93, 95,  96

				foreach ( $types_order as $type_id ) {
					foreach ( $available_categories as $available_category ) {
						if ( $available_category->term_id == $type_id ) {
							?>
							<div>
								<input type="radio"
								       id="membership_category_<?php echo (int) $available_category->term_id; ?>"
								       value="<?php echo (int) $available_category->term_id; ?>"
								       name="membership_category" <?php
								foreach ( $current_types as $type ) {
									if ( $type->term_id == $available_category->term_id ) {
										echo ' checked';
									}
								}
								?>/> <?php echo esc_html( $available_category->name ) . ' - ' . esc_html( $available_category->description ); ?>
							</div>
							<?php
						}
					}
				}

				?>
			</div>

			<?php foreach ( $this->update_details as $key => $val ) {
				$field = dtbaker_member::get_instance()->detail_fields[ $key ];
				if ( ! is_array( $field ) ) {
					$field = array( 'title' => $field );
				}
				?>
				<div class="membership_form_element"><label
						for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['title'] ); ?>:</label><br/>
					<?php if ( isset( $field['eg'] ) ) { ?>
						<small><?php echo $field['eg']; ?></small> <br/> <?php } ?>
					<span
						class="change-field"><?php echo $this->hide( ! empty( $member_details[ $key ] ) ? $member_details[ $key ] : '' ); ?>
						<a href="#" class="change-link">change</a> </span>
					<input type="text" id="<?php echo esc_attr( $key ); ?>" value="" size="20"
					       name="<?php echo esc_attr( $key ); ?>" placeholder="Enter New Value" class="hidden-clicker"/>

				</div>
			<?php } ?>

			<div class="membership_form_element"><label for="member_comments">Comments or Suggestions:</label><br/>
				<textarea id="member_comments" rows="7" cols="80" name="member_comments"></textarea>
			</div>


			<div class="membership_form_element"><input type="submit" value="Submit" tabindex="6" id="submit" name="submit"/>
			</div>

		</form>
		<?php
		return ob_get_clean();
	}

}

new TechSpace_Frontend_Submit();
