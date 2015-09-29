<?php
/*
Plugin Name: AM2 Vanity URLs
Plugin URI: http://am2studio.hr
Description: AM2 studio vanity URLs
Author: AM2 studio
Version: 1.2
Author URI: http://www.am2studio.hr
*/

/*
2015-09-08: Fix bug with custom URLs
2015-09-11: Full rewrite! Using add_
*/
function am2_redirect_empty_page(){
	if(is_404()){
        $paged = get_query_var('paged');
        if(!empty($paged) && $paged > 1){
            $uri = $_SERVER['REQUEST_URI'];
            $has_page_pos = strpos($uri,'page');
            $final = substr($uri,0,$has_page_pos);
            $link = get_bloginfo('url').$final;
            wp_redirect($link, '302'); //-> OVO RIJEŠITI$target_id->post_id);
        }
    }
	$vanity_urls = get_option('am2_vanity_urls');
		$uri = $_SERVER['REQUEST_URI'];
		
	foreach($vanity_urls as $vanity):
		$construct_check_uri = '/'.$vanity['context'].'/'.$vanity['original_slug'].'/';
		if($uri == $construct_check_uri){
			$link = site_url().'/'.$vanity['url'].'/';;
			wp_redirect($link, '302');
		}
		
	endforeach;
	
}
add_action('template_redirect', 'am2_redirect_empty_page');

function am2_on_update_vanity($object_id, $am2_vanity_url, $object_context = 'post'){
	
	/*
	Structure:
	[url] 			= url of the vanity URL
	[original_slug] = url of the original post/taxonomy
	[context]		= post/page/category/any type or taxonomy
	[context_id] 	= context id
	*/
	
	if(empty($object_id)) return;
	
	$vanity_urls = array();
	$vanity_urls = get_option('am2_vanity_urls');
	
	if(empty($am2_vanity_url)){
		if(!empty($vanity_urls[$object_context.'_'.$object_id])){
			//if it's deleted we need to unset it and flush rewrite rules.
			unset($vanity_urls[$object_context.'_'.$object_id]);
			update_option('am2_vanity_urls',$vanity_urls);
			flush_rewrite_rules();
			
			return;
		}
	}
	
	if(!empty($am2_vanity_url)){


		$am2_vanity_url = ltrim($am2_vanity_url,'/');
		$am2_vanity_url = rtrim($am2_vanity_url,'/');
		
		$vanity_urls[$object_context.'_'.$object_id]['url'] = $am2_vanity_url;
		
		$original_slug = '';
		$post_types = get_post_types();
		if(in_array($object_context, $post_types)){
			$object = get_post($object_id); 
			$original_slug = $object->post_name;
		}
		
		$taxonomies = get_taxonomies();
		if(in_array($object_context, $taxonomies)){
			
			$object = get_term_by('id', $object_id, $object_context); 
			$original_slug = $object->slug;
		}
		
		$vanity_urls[$object_context.'_'.$object_id]['original_slug'] = $original_slug;
		$vanity_urls[$object_context.'_'.$object_id]['context'] = $object_context;
		$vanity_urls[$object_context.'_'.$object_id]['context_id'] = $object_id;
		
		update_option('am2_vanity_urls',$vanity_urls);
		
		am2_generate_rewrite_rules();		
		flush_rewrite_rules();
	}
	
}

function am2_generate_rewrite_rules(){
	$vanity_urls = get_option('am2_vanity_urls');
	//cleanup context = revision 
	foreach($vanity_urls as $key => $vanity):
		if($vanity['context'] == 'revision'){
			unset($vanity_urls[$key]);
		}
	endforeach;
	update_option('am2_vanity_urls',$vanity_urls);
	
	$post_types = get_post_types();
	$args = array(); 
	$output = 'objects'; // or objects
	$taxonomies = get_taxonomies( $args, $output );
	foreach($vanity_urls as $vanity):
		if(in_array($vanity['context'], $post_types)){
			// It's a post type
			add_rewrite_rule($vanity['url'].'/?$', 'index.php?'.$vanity['context'].'='.$vanity['original_slug'], 'top');
		}	
		if(!empty($taxonomies[$vanity['context']])){ //$taxonomies['category']
				add_rewrite_rule($vanity['url'].'/?$', 'index.php?'.$taxonomies[$vanity['context']]->query_var.'='.$vanity['original_slug'], 'top');
				add_rewrite_rule($vanity['url'].'/page/?([0-9]{1,})/?$', 'index.php?'.$taxonomies[$vanity['context']]->query_var.'='.$vanity['original_slug'].'&paged=$matches[1]', 'top');				
		}
	endforeach;	
}

function am2_add_rewrite_rules_to_init() { 
    am2_generate_rewrite_rules();
}
add_action( 'init', 'am2_add_rewrite_rules_to_init' );


// create custom plugin settings menu

function am2_vanity_create_menu() {
	//create new top-level menu
	//add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	add_submenu_page('options-general.php','Vanity URL Settings', 'Vanity URL Settings', 'administrator', 'am2-vanity-settings-page', 'am2_vanity_settings_page' );
	//call register settings function
	add_action( 'admin_init', 'register_am2_vanity_settings' );
}
add_action('admin_menu', 'am2_vanity_create_menu');

