<?php

/*
Plugin Name: Hide From Search
Description: A plugin to hide posts and pages from the WordPress Search
Author: Myles Taylor
Version: 1.0.0
*/


if (!defined('ABSPATH')) {
	exit;
}


class hide_from_search {
	
	
	public function __construct() {

		// Enqueue Scripts and Styles
		add_action('admin_enqueue_scripts', array($this, 'hfs_enqueue_styles'));

		// Create Admin Page
		add_action('admin_menu', array($this, 'create_settings_page'));
						
		// Add HFS Checkbox to posts
		add_action('add_meta_boxes', array($this, 'hfs_add_custom_checkbox'));
		// Save Checkbox value on update or publish
		add_action('save_post', array($this, 'save_custom_checkbox_meta_box'));

		// Add an action hoo for back-end form submission handling
		add_action('admin_post_hfs_save_checkboxes', array($this, 'handle_checkboxes_form_submission'));

		// Action to exclude posts that are stored in DB Option hfs_posts_to_hide
		add_action('pre_get_posts', array($this, 'exclude_hidden_posts_from_search'));
    
	}



	// Enequeue scripts and styles
	public function hfs_enqueue_styles() {

		$this->all_custom_post_types = get_post_types(array('_builtin'=>false, 'public' => true));
		$this->all_custom_post_types['post'] = 'Default Posts';
		$log_data = print_r($this->all_custom_post_types, true);
		error_log($log_data, 4);

		wp_enqueue_style('hfs_css', plugin_dir_url(__FILE__).'css/hfs.css', array(), '1.0.0', 'all');
		
		wp_enqueue_script('vue', 'https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.min.js', array(), '2.6.14', false);
		wp_enqueue_script('hfs_js', plugin_dir_url(__FILE__).'js/hfs.js', array(), '1.0.0', false);
		// Pass PHP data to JS file
		wp_localize_script('hfs_js', 'hfs_data', array('cpts' => $this->all_custom_post_types));

	}



	// Create Admin page
	public function create_settings_page() {
		$title = 'Hide From Search';
		$menu_title = 'Hide From Search';
		$capability = 'manage_options';
		$slug = 'hide_from_search';
		$callback = array($this, 'hfs_content');
		$icon = 'dashicons-code-standards';
		$position = 100;

		add_menu_page($title, $menu_title, $capability, $slug, $callback, $icon, $position);
	}

	

	public function hfs_content() {
		?>
		<main class="wrap">
			<h2>Hide From Search Settings</h2>			
			<section id="hfs_app">
				<div class="aselect" :data-value="value" :data-list="list">
					<div class="selector" @click="toggle()">
						<div class="label">
							<span>{{ value }}</span>
						</div>
						<div class="arrow" :class="{ expanded : visible }"></div>
						<div :class="{hidden : !visible, visible }">
							<ul>																								
								<li :data-link="key" :class="{ current: key === value }" v-for="(item, key) in list" @click="select(key)">{{ item }}</li>
							</ul>
						</div>
					</div>					
				</div>	
				<div class="hfs-listed-title">Viewing Post Type: {{ displayValue }}</div>			
			</section>
			<section class="hfs_listed_cpts">
				<div class="hfs-inner">
					<?php

						if (isset($_GET['custom_post_type'])) {

							$custom_post_type = sanitize_text_field($_GET['custom_post_type']);

							// Check if the provided CPT exists
							if(post_type_exists($custom_post_type)) {

								// Display each of the posts of the selected CPT
								$hfs_posts = get_posts(array('post_type' => $custom_post_type));

								if (!empty($hfs_posts)) {

									?>
									<form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=hfs_save_checkboxes')); ?>">
										<?php if (isset($_GET['custom_post_type'])) : ?>
									        <input type="hidden" name="custom_post_type" value="<?php echo esc_attr($_GET['custom_post_type']); ?>">
									    <?php endif; ?>
										<div class="hfs-loaded-posts">											
						                    <?php
						                    foreach ($hfs_posts as $hfs_post) {

						                        // Retrieve the current value of the checkbox for each post
						                        $checkbox_value = get_post_meta($hfs_post->ID, 'custom_checkbox', true);
						                        ?>
						                        <div class="hfs-post-type">
						                            <div class="hfs-name">Post Name: <?php echo esc_html($hfs_post->post_title); ?></div>
						                            <div class="hfs-check">
						                                <label for="custom_checkbox_<?php echo esc_attr($hfs_post->ID); ?>">
						                                    <input type="checkbox" id="custom_checkbox_<?php echo esc_attr($hfs_post->ID); ?>" name="custom_checkbox[<?php echo esc_attr($hfs_post->ID); ?>]" value="1" <?php checked(1, $checkbox_value); ?>>
						                                    Hide this post from Search Results
						                                </label>
						                            </div>
						                        </div>
						                        <?php
						                    }
						                    ?>
					                   </div>
					                    <?php wp_nonce_field('hfs_save_checkboxes', 'hfs_checkboxes_nonce'); ?>
					                    <p>
					                        <input type="hidden" name="action" value="hfs_save_checkboxes">
					                        <input type="submit" value="Save" class="button button-primary">
					                    </p>
					                </form>
									<?php
								} else {
									echo '<div class="hfs-no-posts">No posts found for the selected custom post type.</div>';
								}
							} else {
								echo 'Invalid custom post type.';
							}
						} else {
							echo '<p>Select a post type.</p>';
						}
					?>
				</div>
			</section>
		</main>
		<?php
	}

	


