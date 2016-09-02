<?php

// handle frontend form submission for membership applications


class TechSpace_Frontend_Submit{

	/** Hook WordPress
	 *	@return void
	 */
	public function __construct(){
		add_action('init', array($this, 'handle_submit'), 100);
		add_shortcode('membership_signup_form', array($this, 'membership_signup_form'));
	}

	public $details = array('role'=>'','email'=>'','phone'=>'','emergency'=>'');

	public function handle_submit(){

		if ( !empty($_POST) && !empty($_POST['techspace_member_submit']) && wp_verify_nonce($_POST['techspace_submit'],'techspace_submit_member') )
		{

// Do some minor form validation to make sure there is content
			$title = '';
			if (isset ($_POST['your_name'])) {
				$title = trim( wp_strip_all_tags( $_POST['your_name'] ) );
			}
			if(!$title){
				echo 'Please go back and enter your name.';
				exit;
			}

			$details = $this->details;
			foreach($details as $key=>$val){
				// format date fields as timestamps for easier querying.
				if(!empty($_POST[$key])) {
					$details[$key] = wp_strip_all_tags($_POST[$key]);
				}else{
					echo 'Please go back and enter all fields.';
					exit;
				}
			}

			$membership_category = 0;
			$available_categories = get_terms( 'dtbaker_membership_type', array(
				'hide_empty' => false,
			) );
			foreach($available_categories as $available_category){
				if($available_category->term_id == $_POST['membership_category']){
					$membership_category = $available_category->term_id;
				}
			}

			// Add the content of the form to $post as an array
			$post = array(
				'post_title' => wp_strip_all_tags( $title ),
				'post_content' => "Signup from website. IP Address: ". $_SERVER['REMOTE_ADDR']."\n\n\n".wp_strip_all_tags( isset($_POST['member_comments']) ? $_POST['member_comments'] : '' ),
				//'post_category' => array($membership_category),  // Usable for custom taxonomies too
				'post_status' => 'draft',            // Choose: publish, preview, future, etc.
				'post_type' => 'dtbaker_membership'  // Use a custom post type if you want to
			);
			$post_id = wp_insert_post($post);  // http://codex.wordpress.org/Function_Reference/wp_insert_post

			if($post_id){
				wp_set_object_terms($post_id, $membership_category, 'dtbaker_membership_type');

				//print_r($post);print_r($details);exit;
				foreach($details as $key=>$val){
					// format date fields as timestamps for easier querying.
					if($val) {
						update_post_meta( $post_id, 'membership_details_' . $key, $val );
					}
				}
				wp_mail("dtbaker@gmail.com","TechSpace Membership Signup ($title)","Member signup: $title. Link: ".get_edit_post_link($post_id));
				//wp_mail("manager@gctechspace.org","TechSpace Membership Signup ($title)","Member signup: $title. Link: ".get_edit_post_link($post_id));
			}

			$location = get_permalink(get_page_by_title('Signup Success'));

			echo "<meta http-equiv='refresh' content='0;url=" . esc_url($location) . "' />";
			exit;
		} // end IF
	}
	public function membership_signup_form($atts=array()){
		ob_start();
		?>
		<form method="post" action="" class="techspace_signup">
			<input type="hidden" name="techspace_member_submit" value="true">
			<?php wp_nonce_field( 'techspace_submit_member','techspace_submit' ); ?>

			<div class="membership_form_element"><label for="your_name">Your Name:</label><br />
				<input type="text" id="your_name" value="" tabindex="1" size="20" name="your_name" />
			</div>

			<div class="membership_form_element"><label for="membership_category">Membership Type:</label><br />
<!--				<small>Details about membership types available on our website.</small>-->

			<?php $available_categories = get_terms( 'dtbaker_membership_type', array(
				'hide_empty' => false,
			) );
			foreach($available_categories as $available_category){
				?>
				<div>
					<input type="radio" id="membership_category_<?php echo (int)$available_category->term_id;?>" value="<?php echo (int)$available_category->term_id;?>" name="membership_category" /> <?php echo esc_html($available_category->name).' - '.esc_html($available_category->description);?>
				</div>
				<?php
			}
			//wp_dropdown_categories( 'show_option_none=Membership+Type&tab_index=4&taxonomy=dtbaker_membership_type&hide_empty=0&name=membership_category' );
			?>
			</div>

			<?php foreach($this->details as $key=>$val){
				$field = dtbaker_member::get_instance()->detail_fields[$key];
				if(!is_array($field))$field = array('title'=>$field);
				?>
				<div class="membership_form_element"><label for="<?php echo esc_attr($key);?>"><?php echo esc_html($field['title']);?>:</label><br />
					<?php if(isset($field['eg'])){ ?> <small><?php echo $field['eg'];?></small> <br/> <?php } ?>
					<input type="text" id="<?php echo esc_attr($key);?>" value="" size="20" name="<?php echo esc_attr($key);?>" />

				</div>
			<?php } ?>

			<div class="membership_form_element"><label for="member_comments">Comments or Suggestions:</label><br />
				<textarea id="member_comments" rows="7" cols="80" name="member_comments"></textarea>
			</div>


			<div class="membership_form_element"><input type="submit" value="Submit" tabindex="6" id="submit" name="submit" /></div>

		</form>
		<?php
		return ob_get_clean();
	}

}
new TechSpace_Frontend_Submit();