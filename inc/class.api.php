<?php


class TechSpace_API_Endpoint{

	/** Hook WordPress
	 *	@return void
	 */
	public function __construct(){
		add_filter('query_vars', array($this, 'add_query_vars'), 0);
		add_action('parse_request', array($this, 'sniff_requests'), 0);
		add_action('init', array($this, 'add_endpoint'), 0);
	}

	/** Add public query vars
	 *	@param array $vars List of current public query vars
	 *	@return array $vars
	 */
	public function add_query_vars($vars){
		$vars[] = '__api';
		$vars[] = 'rfid';
		$vars[] = 'access';
		$vars[] = 'doors';
		$vars[] = 'doorstatus';
		return $vars;
	}

	/** Add API Endpoint
	 *	This is where the magic happens - brush up on your regex skillz
	 *	@return void
	 */
	public function add_endpoint(){
		add_rewrite_rule('^api/rfid/?([0-9]+)?/?([a-zA-Z0-9-]+)?/?','index.php?__api=1&rfid=$matches[1]&access=$matches[2]','top');
		add_rewrite_rule('^api/doors/?([a-zA-Z0-9-]+)?/?','index.php?__api=1&doors=$matches[1]','top');
	}
	/**	Sniff Requests
	 *	This is where we hijack all API requests
	 * 	If $_GET['__api'] is set, we kill WP and serve up api results
	 *	@return die if API request
	 */
	public function sniff_requests(){
		global $wp;
		if(isset($wp->query_vars['__api'])){
			if(!empty($_POST['secret']) && $_POST['secret'] == get_option( 'techspace_membership_api_secret' )){
				if(!empty($wp->query_vars['access']) && $wp->query_vars['access'] == 'wifipassword') {
					// $wp->query_vars['getwifi']
					//getting wifi password from chicken.
					$wifipassword = get_option( 'techspace_membership_wifi_password' );
					$this->send_response($wifipassword);

				}else if(!empty($wp->query_vars['doors'])){
					$this->handle_door_status();
				}else if(empty($wp->query_vars['rfid']) && !empty($wp->query_vars['access']) && $wp->query_vars['access'] == 'all'){
					// handle the ALL api request. /api/rfid/all
					$this->handle_all();
				}else if($wp->query_vars['access'] == 'signup'){
					$this->handle_signup();
				}else{
					//mail('dtbaker@gmail.com','API Debug: '.$_SERVER['REQUEST_URI'],var_export($_REQUEST,true));
					// handle the CHECKIN api request. /api/rfid/12345/door-3
					$this->handle_request();
				}
			}else{
				mail('dtbaker@gmail.com','API Invalid Secret: '.$_SERVER['REQUEST_URI'],var_export($_REQUEST,true));
				$this->send_response('Invalid IP Address');
			}
			exit;
		}
	}

	protected function handle_signup(){
		global $wp;

		$rfid = $_POST['rfid'];
		$email = $_POST['email'];
		$name = !empty($_POST['name']) ? $_POST['name'] : false;

		if(!$rfid || !$email){
			$this->_send_slack_message("Failed to signup user for some reason");
			$this->send_response('-1');
		}

		// find a user with this email address already.

		$members = get_posts(array(
			'post_type' => 'dtbaker_membership',
			'post_status' => 'publish',
			'posts_per_page' => -1,
		));

		if($rfid != 'guest'){
			$valid_rfid = $this->get_rfid_entry($rfid);
		}else{
			$valid_rfid = false;
		}

		$member_manager = dtbaker_member::get_instance();

		foreach($members as $member){
			$member_details = $member_manager->get_details($member->ID);
			if(!empty($member_details['email']) && strtolower($member_details['email']) == strtolower($email)){
				// found a match yay!
				// are they submitting their name as well?
				if($name){
					wp_update_post(
						array(
							'ID' => $member->ID,
							'post_title' => $name,
						)
					);
				}
				// if we've got a new rfid key, add that to their account.
				// no . that would let someone access the system if they knew an existing admin email address.
				// just add the new RFID card and send a "do manual" message back to the signup form.
				$this->_send_slack_message("Existing member " . ( $name ? $name : $member_details->post_title ) . " wants to add a new RFID key to their account: ".$rfid);
				$member_manager->trigger_email( $member->ID );
				$this->send_response('existing');
			}
		}

		// if we're here we have to create a new member entry

		$member_id = wp_insert_post(array(
			'post_title' => $name ? $name : $email,
			'post_type' => 'dtbaker_membership',
			'post_status' => 'publish',
			'post_content' => 'Inserted by the API with email address '.$email.' from '.$_SERVER['REMOTE_ADDR'].' at '.current_time( 'mysql' ),
		));

		$this->_send_slack_message("New member signup ".$email." from chicken system. Using RFID key: ".$rfid);

		if($member_id){
			// created a member from signup. yes!
			update_post_meta( $member_id, 'membership_details_email', $email);
			if($valid_rfid){
				update_post_meta( $valid_rfid, 'rfid_details_member_id', $member_id);
			}
			$member_manager->trigger_email( $member_id );
			$this->send_response('success');
		}

		$this->send_response('fail');
	}

