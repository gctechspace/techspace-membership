<?php

/**
 * Plugin Name: Techspace Membership
 * Description: Membership management for techspace
 * Plugin URI: http://dtbaker.net
 * Version: 1.0.2
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


	public function init() {
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_css' ) );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_css' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'plugins_loaded', array( $this, 'db_upgrade_check' ) );
		add_filter( 'whitelist_options', array( $this, 'whitelist_options' ), 100, 1 );

	}

	public function admin_menu() {
		add_options_page(
			'TechSpace Member',
			'TechSpace Member',
			'manage_options',
			'techspace-membership-plugin',
			array( $this, 'menu_settings_callback' )
		);

	}

	public function menu_settings_callback() {
		?>
		<div class="wrap">
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

	public function widgets_init() {

	}

	public function settings_init() {
		add_settings_section(
			'techspace_membership_section',
			'TechSpace Membership Settings',
			array( $this, 'settings_section_callback' ),
			'techspace_member_settings'
		);

		add_settings_field(
			'techspace_membership_api_secret',
			'Our API Secret',
			array( $this, 'settings_callback_api_secret' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_api_secret' );

		add_settings_field(
			'techspace_membership_slack_api',
			'Slack API Key',
			array( $this, 'settings_callback_slack_api' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_slack_api' );
		add_settings_field(
			'techspace_membership_slack_real_token',
			'Slack Real Token',
			array( $this, 'settings_callback_slack_real_token' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_slack_real_token' );

		add_settings_field(
			'techspace_membership_wifi_password',
			'Current Wifi Password',
			array( $this, 'settings_callback_wifi_api' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_wifi_password' );

		add_settings_field(
			'techspace_membership_square_app_id',
			'Square App ID',
			array( $this, 'settings_callback_square_app_id' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_square_app_id' );

		add_settings_field(
			'techspace_membership_square_access_token',
			'Square Access Token',
			array( $this, 'settings_callback_square_access_token' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_square_access_token' );

		add_settings_field(
			'techspace_membership_square_location_id',
			'Square Location ID',
			array( $this, 'settings_callback_square_location_id' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_square_location_id' );

		add_settings_field(
			'techspace_membership_square_environment',
			'Square Environment (production or sandbox)',
			array( $this, 'settings_callback_square_environment' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_square_environment' );

		add_settings_field(
			'techspace_membership_square_webhook_signature',
			'Square Webhook Signature',
			array( $this, 'settings_callback_square_webhook_signature' ),
			'techspace_member_settings',
			'techspace_membership_section'
		);
		register_setting( 'techspace_member_settings', 'techspace_membership_square_webhook_signature' );
	}

	public function whitelist_options( $options ) {

		if ( isset( $options['techspace_member_settings'] ) && ! empty( $_POST['option_page'] ) && $_POST['option_page'] == 'techspace_member_settings' ) {
			// we're saving the techspace member settings area. remove options if we are attempting to save an empty field.
			foreach ( $options['techspace_member_settings'] as $index => $key ) {
				if ( empty( $_POST[ $key ] ) ) {
					unset( $options['techspace_member_settings'][ $index ] );
				}
			}
		}

		return $options;
	}

	public function settings_section_callback() {
		echo '<p>Please set the TechSpace membership settings below:</p>';
	}

	public function settings_callback_api_secret() {
		$setting = esc_attr( get_option( 'techspace_membership_api_secret' ) );
		?> <input type="password" name="techspace_membership_api_secret" class="techspace-edit-form" value="<?php echo $setting ;?>"> <?php
	}

	public function settings_callback_slack_api() {
		$setting = esc_attr( get_option( 'techspace_membership_slack_api' ) );
		?> <input type="password" name="techspace_membership_slack_api" class="techspace-edit-form" value="<?php echo $setting ;?>"> <?php
	}

	public function settings_callback_slack_real_token() {
		$setting = esc_attr( get_option( 'techspace_membership_slack_real_token' ) );
		?> <input type="password" name="techspace_membership_slack_real_token" class="techspace-edit-form" value="<?php echo $setting ;?>"> <?php
	}

	public function settings_callback_wifi_api() {
		$setting = esc_attr( get_option( 'techspace_membership_wifi_password' ) );
		?> <input type="password" name="techspace_membership_wifi_password" class="techspace-edit-form" value="<?php echo $setting ;?>"> <?php
	}

	public function settings_callback_square_app_id() {
		$setting = esc_attr( get_option( 'techspace_membership_square_app_id' ) );
		?> <input type="password" name="techspace_membership_square_app_id" class="techspace-edit-form" value="<?php echo $setting ;?>"> <?php
	}

	public function settings_callback_square_access_token() {
		$setting = esc_attr( get_option( 'techspace_membership_square_access_token' ) );
		?> <input type="password" name="techspace_membership_square_access_token" class="techspace-edit-form" value="<?php echo $setting ;?>"> <?php
	}

	public function settings_callback_square_location_id() {
		$setting = esc_attr( get_option( 'techspace_membership_square_location_id' ) );
		?> <input type="password" name="techspace_membership_square_location_id" class="techspace-edit-form" value="<?php echo $setting ;?>"> <?php
	}

	public function settings_callback_square_environment() {
		$setting = esc_attr( get_option( 'techspace_membership_square_environment' ) );
		?> <input type="password" name="techspace_membership_square_environment" class="techspace-edit-form" value="<?php echo $setting ;?>"> <?php
	}

	public function settings_callback_square_webhook_signature() {
		$setting = esc_attr( get_option( 'techspace_membership_square_webhook_signature' ) );
		?> <input type="password" name="techspace_membership_square_webhook_signature" class="techspace-edit-form" value="<?php echo $setting ;?>">
		<code>https://gctechspace.org/api/square/webhook</code>
		<?php
	}

	public function frontend_css() {
		wp_register_style( 'dtbaker_membership_frontend', plugins_url( 'css/membership-frontend.css', __FILE__ ), false, '1.0.1' );
		wp_enqueue_style( 'dtbaker_membership_frontend' );
	}

	public function admin_css() {
		wp_register_style( 'dtbaker_membership_admin', plugins_url( 'css/membership-admin.css', __FILE__ ), false, '1.0.1' );
		wp_enqueue_style( 'dtbaker_membership_admin' );
		wp_enqueue_script(
			'field-date-js', plugins_url( 'js/techspace-membership.js', __FILE__ ), array(
			'jquery',
			'jquery-ui-core',
			'jquery-ui-datepicker'
		), time(), true
		);
		wp_enqueue_style( 'jquery-ui-datepicker-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css' );
		wp_enqueue_style( 'jquery-ui-datepicker' );
	}


	public function generate_post_select( $select_id, $post_type, $selected = 0 ) {
		$post_type_object = get_post_type_object( $post_type );
		$label            = $post_type_object->label;
		$posts            = get_posts( array(
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'suppress_filters' => false,
			'posts_per_page'   => - 1
		) );
		echo '<select name="' . $select_id . '" id="' . $select_id . '">';
		echo '<option value = "" > - Select ' . $label . ' - </option>';
		foreach ( $posts as $post ) {
			echo '<option value="', $post->ID, '"', $selected == $post->ID ? ' selected="selected"' : '', '>', $post->post_title, '</option>';
		}
		echo '</select>';
	}


	public function db_upgrade_check() {
		global $wpdb;

		$sql = <<< EOT

CREATE TABLE {$wpdb->prefix}ts_rfid (
  ts_rfid int(11) NOT NULL AUTO_INCREMENT,
  member_id int(11) NOT NULL DEFAULT '0',
  access_time int(11) NOT NULL DEFAULT '0',
  rfid_id int(11) NOT NULL DEFAULT '0',
  access_id int(11) NOT NULL DEFAULT '0',
  ip_address varchar(16) NOT NULL DEFAULT '',
  api_result varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  ts_rfid (ts_rfid),
  KEY member_id (member_id),
  KEY rfid_id (rfid_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}ts_buck (
  ts_buck int(11) NOT NULL AUTO_INCREMENT,
  member_id int(11) NOT NULL,
  timestamp int(11) NOT NULL DEFAULT '0',
  amount DECIMAL(12,6) NOT NULL DEFAULT '0',
  verified int(1) NOT NULL DEFAULT '0',
  state varchar(20) NOT NULL DEFAULT 'pending',
  comment varchar(254) NOT NULL DEFAULT '',
  metadata LONGTEXT NULL,
  PRIMARY KEY  ts_buck (ts_buck),
  KEY member_id (member_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

EOT;

		$hash = md5( $sql );
		if ( get_option( "techspace_member_db_hash" ) != $hash ) {
			$bits = explode( ';', $sql );
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			foreach ( $bits as $sql ) {
				if ( trim( $sql ) ) {
					dbDelta( trim( $sql ) . ';' );
				}
			}
			$wpdb->hide_errors();
			update_option( "techspace_member_db_hash", $hash );
		}

	}

	public function fuzzy_date( $time ) {
		$ago = time() - $time;
		if ( $ago >= 0 && $ago < 60 ) {
			$when = round( $ago );
			$s    = ( $when == 1 ) ? "second" : "seconds";

			return "$when $s ago";
		} elseif ( $ago >= 0 && $ago < 3600 ) {
			$when = round( $ago / 60 );
			$m    = ( $when == 1 ) ? "minute" : "minutes";

			return "$when $m ago";
		} elseif ( $ago >= 3600 && $ago < 86400 ) {
			$when = round( $ago / 60 / 60 );
			$h    = ( $when == 1 ) ? "hour" : "hours";

			return "$when $h ago";
		} elseif ( $ago >= 86400 && $ago < 2629743.83 ) {
			$when = round( $ago / 60 / 60 / 24 );
			$d    = ( $when == 1 ) ? "day" : "days";

			return "$when $d ago";
		} elseif ( $ago >= 2629743.83 && $ago < 31556926 ) {
			$when = round( $ago / 60 / 60 / 24 / 30.4375 );
			$m    = ( $when == 1 ) ? "month" : "months";

			return "$when $m ago";
		} elseif ( $ago > 31556926 ) {
			$when = round( $ago / 60 / 60 / 24 / 365 );
			$y    = ( $when == 1 ) ? "year" : "years";

			return "$when $y ago";
		} elseif ( $ago < - 31556926 ) {
			$when = abs( round( $ago / 60 / 60 / 24 / 365 ) );
			$y    = ( $when == 1 ) ? "year" : "years";

			return "in $when $y";
		} elseif ( $ago >= - 31556926 && $ago < - 2629743.83 ) { //-2678400
			$when = abs( round( $ago / 60 / 60 / 24 / 30.4375 ) );
			$m    = ( $when == 1 ) ? "month" : "months";

			return "in $when $m";
		} elseif ( $ago >= - 2629743.83 && $ago < - 86400 ) {
			$when = abs( round( $ago / 60 / 60 / 24 ) );
			$d    = ( $when == 1 ) ? "day" : "days";

			return "in $when $d";
		} elseif ( $ago >= - 86400 && $ago < - 3600 ) {
			$when = abs( round( $ago / 60 / 60 ) );
			$h    = ( $when == 1 ) ? "hour" : "hours";

			return "in $when $h";
		} elseif ( $ago >= - 3600 ) {
			$when = abs( round( $ago / 60 ) );
			$m    = ( $when == 1 ) ? "minute" : "minutes";

			return "in $when $m";
		} else {
			$when = abs( round( $ago ) );
			$s    = ( $when == 1 ) ? "second" : "seconds";

			return "in $when $s";
		}
	}

}

DtbakerMembershipManager::get_instance()->init();

require_once 'inc/class.cpt.php';
require_once 'inc/class.table.php';
require_once 'inc/class.api.php';
require_once 'inc/class.submit.php';
require_once 'inc/class.square.php';

require_once 'inc/class.member.php';
require_once 'inc/class.rfid.php';
require_once 'inc/class.cron.php';
require_once 'inc/class.stats.php';
require_once 'inc/class.bucks.php';

require_once 'vendor/autoload.php';
