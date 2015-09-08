<?php
/*
Plugin Name: AM2 Vanity URLs
Plugin URI: http://am2studio.hr
Description: AM2 studio vanity URLs
Author: AM2 studio
Version: 1.1
Author URI: http://www.am2studio.hr
*/

// Create custom post status for pages
function am2_custom_status_register(){
	
	register_post_status( 'vanity', array(
		'label'                     => _x( 'Vanity', 'page' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Vanity <span class="count">(%s)</span>', 'Vanity <span class="count">(%s)</span>' ),
	) );

}
add_action( 'init', 'am2_custom_status_register' );

// Add custom post status to Quick Edit dropdown
function am2_status_into_inline_edit() { // ultra-simple example
	echo "<script>
	jQuery(document).ready( function() {
		jQuery( 'select[name=\"_status\"]' ).append( '<option value=\"vanity\">Vanity</option>' );
	});
	</script>";
}
add_action('admin_footer-edit.php','am2_status_into_inline_edit');

// create custom plugin settings menu
add_action('admin_menu', 'am2_vanity_create_menu');

function am2_vanity_create_menu() {

	//create new top-level menu
	//add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	add_submenu_page('options-general.php','Vanity URL Settings', 'Vanity URL Settings', 'administrator', 'am2-vanity-settings-page', 'am2_vanity_settings_page' );

	//call register settings function
	add_action( 'admin_init', 'register_am2_vanity_settings' );
}


function register_am2_vanity_settings() {
	//register our settings
	register_setting( 'am2-vanity-plugin-settings-group', 'vanity_available_post_types' );
	//register_setting( 'am2-vanity-plugin-settings-group', 'some_other_option' );
	//register_setting( 'am2-vanity-plugin-settings-group', 'option_etc' );
}

function am2_vanity_settings_page() {
?>
<div class="wrap">
<h2>Vanity URLs settings page</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'am2-vanity-plugin-settings-group' ); ?>
    <?php do_settings_sections( 'am2-vanity-plugin-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Use Vanity URLs for these custom post types</th>
        <td>
        <?php $vanity_available_post_types = get_option('vanity_available_post_types');
		$available_post_types = get_post_types( array('public' => true), 'objects');
		foreach($available_post_types as $type):
			?>
            	<label><input type="checkbox" name="vanity_available_post_types[]" value="<?php echo $type->name; ?>"<?php if(in_array($type->name,$vanity_available_post_types)) { ?> checked="checked"<?php } ?>  /> <?php echo $type->label; ?></label><br />
			<?php
		endforeach;
		?>
        </td>
        </tr>
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php }

/**
 * Adds a box to the main column on the Post and Page edit screens.
 */
function am2_vanity_add_meta_box() {
	
	$vanity_available_post_types = get_option('vanity_available_post_types');
	$screens = $vanity_available_post_types;

	foreach ( $screens as $screen ) {

		add_meta_box(
			'am2_vanity_sectionid',
			__( 'Vanity URL setup', 'am2_vanity_textdomain' ),
			'am2_vanity_meta_box_callback',
			$screen,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'am2_vanity_add_meta_box' );

/**
 * Prints the box content.
 * 
 * @param WP_Post $post The object for the current post/page.
 */
function am2_vanity_meta_box_callback( $post ) {

	// Add a nonce field so we can check for it later.
	wp_nonce_field( 'am2_vanity_meta_box', 'am2_vanity_meta_box_nonce' );

	/*
	 * Use get_post_meta() to retrieve an existing value
	 * from the database and use the value for the form.
	 */
	$value = get_post_meta( $post->ID, '_am2_vanity_url', true );

	echo '<label for="am2_vanity_url">';
	_e( 'Vanity URL', 'myplugin_textdomain' );
	echo '</label>';
	echo '<input type="text" id="am2_vanity_url" name="am2_vanity_url" value="' . esc_attr( $value ) . '" size="25" />';
}

/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
function am2_vanity_save_meta_box_data( $post_id ) {

	/*
	 * We need to verify this came from our screen and with proper authorization,
	 * because the save_post action can be triggered at other times.
	 */

	// Check if our nonce is set.
	if ( ! isset( $_POST['am2_vanity_meta_box_nonce'] ) ) {
		return;
	}

	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['am2_vanity_meta_box_nonce'], 'am2_vanity_meta_box' ) ) {
		return;
	}

	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

	} else {

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}

	/* OK, it's safe for us to save the data now. */
	
	// Make sure that it is set.
	if ( ! isset( $_POST['am2_vanity_url'] ) ) {
		return;
	}

	// Sanitize user input.
	$am2_vanity_url = sanitize_title( $_POST['am2_vanity_url'] );
	unset($_POST['am2_vanity_url']);
	
	// Handle Vanity Pages
	am2_on_update_vanity($post_id, $am2_vanity_url);

	
}
add_action( 'save_post', 'am2_vanity_save_meta_box_data' );

