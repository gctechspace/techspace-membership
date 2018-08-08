<?php

class dtbaker_rfid {


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
		add_filter( 'manage_dtbaker_rfid_posts_columns', array( $this, 'manage_dtbaker_rfid_posts_columns' ) );
		add_action( 'manage_dtbaker_rfid_posts_custom_column', array(
			$this,
			'manage_dtbaker_rfid_posts_custom_column'
		), 10, 2 );
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );

		$this->detail_fields = apply_filters( 'dtbaker_rfid_detail_fields', array(
			'member_id'   => array(
				'title' => 'Member',
				'type'  => 'select',
			),
			'last_access' => array(
				'title' => 'Last Access',
				'type'  => 'date',
			),
		) );
	}

	public function admin_menu() {

		$page = add_submenu_page( 'edit.php?post_type=dtbaker_membership', __( 'RFID History Log' ), __( 'RFID History Log' ), 'edit_pages', 'rfid_history', array(
			$this,
			'show_rfid_history'
		) );

	}

	public function show_rfid_history() {
		?>
		<div class="wrap">
			<h1>RFID History Log</h1>

			<?php
			ini_set( 'display_errors', true );
			ini_set( 'error_reporting', E_ALL );
			$myListTable = new TechSpaceRFIDHistoryTable( array(
				'screen' => 'rfid_history'
			) );
			global $wpdb;
			$history = $wpdb->get_results(
				"SELECT * 
				FROM `" . $wpdb->prefix . "ts_rfid` ORDER BY ts_rfid DESC"
				, ARRAY_A
			);
			$myListTable->set_data( $history );
			$myListTable->set_callback( function ( $item, $column_name ) {
				switch ( $column_name ) {
					case 'member_id':
						if ( $item['member_id'] ) {
							$member = get_post( $item['member_id'] );
							if ( $member ) {
								return sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $member->ID ) ), esc_html( $member->post_title ) );
							}
						}

						return 'N/A';
						break;
					case 'rfid_id':
						if ( $item['rfid_id'] ) {
							$member = get_post( $item['rfid_id'] );
							if ( $member ) {
								return sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $member->ID ) ), esc_html( $member->post_title ) );
							}
						}

						return 'N/A';
						break;
					case 'time':
						return date( 'Y-m-d H:i:s', $item['access_time'] );
						break;
					case 'access':
						if ( $item['access_id'] > 0 ) {
							$available_access = get_terms( 'dtbaker_membership_access', array(
								'hide_empty' => false,
							) );
							foreach ( $available_access as $available_acces ) {
								if ( $available_acces->term_id == $item['access_id'] ) {
									return $available_acces->slug;
								}
							}

							return 'Unknown';
						} else {
							return 'Checkin';
						}

						break;
				}
			} );
			$myListTable->prepare_items();
			$myListTable->display();
			?>

		</div>
		<?php
	}


	public function manage_dtbaker_rfid_posts_columns( $columns ) {
		unset( $columns['author'] );
		unset( $columns['date'] );
		$columns['member_id']   = __( 'Member' );
		$columns['last_access'] = __( 'Last Used' );

		return $columns;
	}

	public function manage_dtbaker_rfid_posts_custom_column( $column, $post_id ) {
		$details = $this->get_details( $post_id );
		switch ( $column ) {
			case 'member_id':
				// find which member has this rfid key with a custom query.
				if ( $details['member_id'] ) {
					$member = get_post( $details['member_id'] );
					if ( $member ) {
						printf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $member->ID ) ), esc_html( $member->post_title ) );
					}
				}
				break;
			case 'last_access':
				if ( ! empty( $details[ $column ] ) ) {
					echo date( 'Y-m-d H:i:s', $details[ $column ] );
					//echo ' ('.DtbakerMembershipManager::get_instance()->fuzzy_date($details[$column]).')';
				}
				break;
			default:
				if ( ! empty( $details[ $column ] ) ) {
					echo esc_html( $details[ $column ] );
				} else {
					echo 'N/A';
				}
				break;

		}
	}

	public function get_details( $post_id ) {
		$detail_fields = array();
		foreach ( $this->detail_fields as $key => $val ) {
			$detail_fields[ $key ] = get_post_meta( $post_id, 'rfid_details_' . $key, true );
		}

		return $detail_fields;
	}

	public function meta_box_callback( $post ) {

		wp_nonce_field( 'dtbaker_rfid_metabox_nonce', 'dtbaker_rfid_metabox_nonce' );

		$rfid_details = $this->get_details( $post->ID );
		foreach ( $this->detail_fields as $field_id => $field_data ) {


			if ( ! is_array( $field_data ) ) {
				$field_data = array(
					'title' => $field_data,
					'type'  => 'text'
				);
			}

			?>
			<p>
				<label
					for="member_detail_<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field_data['title'] ); ?></label>
				<?php switch ( $field_id ) {
					case 'member_id':
						DtbakerMembershipManager::get_instance()->generate_post_select( 'rfid_details[member_id]', 'dtbaker_membership', $rfid_details[ $field_id ] );
						break;
					default:
						switch ( $field_data['type'] ) {
							case 'text':
								?>
								<input type="text" name="rfid_details[<?php echo esc_attr( $field_id ); ?>]"
								       id="member_detail_<?php echo esc_attr( $field_id ); ?>"
								       value="<?php echo esc_attr( isset( $rfid_details[ $field_id ] ) ? $rfid_details[ $field_id ] : '' ); ?>">
								<?php
								break;
							case 'date':
								?>
								<input type="text" name="rfid_details[<?php echo esc_attr( $field_id ); ?>]"
								       id="member_detail_<?php echo esc_attr( $field_id ); ?>"
								       value="<?php echo esc_attr( ! empty( $rfid_details[ $field_id ] ) ? date( 'Y-m-d', $rfid_details[ $field_id ] ) : '' ); ?>"
								       class="dtbaker-datepicker">
								<?php
								break;
						}
				}
				?>
			</p>
			<?php
		}

	}


	public function add_meta_box() {

		$screens = array( 'dtbaker_rfid' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'dtbaker_membership_page_meta_price',
				__( 'RFID Details' ),
				array( $this, 'meta_box_callback' ),
				$screen,
				'normal',
				'high'
			);
		}


	}

	public function save_meta_box( $post_id ) {
		// Check if our nonce is set.
		if ( ! isset( $_POST['dtbaker_rfid_metabox_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['dtbaker_rfid_metabox_nonce'], 'dtbaker_rfid_metabox_nonce' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( isset( $_POST['rfid_details'] ) && is_array( $_POST['rfid_details'] ) ) {
			$rfid_details = $_POST['rfid_details'];
			foreach ( $this->detail_fields as $key => $val ) {
				if ( isset( $rfid_details[ $key ] ) ) {
					if ( is_array( $val ) && isset( $val['type'] ) && $val['type'] == 'date' ) {
						$rfid_details[ $key ] = strtotime( $rfid_details[ $key ] );
					}
					update_post_meta( $post_id, 'rfid_details_' . $key, isset( $rfid_details[ $key ] ) ? $rfid_details[ $key ] : '' );
				}
			}

		}

	}


	public function register_custom_post_type() {


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
			'show_in_menu'        => 'edit.php?post_type=dtbaker_membership',
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


	}

}

dtbaker_rfid::get_instance()->init();