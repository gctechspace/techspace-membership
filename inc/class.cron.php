<?php

class dtbaker_member_cron{


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

	public function admin_menu(){

		$page = add_submenu_page('edit.php?post_type=dtbaker_membership', __( 'Check Updates' ), __( 'Check Updates' ), 'edit_pages',  'member_cron' , array($this, 'member_cron'));

	}

	public function member_cron(){
		?>
		<h3>Cron Job:</h3>
		<pre><?php $this->run_cron_job(true);?></pre>
		<?php

	}

	public function run_cron_job($debug = false){
		$member_manager = dtbaker_member::get_instance();
		$member_types = $member_manager->get_member_types();

		// loop over all members.
		// grab an updated list of invoices for those members.
		// update membership expiry date for any paid invoices.
		// check its association to a member.
		$members = get_posts(array(
			'post_type' => 'dtbaker_membership',
			'post_status' => 'publish',
			'posts_per_page' => -1,
		));
		ini_set('display_errors',true);
		ini_set('error_reporting',E_ALL);

		foreach($members as $member){
			// check for any new invoices for this member.
			$membership_details = $member_manager->get_details($member->ID);
			if($debug){
				echo "\nChecking status for member (".$member->ID.") ".$member->post_title."\n";
				if($membership_details['member_start'])echo " Start: ".date('Y-m-d',$membership_details['member_start']);
				if($membership_details['member_end'])echo " End: ".date('Y-m-d',$membership_details['member_end']) . "\n";
			}

			if(!empty($membership_details['xero_id'])) {
				$invoices = dtbaker_xero::get_instance()->get_contact_invoices( $membership_details['xero_id'], true );

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
					foreach($invoices as $invoice_id => $invoice){
						if(!empty($invoice['paid_time']) && $invoice['status'] == 'PAID'){
							echo " - Found a $".$invoice['total']." paid invoice (paid on ". date('Y-m-d',$invoice['paid_time'])."): $invoice_id \n";
							// check how many days this invoice payment will get the user.
							foreach($member_types as $member_type){
								if($member_type['price'] == $invoice['total']){
									if($debug)echo " -- this invoice is for membership type: ".$member_type['name']." for ".$member_type['months']." months. \n";
									$new_member_date = strtotime('+'.$member_type['months'].' months',$invoice['paid_time']);
									if($debug)echo " -- this invoice will extend the member date until " . date('Y-m-d',$new_member_date) ." \n";
									if(empty($membership_details['member_start'])){
										if($debug)echo " -- will set the membership start date to " . date('Y-m-d',$invoice['paid_time']) ." \n";
										$membership_details['member_start'] = $invoice['paid_time'];
										update_post_meta( $member->ID, 'membership_details_member_start', $membership_details['member_start'] );
									}
									if(empty($membership_details['member_end']) || $membership_details['member_end'] < $new_member_date){
										if($debug)echo " -- will set the membership end date to " . date('Y-m-d',$new_member_date) ." \n";
										$membership_details['member_end'] = $new_member_date;
										update_post_meta( $member->ID, 'membership_details_member_end', $membership_details['member_end'] );
									}

								}
							}
						}
					}
				}else{
					if($debug){
						echo "No invoices found \n";
					}
				}



				if(!empty($membership_details['member_end']) && $membership_details['member_end'] < strtotime('+10 days')){
					// check if expiry is coming up
					if($debug)echo " - membership is about to expire on ".date('Y-m-d',$membership_details['member_end'])."\n";
					// check if we have already sent an invoice for this member date.
					$found_invoice = false;
					if($invoices){
						foreach($invoices as $invoice){
							if(date('Y-m-d',$invoice['time']) == date('Y-m-d',$membership_details['member_end'])){
								if($debug)echo " --- found an invoice for this renewal already! not sending a new one \n";
								$found_invoice = true;
							}
						}
					}
					if(!$found_invoice){
						if($debug)echo " --- Generating a new invoice for this renewal! \n";

						// what membership type do they have.
						$membership_type = false;
						foreach(wp_get_post_terms($member->ID, 'dtbaker_membership_type') as $term){
							if($term->slug && preg_match('#(\d+)-months#',$term->slug,$matches)){
								$membership_type = $matches[1];
							}
						}
						if(!$membership_type){
							if($debug)echo " --- Unknown membership type. Please select one. \n";
						}else {
							if ( $debug ) {
								echo " --- Membership type: " . $membership_type . " months \n";
							}
							foreach($member_types as $member_type) {
								if($member_type['months'] == $membership_type) {
									$invoice_details = array(
										'contact_id'  => $membership_details['xero_id'],
										'date'        => date( 'Y-m-d', $membership_details['member_end'] ),
										'due_date'    => date( 'Y-m-d', strtotime( '+7 days', $membership_details['member_end'] ) ),
										'item_code'   => $member_type['code'],
										'description' => $member_type['name'],
									);
									if ( $debug ) {
										echo " --- Creating a new invoice: \n";
										print_r($invoice_details);
									}
									wp_mail("dtbaker@gmail.com","TechSpace Membership Renewal (".$member->post_title.")","Invoice \n ".var_export($invoice_details,true). " Link: ".get_edit_post_link($member->ID));
									$new_invoice = dtbaker_xero::get_instance()->create_invoice( $invoice_details );
									if ( $new_invoice ) {
										// reload invoice cache for member
										$invoices = dtbaker_xero::get_instance()->get_contact_invoices( $membership_details['xero_id'], true );
										$member_manager->cache_member_invoices( $member->ID, $invoices );
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

dtbaker_member_cron::get_instance()->init();