function am2_on_update_vanity($post_id, $am2_vanity_url){
	
	if(empty($post_id)) 		return;
	
	// If deleting Vanity URL
	if(empty($am2_vanity_url)){
		$previous_vanity_url = get_post_meta( $post_id, '_am2_vanity_url', true );
		if(!empty($previous_vanity_url)){
			$args = array( 'post_type' => 'page', 'post_status' => 'vanity', 'pagename' => $previous_vanity_url, 'meta_query' => array(array('key' => 'vanity_id', 'value' => $post_id)) );
			$vanity_pages = get_posts($args);
			wp_delete_post( $vanity_pages[0]->ID, true );
			
			delete_post_meta( $post_id, '_am2_vanity_url');
			
			return;
		}
	}
	
	//First, check what was the previous vanity URL and delete it if it's not this one
	$previous_vanity_url = get_post_meta( $post_id, '_am2_vanity_url', true ); 
	
	if($previous_vanity_url != $am2_vanity_url){
	
		$args = array( 'post_type' => 'page', 'post_status' => 'vanity', 'pagename' => $previous_vanity_url, 'meta_query' => array(array('key' => 'vanity_id', 'value' => $post_id)) );
		$vanity_pages = get_posts($args); 
		if(!empty($vanity_pages) && count($vanity_pages) == 1) {
			wp_delete_post( $vanity_pages[0]->ID, true );
		}
		
	}

	
	// Now, check if there is already existing Vanity Page or create the new one 
	$args = array( 'post_type' => 'page', 'post_status' => 'vanity', 'pagename' => $am2_vanity_url, 'meta_query' => array(array('key' => 'vanity_id', 'value' => $post_id)) );
	$vanity_pages = get_posts($args);
	if(!empty($vanity_pages)) {
		$final_vanity_url = $vanity_pages[0]->post_name;
	} else {
		$args = array( 
			'post_type' 	=> 'page', 
			'post_status' 	=> 'vanity', 
			'post_name' 	=> $am2_vanity_url,
			'post_title'	=> $am2_vanity_url
		);
		$new_vanity_page_id = wp_insert_post($args);
		$new_vanity_page = get_post($new_vanity_page_id);
		
		update_post_meta($new_vanity_page_id, 'vanity_id', $post_id);
		$final_vanity_url = $new_vanity_page->post_name;
	}

	
	// Finally - Save meta field
	update_post_meta( $post_id, '_am2_vanity_url', $final_vanity_url );
}

function am2_display_vanity_content()
{

    if ('page' === get_post_type() && 'vanity' === get_post_status() && is_page() && !is_admin()) {
        //return print "Yo World!";
		global $post;
		
		
		$vanity_id = get_post_meta($post->ID, 'vanity_id', true); 
		if(empty($vanity_id)) die;
		
		remove_filter('post_link', 'am2_append_query_string'); // We need to remove filters because otherwise we will not get the real URL
		remove_filter('post_type_link', 'am2_append_query_string');
		$vanity_permalink = get_permalink($vanity_id);
		add_filter( 'post_link', 'am2_append_query_string', 10, 3 );
		add_filter( 'post_type_link', 'am2_append_query_string', 10, 3 );
		
		
		$response = wp_remote_get( $vanity_permalink );
if( is_array($response) ) {
  $header = $response['headers']; // array of http header lines
  $body = $response['body']; // use the content
}
		echo $body;
		die;
	}
}
add_action( 'wp', 'am2_display_vanity_content' );

// Change permalinks to Vanity for get_permalink() and the_permalink()
function am2_append_query_string( $url, $post, $leavename ) {
	
	$vanity_available_post_types = get_option('vanity_available_post_types'); 

		if ( in_array($post->post_type, $vanity_available_post_types) ) { 
			if(!empty($post->_am2_vanity_url)){
				return get_bloginfo('home').'/'.$post->_am2_vanity_url; 
			}
		}
		return $url;
	
}
add_filter( 'post_link', 'am2_append_query_string', 10, 3 );
add_filter( 'post_type_link', 'am2_append_query_string', 10, 3 );
