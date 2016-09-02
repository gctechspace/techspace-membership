<?php

class dtbaker_member{


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
		add_filter( 'manage_dtbaker_membership_posts_columns', array( $this, 'manage_dtbaker_membership_posts_columns' ) );
		add_action( 'manage_dtbaker_membership_posts_custom_column' , array( $this, 'manage_dtbaker_membership_posts_custom_column' ), 10, 2 );
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );

		$this->detail_fields = apply_filters('dtbaker_membership_detail_fields', array(
			'role' => array(
				'title' => 'Your Interests',
				'type' => 'text',
				'eg' => 'e.g. Programming, Electronics, 3D Printing, Drones'
			),
			'rfid' => 'RFID Keys',
			'xero_id' => 'Xero Contact',
			'locker_number' => 'Locker Number',
			'member_start' => array(
				'title' => 'Member Start',
				'type' => 'date',
			),
			'member_end' => array(
				'title' => 'Member End',
				'type' => 'date',
			),
			'phone' => 'Phone Number',
			'emergency' => array(
				'title' => 'Emergency Contact',
				'type' => 'text',
				'eg' => 'Please enter the name and number of an emergency contact'
			),
			'facebook' => 'Facebook',
			'twitter' => 'Twitter',
			'google_plus' => 'Google+',
			'email' => 'Email Address',
			'slack' => 'Slack',
			'notifications' => array(
				'title' => 'Notifications',
				'type' => 'select',
				'options' => array(
					'0' => 'Publish notifications on door openenings (e.g. to Slack)',
					'1' => 'No public notifications',
				)
			),
		));

	}

	public function admin_menu(){


	}


	public function manage_dtbaker_membership_posts_columns( $columns ){
		unset( $columns['author'] );
		unset( $columns['date'] );
		$columns['rfid'] = __( 'RFID' );
		$columns['xero_id'] = __( 'Xero Contact' );
		$columns['invoices'] = __( 'Invoices' );
		//$columns['member_start'] = __( 'Membership Start' );
		$columns['member_end'] = __( 'Membership Expiry' );
		return $columns;
	}

	public function manage_dtbaker_membership_posts_custom_column( $column, $post_id ){
		$membership_details = $this->get_details($post_id);
		switch($column){
			case 'rfid':
				// look up linked rfid keys.

				$rfid_keys = get_posts(array(
					'post_type' => 'dtbaker_rfid',
					'post_status' => 'publish',
					'meta_key'   => 'rfid_details_member_id',
					'meta_value' => $post_id,
				));
				if($rfid_keys) {
					foreach($rfid_keys as $rfid_key){
						//echo str_pad(substr($rfid_code,0,5), strlen($rfid_code), '*', STR_PAD_RIGHT);
						printf('<a href="%s">%s</a> ', esc_url(get_edit_post_link($rfid_key->ID)), esc_html($rfid_key->post_title));
					}
				}else{
					echo 'None';
				}
				break;
			case 'xero_id':
				if(!empty($membership_details['xero_cache'])){
					echo esc_html(implode( ' / ', $membership_details['xero_cache']));
				}
				break;
			case 'member_start':
				if(!empty($membership_details[$column])){
					echo date('Y-m-d',$membership_details[$column]);
				}
				break;
			case 'member_end':
				if(!empty($membership_details[$column])){
					echo date('Y-m-d',$membership_details[$column]);
					$member_status = 'member_status_';
					if($membership_details[$column] <= time()){
						$member_status.='expired';
					}else if($membership_details[$column] <= strtotime('+3 weeks')){
						$member_status.='expiring';
					}else{
						$member_status.='good';
					}
					echo ' <span class="member_status '.$member_status.'">'.DtbakerMembershipManager::get_instance()->fuzzy_date($membership_details[$column]).'</span>';
				}
				break;
			case 'invoices':
				if(!empty($membership_details['invoice_cache']) && is_array($membership_details['invoice_cache']) && count($membership_details['invoice_cache'])){
					end($membership_details['invoice_cache']);
					$this->_print_invoice_details(key($membership_details['invoice_cache']), current($membership_details['invoice_cache']));
					/*foreach($membership_details['invoice_cache'] as $invoice_id => $invoice){
						$this->_print_invoice_details($invoice_id, $invoice);
					}*/
				}
				break;
			default:
				if(!empty($membership_details[$column])){
					echo esc_html($membership_details[$column]);
				}else{
					echo 'N/A';
				}
				break;

		}
	}


	public function get_details($post_id){
		$detail_fields = array();
		foreach($this->detail_fields as $key=>$val){
			$detail_fields[$key] = get_post_meta( $post_id, 'membership_details_'.$key, true );
		}
		$detail_fields['expiry_days'] = (!empty($detail_fields['member_end']) && $detail_fields['member_end'] > time()+86400) ? round(($detail_fields['member_end'] - time()) / 86400) : 0;
		$detail_fields['xero_cache'] = get_post_meta( $post_id, 'membership_details_xero_cache', true );
		$detail_fields['invoice_cache'] = get_post_meta( $post_id, 'membership_details_invoice_cache', true );
		return $detail_fields;
	}

	public function meta_box_callback( $post ) {

		wp_nonce_field( 'dtbaker_membership_metabox_nonce', 'dtbaker_membership_metabox_nonce' );

		$membership_details = $this->get_details($post->ID);
		foreach($this->detail_fields as $field_id => $field_data){


			if(!is_array($field_data)){
				$field_data = array(
					'title' => $field_data,
					'type' => 'text'
				);
			}

			?>
			<div class="dtbaker_member_form_field">
				<label for="member_detail_<?php echo esc_attr( $field_id );?>"><?php echo esc_html($field_data['title']); ?></label>
				<?php switch($field_id){
					case 'rfid':
						//DtbakerMembershipManager::get_instance()->generate_post_select( 'membership_details[rfid]', 'dtbaker_rfid', $membership_details['rfid']);
						// look up linked rfid keys.
						$rfid_keys = get_posts(array(
							'post_type' => 'dtbaker_rfid',
							'post_status' => 'publish',
							'meta_key'   => 'rfid_details_member_id',
							'meta_value' => $post->ID,
						));
						if($rfid_keys) {
							foreach($rfid_keys as $rfid_key){
								//echo str_pad(substr($rfid_code,0,5), strlen($rfid_code), '*', STR_PAD_RIGHT);
								printf('<a href="%s">%s</a> ', esc_url(get_edit_post_link($rfid_key->ID)), esc_html($rfid_key->post_title));
							}
						}else{
							echo 'None';
						}
						break;
					case 'xero_id':
						// lookup xero contacts from api. uses the ContactID field from Xero
						$contacts = dtbaker_xero::get_instance()->get_all_contacts( isset($_REQUEST['xero_refresh']) );
						if(empty($membership_details['xero_id']))$membership_details['xero_id'] = 0;
						?>
						<select name="membership_details[xero_id]">
							<option value=""> - Please Select - </option>
							<option value="CreateNew">Create New XERO Contact</option>
							<?php if(is_array($contacts) && count($contacts)){
								foreach($contacts as $contact_id => $contact){ ?>
									<option value="<?php echo esc_attr($contact_id);?>" <?php echo selected($membership_details['xero_id'], $contact_id);?>><?php echo esc_attr($contact['name']);?></option>
								<?php }
							}else{
								?>
								<option value=""> failed to get xero listing </option><?php
							} ?>
						</select>
						<a href="<?php echo add_query_arg('xero_refresh', 1, get_edit_post_link($post->ID));?>" class="member_inline_button">Refresh List</a>
						<?php
						// look up xero invoices for this contact
						if( !empty($membership_details['xero_id'])) {
							?>
							<br/>
							<br/>
							Xero Invoices:
							<a href="<?php echo add_query_arg('xero_invoice_refresh', 1, get_edit_post_link($post->ID));?>" class="member_inline_button">Refresh</a>
							<a href="#" class="member_inline_button" id="xero_create_new_invoice">Create New</a>
							<div id="xero_create_invoice_form">
								<strong>Create a new Invoice in Xero:</strong>
								<div class="dtbaker_member_form_field">
									Invoice Date: <input type="text" name="xero_invoice_date" value="" class="dtbaker-datepicker">
								</div>
								<div class="dtbaker_member_form_field">
									<?php //print_r(dtbaker_xero::get_instance()->get_line_items()); ?>
									Membership Type: <select type="text" name="xero_line_item">
										<option value="">Please select</option>
										<?php
										$line_items = $this->get_member_types();
										foreach($line_items as $line_item_id => $line_item){
											?>
											<option value="<?php echo $line_item_id;?>"><?php echo $line_item['name'] . ' $'.$line_item['price'];?></option>

											<?php
										}
										?>
									</select>
								</div>
								<div class="dtbaker_member_form_field">
									<input type="button" name="create_invoice" value="Create Invoice" onclick="jQuery('#publish').click();">
								</div>
							</div>
							<br/>
							<?php
							$invoices = dtbaker_xero::get_instance()->get_contact_invoices( $membership_details['xero_id'], isset($_REQUEST['xero_invoice_refresh']) );
							if($invoices){
								$this->cache_member_invoices($post->ID, $invoices);
								?>
								<ul class="dtbaker-xero-invoices">
									<?php foreach($invoices as $invoice_id => $invoice){ ?>
										<li>
											<?php $this->_print_invoice_details($invoice_id, $invoice); ?>
										</li>
									<?php } ?>
								</ul>
								<?php
							}
						}

						break;
					default:
						switch($field_data['type']){
							case 'select':
								if(empty($membership_details[$field_id]))$membership_details[$field_id] = 0;
								?>
								<select name="membership_details[<?php echo esc_attr( $field_id );?>]" id="member_detail_<?php echo esc_attr( $field_id );?>">
									<?php foreach($field_data['options'] as $value => $label){ ?>
										<option value="<?php echo esc_attr($value);?>" <?php echo selected($membership_details[$field_id], $value);?>><?php echo esc_attr($label);?></option>
									<?php } ?>
								</select>
								<?php
								break;
							case 'text':
								?>
								<input type="text" name="membership_details[<?php echo esc_attr( $field_id );?>]" id="member_detail_<?php echo esc_attr( $field_id );?>" value="<?php echo esc_attr( isset($membership_details[$field_id]) ? $membership_details[$field_id] : '' ); ?>">
								<?php
								break;
							case 'date':
								?>
								<input type="text" name="membership_details[<?php echo esc_attr( $field_id );?>]" id="member_detail_<?php echo esc_attr( $field_id );?>" value="<?php echo esc_attr( !empty($membership_details[$field_id]) ? date('Y-m-d',$membership_details[$field_id]) : '' ); ?>" class="dtbaker-datepicker">
								<?php
								break;
						}
				}
				?>
			</div>
			<?php
		}

	}

	private function _print_invoice_details($invoice_id, $invoice){
		?>
		<a href="https://go.xero.com/AccountsReceivable/View.aspx?InvoiceID=<?php echo esc_attr($invoice_id);?>" target="_blank"><?php echo esc_html($invoice['number'] .' '.date('Y-m-d',$invoice['time']) .' '.$invoice['status'].' $'.$invoice['total']); ?></a> <?php
		if($invoice['due'] > 0){
			?>
			<span class="dtbaker-invoice-due">$<?php echo esc_html($invoice['due']);?> due.</span>
			<?php
		}else if($invoice['status'] == 'PAID'){
			?>
			<span class="dtbaker-invoice-paid">$<?php echo esc_html($invoice['total']);?> paid</span>
			<?php
		}
		if(!$invoice['emailed']){
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

				// create new contact if missing
				if($membership_details['xero_id'] == "CreateNew"){
					$new_xero_contact = dtbaker_xero::get_instance()->create_contact( array(
						'name' => get_the_title($post_id),
						'email'       => $membership_details['email'],
						'phone'       => $membership_details['phone'],
					) );
					$all_contacts = dtbaker_xero::get_instance()->get_all_contacts( true );
					if($new_xero_contact && isset($all_contacts[$new_xero_contact])){
						$membership_details['xero_id'] = $new_xero_contact;
					}else{
						echo 'Failed to create new contact'; exit;
					}
				}
				// cache local xero details for this member
				$contacts = dtbaker_xero::get_instance()->get_all_contacts( );
				if(isset($contacts[$membership_details['xero_id']])){
					update_post_meta( $post_id, 'membership_details_xero_cache', $contacts[$membership_details['xero_id']]);

					// we have a valid contact in our system. are we generating an invoice?
					if(!empty($_POST['xero_line_item']) && !empty($_POST['xero_invoice_date']) && $invoice_time = strtotime($_POST['xero_invoice_date'])){
						$line_items = $this->get_member_types();
						if(isset($line_items[$_POST['xero_line_item']])) {
							$new_invoice = dtbaker_xero::get_instance()->create_invoice( array(
								'contact_id' => $membership_details['xero_id'],
								'date'       => date( 'Y-m-d', $invoice_time ),
								'due_date'   => date( 'Y-m-d', strtotime( '+7 days', $invoice_time ) ),
								'item_code'  => $line_items[$_POST['xero_line_item']]['code'],
								'description'  => $line_items[$_POST['xero_line_item']]['name'],
							) );
							if($new_invoice){
								// reload invoice cache for member
								$invoices = dtbaker_xero::get_instance()->get_contact_invoices( $membership_details['xero_id'], true );
								$this->cache_member_invoices($post_id, $invoices);
							}else{
								echo 'Failed to create invoice'; exit;
							}
						}
					}
				}else{
					unset($membership_details['xero_id']);
				}
			}
			foreach($this->detail_fields as $key=>$val){
				// format date fields as timestamps for easier querying.
				if(isset($membership_details[$key])) {
					if ( is_array( $val ) && isset( $val['type'] ) && $val['type'] == 'date' ) {
						$membership_details[ $key ] = strtotime( $membership_details[ $key ] );
					}
					update_post_meta( $post_id, 'membership_details_' . $key, $membership_details[ $key ] );
				}
			}

		}

	}

	public function cache_member_invoices($member_post_id, $invoices){
		if($invoices && is_array($invoices)){
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


	}

	public function get_member_types() {

		// todo: config utility to select which line items to store.
		$member_types = array(
			'b5f3cfc-6f3e-4311-978f-e36069929067' => array(
				'code' => 'mem12Months',
				'name' => '12 Months Membership',
				'months' => '12',
				'price' => '225',
			),
			'07860992-c84a-4633-9805-065aba35401b' => array(
				'code' => 'mem6Monthly',
				'name' => '6 Months Membership',
				'months' => '6',
				'price' => '125',
			),
			'e11ddd97-062e-408f-abaa-2cdd1214a2aa' => array(
				'code' => 'memMonthly2016',
				'name' => '1 Month Membership',
				'months' => '1',
				'price' => '25',
			),
		);
		/*foreach(dtbaker_xero::get_instance()->get_line_items() as $id => $line_item){
			if(!isset($member_types[$id]) && $line_item['price']){
				$line_item['name'] = $line_item['description'];
				$member_types[$id] = $line_item;
			}
		}*/
		return $member_types;
	}

}

dtbaker_member::get_instance()->init();