  	/*********************************
  	 *
  	 *     Checkbox
  	 * 
  	 *********************************/ 

  	// Add Checkbox to posts
  	public function hfs_add_custom_checkbox() {
  		
  		$custom_post_types = get_post_types(array('_builtin'=> false, 'public'=> true), 'names');

  		foreach ($custom_post_types as $post_type) {
	  		add_meta_box(
	  			'hfs_checkbox', // Unique ID
	  			'Hide From Search', // Title of the meta box
	  			array($this, 'hfs_render_checkbox'), // Callback function to render the content of the meta box
	  			$post_type, // Show the meta box on posts. You can change it to 'page' for pages.
	  			'side', // Context: normal, advanced, or side
	  			'low' // Priority: high, core, default, low
	  		);
	  	}
  	}


  	// Display the checkbox in the meta box
	public function hfs_render_checkbox($post) {
	    // Retrieve the current value of the checkbox
	    $checkbox_value = get_post_meta($post->ID, 'custom_checkbox', true);
	    // Use nonce verification
	    wp_nonce_field('custom_checkbox_nonce', 'custom_checkbox_nonce');

	    // Output the checkbox
	    ?>
	    <label for="custom_checkbox">
	        <input type="checkbox" id="custom_checkbox" name="custom_checkbox" value="1" <?php checked(1, $checkbox_value); ?>>
	        Hide this post from Search Results
	    </label>
	    <?php
	}


  	// Save Checkbox Value on update or publish
	public function save_custom_checkbox_meta_box($post_id) {
	    // Check if the current user is authorized to edit the post
	    if (!current_user_can('edit_post', $post_id)) {
	        return;
	    }

	    // Verify the nonce
	    if (!isset($_POST['custom_checkbox_nonce']) || !wp_verify_nonce($_POST['custom_checkbox_nonce'], 'custom_checkbox_nonce')) {
	        return;
	    }

	    // Check if it's an autosave or bulk edit
	    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
	        return;
	    }

	    // Save the checkbox value
	    $checkbox_value = isset($_POST['custom_checkbox']) ? 1 : 0;
	    update_post_meta($post_id, 'custom_checkbox', $checkbox_value);
	}



	// Save Checkbox Value on form submission
	public function handle_checkboxes_form_submission() {

	    if (isset($_POST['hfs_checkboxes_nonce']) && wp_verify_nonce($_POST['hfs_checkboxes_nonce'], 'hfs_save_checkboxes')) {
	    
	        if (isset($_POST['custom_post_type']) && post_type_exists($_POST['custom_post_type'])) {
	    
	            // Get the existing post IDs from the database
	            $existing_post_ids = get_option('hfs_posts_to_hide', array());

	            // Get all posts of the selected custom post type
	            $hfs_posts = get_posts(array('post_type' => $_POST['custom_post_type']));

	            // Loop through all posts and update their checkbox value
	            foreach ($hfs_posts as $hfs_post) {
	                $post_id = $hfs_post->ID;

	                // Check if the checkbox is checked in the form data
	                $checkbox_value = isset($_POST['custom_checkbox'][$post_id]) ? 1 : 0;

	                if ($checkbox_value === 1) {
	                    // Add the post ID to the existing array if the checkbox is checked
	                    if (!in_array($post_id, $existing_post_ids)) {
	                        $existing_post_ids[] = $post_id;
	                    }
	                } else {
	                    // Remove the post ID from the array if the checkbox is unchecked
	                    $key = array_search($post_id, $existing_post_ids);
	                    if ($key !== false) {
	                        unset($existing_post_ids[$key]);
	                    }
	                }

	                // Update the checkbox value for each post
	                update_post_meta($post_id, 'custom_checkbox', $checkbox_value);

	                // Update the transient to reflect the latest data
					set_transient('hfs_posts_to_hide_cache', $existing_post_ids, HOUR_IN_SECONDS);
	            }

	            // Save the updated post IDs array to the database (serialize it once)
	            update_option('hfs_posts_to_hide', $existing_post_ids);
	        }
	    }

	    // Redirect back to the plugin settings page after saving
	    $redirect_url = add_query_arg(array('page' => 'hide_from_search', 'custom_post_type' => $_POST['custom_post_type']), admin_url('admin.php'));
	    wp_safe_redirect($redirect_url);

	    exit();
	}



  	/*********************************
  	 *
  	 *     Hide posts from Search
  	 * 
  	 *********************************/ 

	// Exclude hidden posts from search query
	public function exclude_hidden_posts_from_search($query) {

	    // Check if the 'hfs_posts_to_hide' option isn't empty
	    $posts_to_hide = get_transient('hfs_posts_to_hide_cache');

	    if ($posts_to_hide === false) {

	        // If the cache is empty or expired, fetch the posts to hide from the database
	        $posts_to_hide = get_option('hfs_posts_to_hide');
	        
	        // Unserialize the value to get the array of post IDs
	        $posts_to_hide = maybe_unserialize($posts_to_hide);

	        // Cache the result for a specific duration (e.g., 1 hour)
	        set_transient('hfs_posts_to_hide_cache', $posts_to_hide, HOUR_IN_SECONDS);
	    }

	    if (!empty($posts_to_hide) && $query->is_main_query() && $query->is_search()) {
	        // Exclude the hidden post IDs from the search query
	        $query->set('post__not_in', $posts_to_hide);
	    }
	}


}


new hide_from_search();