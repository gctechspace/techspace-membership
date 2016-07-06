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
		$membership_details = get_post_meta( $post_id, 'membership_details', true );
		if( !$membership_details || !is_array($membership_details) ){
			$membership_details = array();
		}
		switch($column){
			case 'rfid':
				// obfuscate RFID key a bit:
				echo isset($membership_details['rfid']) ? str_pad(substr($membership_details['rfid'],0,5), strlen($membership_details['rfid']), '*', STR_PAD_RIGHT) : 'N/A';
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

	public function meta_box_price_callback( $post ) {

		wp_nonce_field( 'dtbaker_membership_metabox_nonce', 'dtbaker_membership_metabox_nonce' );

		$membership_details = get_post_meta( $post->ID, 'membership_details', true );
		if( !$membership_details || !is_array($membership_details) ){
			$membership_details = array();
		}
		foreach($this->membership_detail_fields as $field_id => $field_title){
			?>
			<p>
				<label for="member_detail_<?php echo esc_attr( $field_id );?>"><?php echo esc_html($field_title); ?></label>
				<?php switch($field_id){
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
					$membership_details['xero_cache'] = $contacts[$membership_details['xero_id']];
				}else{
					unset($membership_details['xero_id']);
					unset($membership_details['xero_cache']);
				}
			}
			update_post_meta( $post_id, 'membership_details', $membership_details );

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

		register_taxonomy( 'dtbaker_membership_access', array( 'dtbaker_membership' ), $args );

		
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
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 36,
			'menu_icon'           => 'dashicons-admin-users',
			'can_export'          => true,
			'has_archive'         => false, // important for our support/documentation-menu slug
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'rewrite'             => $rewrite,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type( 'dtbaker_membership', $args );


	}

}

DtbakerMembershipManager::get_instance()->init();