	public function get_rfid_entry($rfid_code){

		$rfid_cards = get_posts(array('post_type'=>'dtbaker_rfid','post_status'=> 'publish', 'suppress_filters' => false, 'posts_per_page'=>-1));
		$valid_rfid = false;
		foreach($rfid_cards as $rfid_card){
			if($rfid_card->post_title == $rfid_code){
				$valid_rfid = $rfid_card->ID;
			}
		}
		if(!$valid_rfid){
			// create one.
			global $wp;
			$valid_rfid = wp_insert_post(array(
				'post_title' => $rfid_code,
				'post_type' => 'dtbaker_rfid',
				'post_status' => 'publish',
				'post_content' => 'Inserted by the API by a request from '.(!empty($wp->query_vars['access']) ? $wp->query_vars['access'] : 'Unknown').' '.$_SERVER['REMOTE_ADDR'].' at '.current_time( 'mysql' ),
			));
		}

		return $valid_rfid;

	}

	private function _get_device_name($device){
		$device_name = false;
		$available_access     = get_terms( 'dtbaker_membership_access', array(
			'hide_empty' => false,
		) );
		foreach ( $available_access as $available_acces ) {
			if ( $device && $available_acces->slug == $device ) {
				$device_name = $available_acces->name;
			}
		}
		return $device_name;
	}