function register_am2_vanity_settings() {
	//register our settings
	register_setting( 'am2-vanity-plugin-settings-group', 'vanity_available_post_types' );
	register_setting( 'am2-vanity-plugin-settings-group', 'vanity_available_taxonomies' );
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
    <?php 
	$taxonomies = get_taxonomies(); 
	unset($taxonomies['nav_menu']);
	unset($taxonomies['link_category']);
	unset($taxonomies['post_format']);
	?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Use Vanity URLs for these taxonomies</th>
        <td>
        <?php $vanity_available_post_types = get_option('vanity_available_taxonomies');
		$available_taxonomies = $taxonomies;
		foreach($available_taxonomies as $key => $value):
			?>
            	<label><input type="checkbox" name="vanity_available_taxonomies[]" value="<?php echo $key; ?>"<?php if(in_array($key,$vanity_available_post_types)) { ?> checked="checked"<?php } ?>  /> <?php echo $value; ?></label><br />
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

	$value = '';
	$vanity_urls = get_option('am2_vanity_urls');
	if(!empty($vanity_urls[$post->post_type."_".$post->ID]['url'])){
		$value = $vanity_urls[$post->post_type."_".$post->ID]['url'];
	}
	
	echo '<label for="am2_vanity_url">';
	_e( 'Vanity URL', 'myplugin_textdomain' );
	echo '</label>';
	echo site_url().'/<br><input type="text" id="am2_vanity_url" name="am2_vanity_url" value="' . esc_attr( $value ) . '" size="25" />';
}

function am2_vanity_save_meta_box_data( $post_id ) {
	// Check if our nonce is set.
	if ( ! isset( $_POST['am2_vanity_meta_box_nonce'] ) ) return;
	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['am2_vanity_meta_box_nonce'], 'am2_vanity_meta_box' ) ) return;
	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

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
	// Sanitize user input.
	//$am2_vanity_url = sanitize_url( $_POST['am2_vanity_url'] );
	$am2_vanity_url = $_POST['am2_vanity_url'];
	unset($_POST['am2_vanity_url']);
	
	$post_type = get_post_type($post_id);
	if($post_type == 'revision') continue;
	// Handle Vanity Pages
	am2_on_update_vanity($post_id, $am2_vanity_url, $post_type);	
}
add_action( 'save_post', 'am2_vanity_save_meta_box_data' );


// Change permalinks to Vanity for get_permalink() and the_permalink()
function am2_append_query_string( $url, $post, $leavename ) {
	
	
	$vanity_urls = get_option('am2_vanity_urls'); 
	if(!empty($vanity_urls[$post->post_type."_".$post->ID]['url'])){
		$url = site_url().'/'.$vanity_urls[$post->post_type."_".$post->ID]['url'];
	}
    return $url; 
	
}
add_filter( 'post_link', 'am2_append_query_string', 10, 3 );
add_filter( 'post_type_link', 'am2_append_query_string', 10, 3 );


function am2_vanity_term_link_filter( $url, $term, $taxonomy ) {
	$vanity_urls = get_option('am2_vanity_urls'); 
	if(!empty($vanity_urls[$taxonomy."_".$term->term_id]['url'])){
		$url = site_url().'/'.$vanity_urls[$taxonomy."_".$term->term_id]['url'];
	}
    return $url;  
}
add_filter('term_link', 'am2_vanity_term_link_filter', 10, 3);

// 2015-09-08: Added fields to category pages

function am2_vanity_add_taxonomy_fields( $term ) {    //check for existing featured ID
    $t_id = $term->term_id;
    $vanity_urls = get_option('am2_vanity_urls');
	$value = '';
	if(!empty($vanity_urls[$_REQUEST['taxonomy']."_".$term->term_id]['url'])){
		$value = $vanity_urls[$_REQUEST['taxonomy']."_".$term->term_id]['url'];
	}
?>
<tr class="form-field">
	<th scope="row" valign="top"><label for="_am2_vanity_url"><?php _e('Vanity URL'); ?></label></th>
	<td>
    	<?php echo site_url().'/'; ?>
		<input type="hidden" name="_am2_vanity_term_name" value="<?php echo $_REQUEST['taxonomy']; ?>" />
        <input type="text" name="_am2_vanity_url" id="_am2_vanity_url" placeholder="any-url" size="3" style="width:60%;" value="<?php echo $value; ?>"><br />
        <span class="description"><?php _e('This URL will replace the dafault category URL with new one.'); ?></span>
    </td>
</tr>
<?php
}
function am2_vanity_save_taxonomy( $term_id ) {
	am2_on_update_vanity($term_id, $_POST['_am2_vanity_url'], $object_context = $_POST['_am2_vanity_term_name']);
}

$available_taxonomies = get_option('vanity_available_taxonomies');
foreach($available_taxonomies as $key => $value):
	add_action ( 'create_'.$value, 'am2_vanity_save_taxonomy', 10, 2 );
	add_action ( 'edited_'.$value, 'am2_vanity_save_taxonomy', 10, 2 );
	add_action ( 'edit_'.$value.'_form_fields', 'am2_vanity_add_taxonomy_fields' );
endforeach;