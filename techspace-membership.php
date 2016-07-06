<?php

/**
 * Plugin Name: Techspace Membership
 * Description: Membership management for techspace
 * Plugin URI: http://dtbaker.net
 * Version: 1.0.1
 * Author: dtbaker
 * Author URI: http://dtbaker.net
 * Text Domain: techspace-membership
 */


class DtbakerMembershipManager {
	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public $membership_detail_fields = array();
	public $social_icons = array();

	public function init() {
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_css' ) );
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_css' ) );
		add_filter( 'manage_dtbaker_membership_posts_columns', array( $this, 'manage_dtbaker_membership_posts_columns' ) );
		add_action( 'manage_dtbaker_membership_posts_custom_column' , array( $this, 'manage_dtbaker_membership_posts_custom_column' ), 10, 2 );
		add_filter( 'manage_dtbaker_rfid_posts_columns', array( $this, 'manage_dtbaker_rfid_posts_columns' ) );
		add_action( 'manage_dtbaker_rfid_posts_custom_column' , array( $this, 'manage_dtbaker_rfid_posts_custom_column' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		$this->membership_detail_fields = apply_filters('dtbaker_membership_detail_fields', array(
			'role' => 'Role (member, committee, etc..)',
			'rfid' => 'RFID Key',
			'xero_id' => 'Xero Contact',
		));

		$this->social_icons = apply_filters('dtbaker_membership_icons', array(
			'facebook' => 'Facebook',
			'twitter' => 'Twitter',
			'google-plus' => 'Google+',
			'envelope' => 'Email',
		));
	}

	public function admin_menu(){
		add_options_page(
			'TechSpace Member',
			'TechSpace Member',
			'manage_options',
			'techspace-membership-plugin',
			array( $this, 'menu_settings_callback' )
		);
	}

	public function menu_settings_callback(){
		if ( ! isset( $_REQUEST['settings-updated'] ) )
			$_REQUEST['settings-updated'] = false;
		?>
		<div class="wrap">
			<?php if ( false !== $_REQUEST['settings-updated'] ) : ?>
				<div class="updated fade"><p><strong><?php _e( 'Settings saved!', 'wporg' ); ?></strong></p></div>
			<?php endif; ?>
			<div id="poststuff">
				<div id="post-body">
					<div id="post-body-content">
						<form method="post" action="options.php">
							<?php
							settings_fields( 'techspace_member_settings' );
							do_settings_sections( 'techspace_member_settings' );
							submit_button();
							?>

						</form>
					</div> <!-- end post-body-content -->
				</div> <!-- end post-body -->
			</div> <!-- end poststuff -->
		</div>
		<?php
	}

	public function manage_dtbaker_membership_posts_columns( $columns ){
		unset( $columns['author'] );
		unset( $columns['date'] );
		$columns['rfid'] = __( 'RFID' );
		$columns['xero_id'] = __( 'Xero Contact' );
		$columns['paid'] = __( 'Total Paid' );
		$columns['membership_start'] = __( 'Membership Start' );
		$columns['membership_expiry'] = __( 'Membership Expiry' );
		return $columns;
	}

	public function manage_dtbaker_membership_posts_custom_column( $column, $post_id ){
		$membership_details = $this->get_member_details($post_id);
		switch($column){
			case 'rfid':
				// obfuscate RFID key a bit:
				if(!empty($membership_details['rfid'])){
					$rfid = get_post($membership_details['rfid']);
					$rfid_code = $rfid->post_title;
					echo str_pad(substr($rfid_code,0,5), strlen($rfid_code), '*', STR_PAD_RIGHT);
				}
				break;
			case 'xero_id':
				if(!empty($membership_details['xero_cache'])){
					echo esc_html(implode( ' / ', $membership_details['xero_cache']));
				}
				break;
			case 'paid':
				echo 'When they are paid til';
				break;
			case 'membership_start':
				echo date('Y-m-d');
				break;
			case 'membership_expiry':
				echo date('Y-m-d');

		}
	}

	public function manage_dtbaker_rfid_posts_columns( $columns ){
		unset( $columns['author'] );
		unset( $columns['date'] );
		$columns['member_id'] = __( 'Member' );
		$columns['status'] = __( 'Status' );
		return $columns;
	}

	public function manage_dtbaker_rfid_posts_custom_column( $column, $post_id ){
		switch($column){
			case 'member_id':
				echo 'member';
				break;
			case 'status':
				echo 'status';
				break;

		}
	}

	public function widgets_init(){

	}

	public function settings_init() {
		add_settings_section(
			'techspace_membership_section',
			'TechSpace Membership Settings',
			array( $this, 'settings_section_callback' ),
			'techspace_member_settings'
		);

		add_settings_field(
			'techspace_membership_private_key',
			'Private Key',
			array( $this, 'settings_callback_private_key' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_private_key' );

		add_settings_field(
			'techspace_membership_public_key',
			'Public Key',
			array( $this, 'settings_callback_public_key' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_public_key' );

		add_settings_field(
			'techspace_membership_consumer_key',
			'Consumer Key',
			array( $this, 'settings_callback_consumer_key' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_consumer_key' );

		add_settings_field(
			'techspace_membership_secret_key',
			'Secret Key',
			array( $this, 'settings_callback_secret_key' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_secret_key' );
	}

	public function settings_section_callback(){
		echo '<p>Please set the TechSpace membership settings below:</p>';
	}
	public function settings_callback_private_key(){
		$setting = esc_attr( get_option( 'techspace_membership_private_key' ) );
		?> <textarea name="techspace_membership_private_key" placeholder="<?php echo strlen($setting) ? 'Already Saved' : 'Paste New Private Key Here';?>"></textarea> <?php
	}
	public function settings_callback_public_key(){
		$setting = esc_attr( get_option( 'techspace_membership_public_key' ) );
		?> <textarea name="techspace_membership_public_key" placeholder="<?php echo strlen($setting) ? 'Already Saved' : 'Paste New Public Key Here';?>"></textarea> <?php
	}
	public function settings_callback_consumer_key(){
		$setting = esc_attr( get_option( 'techspace_membership_consumer_key' ) );
		?> <input type="text" name="techspace_membership_consumer_key" placeholder="<?php echo strlen($setting) ? 'Already Saved' : 'Paste New consumer Key Here';?>"></input> <?php
	}
	public function settings_callback_secret_key(){
		$setting = esc_attr( get_option( 'techspace_membership_secret_key' ) );
		?> <input type="text" name="techspace_membership_secret_key" placeholder="<?php echo strlen($setting) ? 'Already Saved' : 'Paste New secret Key Here';?>"></input> <?php
	}

	public function frontend_css() {
		wp_register_style( 'dtbaker_membership_frontend', plugins_url( 'css/membership-frontend.css', __FILE__ ) , false, '1.0.1' );
		wp_enqueue_style( 'dtbaker_membership_frontend' );
	}
	public function admin_css() {
		wp_register_style( 'dtbaker_membership_admin', plugins_url( 'css/membership-admin.css', __FILE__ ) , false, '1.0.1' );
		wp_enqueue_style( 'dtbaker_membership_admin' );
	}

	private function _xero_api_init(){

		require 'XeroOAuth-PHP/lib/XeroOAuth.php';

		define ( "XRO_APP_TYPE", "Private" );
		$useragent = "GCTechSpace WordPress Plugin";

		$signatures = array (
			'consumer_key' => get_option( 'techspace_membership_consumer_key' ),
			'shared_secret' => get_option( 'techspace_membership_secret_key' ),
			'public_key' => get_option( 'techspace_membership_public_key' ),
			'private_key' => get_option( 'techspace_membership_private_key' ),
			// API versions
			'core_version' => '2.0',
			'payroll_version' => '1.0',
			'file_version' => '1.0',
		);

		$XeroOAuth = new XeroOAuth ( array_merge ( array (
			'application_type' => XRO_APP_TYPE,
			'oauth_callback' => 'oob', // not needed for private app
			'user_agent' => $useragent
		), $signatures ) );

		$XeroOAuth->config ['access_token'] = $XeroOAuth->config ['consumer_key'];
		$XeroOAuth->config ['access_token_secret'] = $XeroOAuth->config ['shared_secret'];
		return $XeroOAuth;
	}

	private function _xero_get_all_contacts( $force = false ){
		$XeroOAuth = $this->_xero_api_init();
		$all_contacts = get_transient( 'techspace_xero_contacts' );
		if(!$force && $all_contacts && is_array($all_contacts)){
			return $all_contacts;
		}else{
			$all_contacts = array();
		}
		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array());
		if ($XeroOAuth->response['code'] == 200) {
			$contacts = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			if(count($contacts->Contacts[0]) > 1){
				foreach($contacts->Contacts[0] as $contact){
					$contact_id = (string)$contact->ContactID;
					$all_contacts[$contact_id] = array(
						'name' => !empty($contact->Name) ? (string)$contact->Name : '',
						'email' => !empty($contact->EmailAddress) ? (string)$contact->EmailAddress : '',
					);
				}
			}
		} else {
			// log error?
		}
		if($all_contacts){
			// sort by name.
			uasort($all_contacts, function($a, $b) {
				return strnatcasecmp($a['name'], $b['name']);
			});
			set_transient( 'techspace_xero_contacts', $all_contacts, 12 * HOUR_IN_SECONDS );
		}
		return $all_contacts;
	}

	public function get_member_details($post_id){
		$membership_details = array();
		foreach($this->membership_detail_fields as $key=>$val){
			$membership_details[$key] = get_post_meta( $post_id, 'membership_details_'.$key, true );
		}
		$membership_details['xero_cache'] = get_post_meta( $post_id, 'membership_details_xero_cache', true );
		return $membership_details;
	}
	public function meta_box_price_callback( $post ) {

		wp_nonce_field( 'dtbaker_membership_metabox_nonce', 'dtbaker_membership_metabox_nonce' );

		$membership_details = $this->get_member_details($post->ID);
		foreach($this->membership_detail_fields as $field_id => $field_title){
			?>
			<p>
				<label for="member_detail_<?php echo esc_attr( $field_id );?>"><?php echo esc_html($field_title); ?></label>
				<?php switch($field_id){
					case 'rfid':
						$this->generate_post_select( 'membership_details[rfid]', 'dtbaker_rfid', $membership_details['rfid']);
						break;
					case 'xero_id':
						// lookup xero contacts from api. uses the ContactID field from Xero
						$contacts = $this->_xero_get_all_contacts( isset($_REQUEST['xero_refresh']) );
						if(empty($membership_details['xero_id']))$membership_details['xero_id'] = 0;
						?>
						<select name="membership_details[xero_id]">
							<option value=""> - Please Select - </option>
							<?php if(is_array($contacts) && count($contacts)){
								foreach($contacts as $contact_id => $contact){ ?>
									<option value="<?php echo esc_attr($contact_id);?>" <?php echo selected($membership_details['xero_id'], $contact_id);?>><?php echo esc_attr($contact['name']);?></option>
								<?php }
							}else{
								?>
								<option value=""> failed to get xero listing </option><?php
							} ?>
						</select>
						<a href="<?php echo add_query_arg('xero_refresh', 1, get_edit_post_link($post->ID));?>">(refresh xero list)</a>
						<?php
						break;
					default:
						?>
						<input type="text" name="membership_details[<?php echo esc_attr( $field_id );?>]" id="member_detail_<?php echo esc_attr( $field_id );?>" value="<?php echo esc_attr( isset($membership_details[$field_id]) ? $membership_details[$field_id] : '' ); ?>">
						<?php
				}
				?>
			</p>
			<?php
		}

		$contact = get_post_meta( $post->ID, 'membership_contact', true );
		if( !$contact || !is_array($contact) ){
			$contact = array();
		}
		?>
		<p>(optional) Public Contact Details for Website:</p>
		<?php
		foreach($this->social_icons as $icon_name => $icon_title){
			?>
			<p>
				<label for="contact_<?php echo esc_attr( $icon_name );?>"><?php printf( __( 'Contact: %s' ) , $icon_title ); ?></label>
				<input type="text" name="membership_contact[<?php echo esc_attr( $icon_name );?>]" id="contact_<?php echo esc_attr( $icon_name );?>" value="<?php echo esc_attr( isset($contact[$icon_name]) ? $contact[$icon_name] : '' ); ?>">
			</p>
			<?php
		}
	}


	public function add_meta_box() {

		$screens = array( 'dtbaker_membership' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'dtbaker_membership_page_meta_price',
				__( 'Member Details' ),
				array( $this, 'meta_box_price_callback' ),
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
			if(!empty($membership_details['xero_id'])){
				// cache local xero details for this member
				$contacts = $this->_xero_get_all_contacts( );
				if(isset($contacts[$membership_details['xero_id']])){
					update_post_meta( $post_id, 'membership_details_xero_cache', $contacts[$membership_details['xero_id']]);
				}else{
					unset($membership_details['xero_id']);
				}
			}
			foreach($this->membership_detail_fields as $key=>$val){
				update_post_meta( $post_id, 'membership_details_'.$key, isset($membership_details[$key]) ? $membership_details[$key] : '');
			}

		}
		if ( isset( $_POST['membership_contact'] ) && is_array( $_POST['membership_contact'] ) ) {
			update_post_meta( $post_id, 'membership_contact', $_POST['membership_contact'] );
		}

		update_post_meta( $post_id, 'membership_contact_html', $this->membership_contact_html($post_id) );

	}

	// we need this in the post_meta field so visual composer can output the single html field from a meta grid option.
	public function membership_contact_html($post_id){
		ob_start();
		$contact = get_post_meta( $post_id, 'membership_contact', true );
		if( !$contact || !is_array($contact) ){
			$contact = array();
		}
		if($contact) {

			foreach ( $this->social_icons as $icon_name => $icon_title ) {
				if ( isset( $contact[ $icon_name ] ) ) {
					?>

					<a href="<?php echo esc_attr( $contact[ $icon_name ] ); ?>" target="_blank"><i
							class="fa fa-<?php echo esc_attr( $icon_name ); ?>"></i></a>
					<?php
				}
			}
		}
		return ob_get_clean();

	}

	public function generate_post_select($select_id, $post_type, $selected = 0) {
		$post_type_object = get_post_type_object($post_type);
		$label = $post_type_object->label;
		$posts = get_posts(array('post_type'=> $post_type, 'post_status'=> 'publish', 'suppress_filters' => false, 'posts_per_page'=>-1));
		echo '<select name="'. $select_id .'" id="'.$select_id.'">';
		echo '<option value = "" > - Select '.$label.' - </option>';
		foreach ($posts as $post) {
			echo '<option value="', $post->ID, '"', $selected == $post->ID ? ' selected="selected"' : '', '>', $post->post_title, '</option>';
		}
		echo '</select>';
	}


	public function register_custom_post_type() {

		$labels = array(
			'name'              => _x( 'Access', 'taxonomy general name', 'techspace-membership' ),
			'singular_name'     => _x( 'Access', 'taxonomy singular name', 'techspace-membership' ),
			'search_items'      => __( 'Search Access', 'techspace-membership' ),
			'all_items'         => __( 'All Access', 'techspace-membership' ),
			'parent_item'       => __( 'Parent Access', 'techspace-membership' ),
			'parent_item_colon' => __( 'Parent Access:', 'techspace-membership' ),
			'edit_item'         => __( 'Edit Access', 'techspace-membership' ),
			'update_item'       => __( 'Update Access', 'techspace-membership' ),
			'add_new_item'      => __( 'Add New Access', 'techspace-membership' ),
			'new_item_name'     => __( 'New Access Name', 'techspace-membership' ),
			'menu_name'         => __( 'Access', 'techspace-membership' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'member-access' ),
		);

		register_taxonomy( 'dtbaker_membership_access', array( 'dtbaker_membership', 'dtbaker_rfid_log' ), $args );

		
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
			'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
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

		
		$labels = array(
			'name'               => 'RFID Keys',
			'singular_name'      => 'RFID Key',
			'menu_name'          => 'RFID',
			'parent_item_colon'  => 'Parent RFID:',
			'all_items'          => 'RFID Keys',
			'view_item'          => 'View RFID',
			'add_new_item'       => 'Add New RFID',
			'add_new'            => 'New RFID Key',
			'edit_item'          => 'Edit RFID',
			'update_item'        => 'Update RFID',
			'search_items'       => 'Search RFIDs',
			'not_found'          => 'No RFIDs found',
			'not_found_in_trash' => 'No RFIDs found in Trash',
		);

		$rewrite = array(
			'slug'       => 'rfid',
			'with_front' => false,
			'pages'      => true,
			'feeds'      => true,
		);
		$args    = array(
			'label'               => 'dtbaker_rfid_item',
			'description'         => 'RFID Keys',
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
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

		register_post_type( 'dtbaker_rfid', $args );
		
		$labels = array(
			'name'               => 'RFID Log History',
			'singular_name'      => 'RFID Log',
			'menu_name'          => 'RFIDs',
			'parent_item_colon'  => 'Parent RFID:',
			'all_items'          => 'RFID History',
			'view_item'          => 'View RFID',
			'add_new_item'       => 'Add New RFID',
			'add_new'            => 'New RFID Log Entry',
			'edit_item'          => 'Edit RFID',
			'update_item'        => 'Update RFID',
			'search_items'       => 'Search RFIDs',
			'not_found'          => 'No RFIDs found',
			'not_found_in_trash' => 'No RFIDs found in Trash',
		);

		$rewrite = array(
			'slug'       => 'rfid-log',
			'with_front' => false,
			'pages'      => true,
			'feeds'      => true,
		);
		$args    = array(
			'label'               => 'dtbaker_rfid_log_item',
			'description'         => 'RFIDs',
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'taxonomies'          => array(),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=dtbaker_rfid',
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

		register_post_type( 'dtbaker_rfid_log', $args );


	}

}

DtbakerMembershipManager::get_instance()->init();


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
			$this->handle_request();
			exit;
		}
	}

	/** Handle Requests
	 *	This is where we send off for an intense pug bomb package
	 *	@return void
	 */
	protected function handle_request(){
		global $wp;

		$rfid = $wp->query_vars['rfid'];
		if(!$rfid || strlen($rfid) < 5)
			$this->send_response('Please tell us RFID code.');

		$access = $wp->query_vars['access'];
		$available_access = get_terms( 'dtbaker_membership_access', array(
			'hide_empty' => false,
		) );
		$valid_access_term_id = false;
		foreach($available_access as $available_acces){
			if($access && $available_acces->slug == $access){
				$valid_access_term_id = $available_acces->term_id;
			}
		}
		if(!$access || !$valid_access_term_id)
			$this->send_response('Please tell us Access location.');

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
				'post_content' => 'Inserted by the API by a request from '.$access.' '.$_SERVER['REMOTE_ADDR'].' at '.date('Y-m-d H:i:s'),
			));
		}

		if($valid_rfid){
			//echo "Found a RFID card with id $valid_rfid";
			// we have a valid RFID card.
			// log a history for this card
			$rfid_history_event = wp_insert_post(array(
				'post_title' => $rfid . ' @ ' .$access,
				'post_type' => 'dtbaker_rfid_log',
				'post_status' => 'publish',
				'post_content' => 'Access from API by a request from '.$access.' '.$_SERVER['REMOTE_ADDR'].' at '.date('Y-m-d H:i:s'),
			));
			if($rfid_history_event){
				wp_set_object_terms( $rfid_history_event, $valid_access_term_id, 'dtbaker_membership_access' );

				// check its association to a member.
				$members = get_posts(array(
					'post_type' => 'dtbaker_membership',
					'post_status' => 'publish',
					'meta_key'   => 'membership_details_rfid',
					'meta_value' => $valid_rfid
				));
				if($members){
					// valid membership! yay!
					$member_access = current($members);
					if($member_access->ID){
						$this->send_response('365');
					}
				}
			}


		}
		// return number of days until membership expires

		$this->send_response('0');
		//$this->send_response("Success with RFID $rfid at location $access");
	}

	/** Response Handler
	 *	This sends a JSON response to the browser
	 */
	protected function send_response($msg){
		$response['message'] = $msg;
		header('content-type: text/plain');
		echo json_encode($response)."\n";
		exit;
	}
}
new TechSpace_API_Endpoint();