	/** Handle Requests
	 *	@return void
	 */
	protected function handle_door_status(){
		global $wp;

		$door_name = $wp->query_vars['doors'];
		if(!$door_name)
			$this->send_response('-1');

		$door_status = $_POST['doorstatus'];

		$device_name = $this->_get_device_name($door_name);

		if($door_name=='ci')return;
		$message = "Device: `" . $door_name . '` = `' . $door_status .'`' . ( $device_name ? ' ('.$device_name.')' : '');

		$this->_send_slack_message($message, 'logs');

	}
	/** Handle Requests
	 *	@return void
	 */
	protected function handle_request(){
		global $wp;

		$rfid = $wp->query_vars['rfid'];
		if(!$rfid || strlen($rfid) < 5)
			$this->send_response('-1');

		$access = $wp->query_vars['access'];
		$valid_access_term_id = false;
		$valid_access_term_name = false;
		$valid_access_object = false;
		$available_access     = get_terms( 'dtbaker_membership_access', array(
			'hide_empty' => false,
		) );
		foreach ( $available_access as $available_acces ) {
			if ( $access && $available_acces->slug == $access ) {
				$valid_access_term_id = $available_acces->term_id;
				$valid_access_term_name = $available_acces->name;
				$valid_access_object = $available_acces;
			}
		}
		if ( ! $access || ! $valid_access_term_id ) {
			$this->send_response( '-3' );
		}

		$valid_rfid = $this->get_rfid_entry($rfid);


		if($valid_rfid){
			//echo "Found a RFID card with id $valid_rfid";
			// we have a valid RFID card.

			update_post_meta($valid_rfid,'rfid_details_last_access', current_time( 'timestamp' ));

			// see if this RFID key has a member associated with it.
			$member_id = get_post_meta($valid_rfid, 'rfid_details_member_id', true);
			$member_access = false;
			if($member_id){
				$member_access = get_post($member_id);
				if ( ! $member_access->ID ) {
					$member_access = false;
				}
			}
			if($member_access) {



				if(!empty($_POST['answer_question'])){

					switch($_POST['answer_question']){
						case 'slack':

							$response = isset($_POST['slackresponse']) ? $_POST['slackresponse'] : '';
							$slackanswers = array(
								'Yes Please',
								'No Thanks',
								'Already On',
							);
							switch($response){
								case 'Yes Please':


									$member_manager = dtbaker_member::get_instance();
									$member_details = $member_manager->get_details( $member_access->ID );

									$setting = trim( get_option( 'techspace_membership_slack_real_token' ) );
									if($setting) {
										$ch   = curl_init( "https://gctechspace.slack.com/api/users.admin.invite" );
										$data = http_build_query( [
											"token"      => $setting,
											"email"      => $member_details['email'],
											"set_active" => 'true',
											"_attempts"  => '1',
											//"as_user" => "true"
										] );
										curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
										curl_setopt( $ch, CURLOPT_TIMEOUT, 4 );
										curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
										curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
										curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
										curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
										$response = curl_exec( $ch );
										mail('dtbaker@gmail.com','Slack Invite: '.$member_details['email'],var_export($data,true).var_export($response,true));
										curl_close( $ch );

									}


									break;
								case 'No Thanks':

									break;
								case 'Already On':

									break;
							}


							break;
					}

				}else if($access == 'do_checkin' || !empty($_POST['post_checkin'])){
					// return full member details for checkin:
					$api_result = $this->get_member_api_result($member_access->ID);
					$this->send_checkin_notice($member_access->ID, $valid_rfid);
				}else{
					// return expiry days for individual log:
					$member_manager = dtbaker_member::get_instance();
					$member_details = $member_manager->get_details( $member_access->ID );
					$member_manager->trigger_email( $member_access->ID );
					$api_result     = $member_details['expiry_days'];
					// check for a valid day/time
                    $extra_details = '';
                    if(!empty($member_details['valid_times'])){
                        $bits = explode('|',strtolower($member_details['valid_times']));
                        $today = strtolower(current_time('D'));
                        $hour = (int)current_time('G');
                        $valid_access = false;
                        foreach($bits as $bit){
                            $valid_day = substr($bit, 0, 3);
                            $valid_time = explode('-', substr($bit, 3));
                            if($valid_day == $today && $hour >= (int)$valid_time[0] && $hour < (int)$valid_time[1] ){
                                $valid_access = true;
                                $extra_details .= 'Access allowed as per time rules: '.$valid_day.' between '.$valid_time[0].' and '.$valid_time[1].'.';
                            }
                        }
                        if(!$valid_access){
                            $extra_details .= 'No access as per time rule '.$member_details['valid_times'].'.';
                            $api_result = '-5';
                        }
                    }

					$this->send_slack_alert($member_access->ID, $valid_access_object, $valid_rfid, $api_result, $extra_details);
				}
			}else{
				$api_result = -2; // no member for this card yet.
				$this->send_slack_alert(false, $valid_access_object, $valid_rfid, $api_result);
			}
			// log a history for this card

			$rfid_log_data = array(
				'member_id' => $member_access ? $member_access->ID : 0,
				'access_time' => current_time( 'timestamp' ),
				'rfid_id' => $valid_rfid,
				'access_id' => $valid_access_term_id,
				'ip_address' => $_SERVER['REMOTE_ADDR'],
				'api_result' => $api_result,
			);
			global $wpdb;
			$wpdb->insert($wpdb->prefix.'ts_rfid', $rfid_log_data);
			$rfid_history_event = $wpdb->insert_id;
			if($rfid_history_event){
				// only send possibly valid result after we've logged it in our database.
				// return number of days until membership expires
                // or this will be 0 if not access to the time.
				$this->send_response($api_result);
			}
			$this->send_response('-3');
		}
		$this->send_response('0');
	}

