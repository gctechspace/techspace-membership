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

	public $location_details = array();

	public $social_icons = array();

	public function init() {
		add_action( 'admin_init', array( $this, 'admin_init' ), 20 );
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_css' ) );
		add_filter( 'manage_dtbaker_membership_posts_columns', array( $this, 'manage_dtbaker_membership_posts_columns' ) );
		add_action( 'manage_dtbaker_membership_posts_custom_column' , array( $this, 'manage_dtbaker_membership_posts_custom_column' ), 10, 2 );


		$this->social_icons = apply_filters('dtbaker_membership_icons', array(
			'facebook' => 'Facebook',
			'twitter' => 'Twitter',
			'google-plus' => 'Google+',
			'envelope' => 'Email',
		));
	}

	public function manage_dtbaker_membership_posts_columns( $columns ){
		unset( $columns['author'] );
		unset( $columns['date'] );
		$columns['email'] = __( 'Email' );
		$columns['rfid'] = __( 'RFID' );
		$columns['expiry'] = __( 'Membership Expiry' );
		$columns['expiry'] = __( 'Membership Expiry' );
		$columns['paid'] = __( 'Total Paid' );
		return $columns;
	}

	public function manage_dtbaker_membership_posts_custom_column( $column, $post_id ){

	}

	public function widgets_init(){

	}

	public function admin_init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_css' ) );
	}
	public function frontend_css() {
		wp_register_style( 'dtbaker_membership_frontend', plugins_url( 'css/membership-frontend.css', __FILE__ ) , false, '1.0.1' );
		wp_enqueue_style( 'dtbaker_membership_frontend' );
	}
	public function admin_css() {
		wp_register_style( 'dtbaker_membership_admin', plugins_url( 'css/membership-admin.css', __FILE__ ) , false, '1.0.1' );
		wp_enqueue_style( 'dtbaker_membership_admin' );
	}


	public function meta_box_price_callback( $post ) {

		wp_nonce_field( 'dtbaker_membership_metabox_nonce', 'dtbaker_membership_metabox_nonce' );
		$membership_role = get_post_meta( $post->ID, 'membership_role', true );
		if ( ! $membership_role ) {
			$membership_role = '';
		}
		?>
		<label for="dtbaker_membership_post_style"><?php _e( 'Role' ); ?></label>
		<p>
			<input type="text" name="membership_role" id="membership_role" value="<?php echo esc_attr( $membership_role ); ?>">
		</p>
		<p>
			<small><?php _e( '(e.g. Sales, Accounting)' ); ?></small>
			<br/></p>
		<?php
		$contact = get_post_meta( $post->ID, 'membership_contact', true );
		if( !$contact || !is_array($contact) ){
			$contact = array();
		}
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

		$screens = array( 'dtbaker_membership_item' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'dtbaker_membership_page_meta_price',
				__( 'Member Details' ),
				array( $this, 'meta_box_price_callback' ),
				$screen,
				'side',
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

		if ( isset( $_POST['membership_role'] ) ) {
			update_post_meta( $post_id, 'membership_role', $_POST['membership_role'] );
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
			'hierarchical'        => true,
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
