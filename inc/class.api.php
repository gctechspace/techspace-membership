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
		return $vars;
	}

	/** Add API Endpoint
	 *	This is where the magic happens - brush up on your regex skillz
	 *	@return void
	 */
	public function add_endpoint(){
		add_rewrite_rule('^api/rfid/?([0-9]+)?/?([a-zA-Z0-9-]+)?/?','index.php?__api=1&rfid=$matches[1]&access=$matches[2]','top');
	}
	/**	Sniff Requests
	 *	This is where we hijack all API requests
	 * 	If $_GET['__api'] is set, we kill WP and serve up api results
	 *	@return die if API request
	 */
	public function sniff_requests(){
		global $wp;
		if(isset($wp->query_vars['__api'])){
			mail('dtbaker@gmail.com','API Debug: '.$_SERVER['REQUEST_URI'],var_export($_REQUEST,true));
			if(!empty($_POST['secret']) && $_POST['secret'] == get_option( 'techspace_membership_api_secret' )){
				if(empty($wp->query_vars['rfid']) && !empty($wp->query_vars['access']) && $wp->query_vars['access'] == 'all'){
					// handle the ALL api request. /api/rfid/all
					$this->handle_all();
				}else{
					// handle the CHECKIN api request. /api/rfid/12345/door-3
					$this->handle_request();
				}
			}else{
				$this->send_response('Invalid Secret');
			}
			exit;
		}
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
		if($access != 'checkin') {
			$available_access     = get_terms( 'dtbaker_membership_access', array(
				'hide_empty' => false,
			) );
			foreach ( $available_access as $available_acces ) {
				if ( $access && $available_acces->slug == $access ) {
					$valid_access_term_id = $available_acces->term_id;
				}
			}
			if ( ! $access || ! $valid_access_term_id ) {
				$this->send_response( '-3' );
			}
		}else{
			$valid_access_term_id = -1; // for checkin in log.
		}

		$rfid_cards = get_posts(array('post_type'=>'dtbaker_rfid','post_status'=> 'publish', 'suppress_filters' => false, 'posts_per_page'=>-1));
		$valid_rfid = false;
		foreach($rfid_cards as $rfid_card){
			if($rfid_card->post_title == $rfid){
				$valid_rfid = $rfid_card->ID;
			}
		}
		if(!$valid_rfid){
			// create one.
			$valid_rfid = wp_insert_post(array(
				'post_title' => $rfid,
				'post_type' => 'dtbaker_rfid',
				'post_status' => 'publish',
				'post_content' => 'Inserted by the API by a request from '.$access.' '.$_SERVER['REMOTE_ADDR'].' at '.current_time( 'mysql' ),
			));
		}

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
				if($access == 'checkin'){
					// return full member details for checkin:
					$api_result = $this->get_member_api_result($member_access->ID);
				}else{
					// return expiry days for individual log:
					$member_manager = dtbaker_member::get_instance();
					$member_details = $member_manager->get_details( $member_access->ID );
					$api_result     = $member_details['expiry_days'];
				}
				$this->send_slack_alert($member_access->ID, $access);
			}else{
				$api_result = -2; // no member for this card yet.
				$this->send_slack_alert(false, $access);
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
				$this->send_response($api_result);
			}
			$this->send_response('-3');
		}
		$this->send_response('0');
	}


	public function send_slack_alert($member_id, $access_point){

		$setting = trim( get_option( 'techspace_membership_slack_api' ) );
		if(strlen($setting)) {

			if($member_id){
				$member_details = get_post($member_id);
				$member_notifications = get_post_meta($member_id,'membership_details_notifications',true);
				if($member_notifications == 1){
					return;
				}else{
					$message = "Member ".$member_details->post_title." just swiped against ".$access_point;
				}
			}else{
				$message = "Unknown RFID card just swiped against ".$access_point;
			}
			$ch   = curl_init( "https://slack.com/api/chat.postMessage" );
			$data = http_build_query( [
				"token"    => $setting,
				"channel"  => '#alerts',
				"text"     => $message,
				"username" => "RFID Bot",
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
		return array(
			'member_name' => get_the_title($member_id),
			'membership_expiry_days' => $member_details['expiry_days'],
			'xero_contact_id' => $member_details['xero_id'],
			//'xero_contact_details' => !empty($member_details['xero_cache']) ? $member_details['xero_cache'] : false,
			'rfid' => $rfid_codes,
			'access' => $access,
		);
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