	public function send_checkin_notice($member_id, $rfid_id) {

		$rfid_details = get_post( $rfid_id );

		if ( $member_id ) {
			$member_details = get_post( $member_id );

			$member_notifications = get_post_meta($member_id,'membership_details_notifications',true);

			if($member_notifications == 1){
				// don't send public notifications. still send log below.
				$public_message = "Anonymous just checked in and will be here for: ".$_REQUEST['time'];
			}else{
				$public_message = "Member " . $member_details->post_title . " just checked in and will be here for: ".$_REQUEST['time'];
			}

			$this->_send_slack_message($public_message, 'check-in', 'Door Bot');

		}

	}

	public function send_slack_alert($member_id, $access_point, $rfid_id, $api_result, $extra_details = ''){


		$rfid_details = get_post($rfid_id);

		if($member_id){
			$member_details = get_post($member_id);

			$message = "Device: `" . ( $access_point ? $access_point->slug : 'unknown' ) . "` = " .$member_details->post_title.", " .$rfid_details->post_title . ( $access_point ? ", ".$access_point->name : '' ) . ( ', ' . ( $api_result > 0 ? 'Granted' : 'DENIED') ) . " ($api_result" . ( $extra_details ? ': ' . $extra_details : '') .')';

		}else{

			$message = "Device: `" . ( $access_point ? $access_point->slug : 'unknown' ) . "` = Unknown RFID card ".$rfid_details->post_title. ( $access_point ? " on ".$access_point->name : '' );
		}

		$this->_send_slack_message($message);


	}

	private function _send_slack_message($message, $channel = 'logs', $botname = 'RFID Bot'){

		$setting = trim( get_option( 'techspace_membership_slack_api' ) );
		if($setting) {
			$ch   = curl_init( "https://slack.com/api/chat.postMessage" );
			$data = http_build_query( [
				"token"    => $setting,
				"channel"  => $channel,
				"text"     => $message,
				"username" => $botname,
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

	/** Handle Requests
	 *	@return void
	 */
	protected function handle_all(){
		global $wp;

		$result = array();

		$members = get_posts(array(
			'post_type' => 'dtbaker_membership',
			'post_status' => 'publish',
			'posts_per_page' => -1,
		));

		foreach($members as $member){
			$result[] = $this->get_member_api_result($member->ID);
		}

		$this->send_response(json_encode($result));

	}

	public function get_member_api_result($member_id){
		$member_manager = dtbaker_member::get_instance();
		$member_details = $member_manager->get_details($member_id);
		$rfid_codes = array();
		$rfid_keys = get_posts(array(
			'post_type' => 'dtbaker_rfid',
			'post_status' => 'publish',
			'meta_key'   => 'rfid_details_member_id',
			'meta_value' => $member_id,
		));
		if($rfid_keys) {
			foreach($rfid_keys as $rfid_key){
				$rfid_codes[] = $rfid_key->post_title;
			}
		}
		$access = array();
		foreach(wp_get_post_terms($member_id, 'dtbaker_membership_access') as $term){
			$access[$term->slug] = $term->name;
		}
		$return = array(
			'member_id' => $member_id,
			'member_name' => get_the_title($member_id),
			'member_email' => strtolower($member_details['email']),
			'membership_expiry_days' => $member_details['expiry_days'],
			'rfid' => $rfid_codes,
			'access' => $access,
			'valid_times' => $member_details['valid_times'],
			'slack' => $member_details['slack'],
			'questions' => array(),
		);

		if(empty($member_details['slack'])){
			$return['questions']['slack'] = array(
				'question' => 'What is your Slack Chat Username?',
				'response' => 'text',
			);
		}

		return $return;

	}

	/** Response Handler
	 *	This sends a JSON response to the browser
	 */
	protected function send_response($msg){
		//header('content-type: text/plain');
		ob_end_clean();
		echo $msg;
		exit;
	}
}
new TechSpace_API_Endpoint();
