<?php

class TechSpace_Cpt {

	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function init() {
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
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
		$args   = array(
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
		$args   = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'member-membership-type' ),
		);
		register_taxonomy( 'dtbaker_membership_type', array( 'dtbaker_membership' ), $args );
		// Add custom form fields to the member area
		add_action( 'dtbaker_membership_type_edit_form_fields', array( $this, 'membership_type_form' ), 10, 2 );
		add_action( 'edited_dtbaker_membership_type', array( $this, 'membership_type_form_save' ), 10, 2 );

		$labels  = array(
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

	public function membership_type_form( $term, $taxonomy ) {
		?>
		<tr class="form-field">
			<th scope="row"><label for="description">Signup</label></th>
			<td>
				<input type="checkbox" name="membership_type_signup"
				       value="1" <?php checked( get_term_meta( $term->term_id, 'membership_type_signup', true ) ); ?>>
				<p class="description">If this option should be available in the public signup form</p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="description">Square</label></th>
			<td>
				Enabled: <input type="checkbox" name="membership_type_square"
				                value="1" <?php checked( get_term_meta( $term->term_id, 'membership_type_square', true ) ); ?>>
				<br/>
				Months: <input type="text" name="membership_type_square_months"
				               value="<?php echo (int) get_term_meta( $term->term_id, 'membership_type_square_months', true ); ?>">
				<br/>
				Price (in cents): <input type="text" name="membership_type_square_price"
				                         value="<?php echo (int) get_term_meta( $term->term_id, 'membership_type_square_price', true ); ?>">
				<br/>
			</td>
		</tr>
		<?php
	}

	public function membership_type_form_save( $term_id, $tt_id ) {
		update_term_meta( $term_id, 'membership_type_signup', ! empty( $_POST['membership_type_signup'] ) );
		update_term_meta( $term_id, 'membership_type_square', ! empty( $_POST['membership_type_square'] ) );
		update_term_meta( $term_id, 'membership_type_square_months', ! empty( $_POST['membership_type_square_months'] ) ? (int) $_POST['membership_type_square_months'] : 0 );
		update_term_meta( $term_id, 'membership_type_square_price', ! empty( $_POST['membership_type_square_price'] ) ? (int) $_POST['membership_type_square_price'] : 0 );
	}


	public function get_member_types() {
		$terms = get_terms( [
			'taxonomy'   => 'dtbaker_membership_type',
			'hide_empty' => false,
		] );

		$member_types = [];

		foreach ( $terms as $term ) {
			$is_square_enabled      = get_term_meta( $term->term_id, 'membership_type_square', true );
			$member_duration_months = get_term_meta( $term->term_id, 'membership_type_square_months', true );
			$square_member_price    = get_term_meta( $term->term_id, 'membership_type_square_price', true );
			$public_signup_allowed  = get_term_meta( $term->term_id, 'membership_type_signup', true );
			$member_types[]         = array(
				'name'            => $term->name,
				'description'     => $term->description,
				'months'          => $member_duration_months,
				'price'           => $square_member_price,
				'term_id'         => $term->term_id,
				'public_signup'   => $public_signup_allowed,
				'square_invoices' => $is_square_enabled,
			);
		}

		return $member_types;
	}

}

TechSpace_Cpt::get_instance()->init();

