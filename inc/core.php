<?php
/**
 * Main plugin class
 */
class B5F_NDBAE_Main
{
	/**
	 * Plugin instance.
	 * @type object
	 */
	protected static $instance = NULL;

	/**
	 * Holds list of all network blog ids.
	 * @type array
	 */
	public $blogs;

	/**
	 * URL to this plugin's directory.
	 * @type string
	 */
	public $plugin_url;

	/**
	 * Path to this plugin's directory.
	 * @type string
	 */
	public $plugin_path;


	/**
	 * Constructor. Intentionally left empty and public.
	 *
	 * @see plugin_setup()
	 * @since 2012.09.12
	 */
	public function __construct() {}
		

    /**
	 * Access this plugin's working instance.
	 *
	 * @wp-hook plugins_loaded
	 * @since   2012.09.13
	 * @return  object of this class
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;
		return self::$instance;
	}


	/**
	 * Used for regular plugin work, ie, magic begins.
	 *
	 * @wp-hook plugins_loaded
	 * @return  void
	 */
	public function plugin_setup()
	{
		$this->plugin_url    = plugins_url( '/', dirname( __FILE__ ) );
		$this->plugin_path   = plugin_dir_path( dirname( __FILE__ ) );
        $this->get_blogs();
		add_action( 'load-plugins.php', array( $this, 'load_blogs' ) );
        new B5F_General_Updater_and_Plugin_Love(array( 
            'repo' => 'Network-Deactivated-but-Active-Elsewhere', 
            'user' => 'brasofilo',
            'plugin_file' => defined( 'B5F_NDBAE_FILE' ) ? B5F_NDBAE_FILE : '',
            'donate_text' => __( 'Buy me two beers'),
            'donate_icon' => '&hearts; ',
            'donate_link' => 'https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=JNJXKWBYM9JP6&lc=US&item_name=Rodolfo%20Buaiz&item_number=Plugin%20donation&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donate_LG%2egif%3aNonHosted'
        ));
	}

    
    
	/**
	 * Dispatch all actions.
	 * Store all blog IDs in $this->blogs.
	 *
	 * @wp-hook load-{$pagenow}
	 * @return void
	 */
	public function load_blogs()
	{ 
		// Load only in /wp-admin/network/
		global $current_screen;
		if( !$current_screen->is_network )
			return;
		
		add_action( 
				'network_admin_plugin_action_links', 
				array( $this, 'list_plugins' ), 
				10, 4 
		);
		add_action(
				'admin_enqueue_scripts',
				array( $this, 'enqueue')
		);
		add_filter( 
				'views_plugins-network', 
				array( $this, 'inactive_views' ), 
				10, 1 
		);

	}
	
	/**
	 * Enqueue script and style.
	 * 
	 * @wp-hook admin_enqueue_scripts
	 */
	public function enqueue()
	{
		wp_enqueue_script( 
				'ndbae-js', 
				$this->plugin_url . 'js/ndbae.js', 
				array(), 
				false, 
				true 
		);
        wp_enqueue_style( 
				'ndbae-css', 
				$this->plugin_url . 'css/ndbae.css'
		);

	}
	
	/**
	 * Button to show/hide locally active plugins in the screen "Inactive plugins"
	 * 
	 * @wp-hook views_plugins-network
	 * @param array $views
	 * @return array
	 */
	public function inactive_views( $views ) 
	{
		if( 
			isset( $_GET['plugin_status'] ) 
			&& in_array( $_GET['plugin_status'], array('inactive','all') ) 
		)
			$views['metakey'] = '<label><input type="checkbox" id="hide_network_but_local"> Hide locally active plugins</label>';
		return $views;
	}
	
	/**
	 * Each plugin row action links. Check if active is any site. If so, mark it.
	 *
	 * @wp-hook network_admin_plugin_action_links
	 * @return array
	 */
	public function list_plugins( $actions, $plugin_file, $plugin_data, $context )
	{
		$check_plugin = $this->get_network_plugins_active( $plugin_file );
		if( !empty( $check_plugin ) )
		{
			$class = isset( $actions['deactivate'] ) ? 'red-blogs' : 'blue-blogs';
			$separator = ' - - ';
			$sites_list = 
				'[-' 
				. implode( $separator, $check_plugin ) 
				. '-]';
			$actions[] = "<a href='#' 
				title='$sites_list' 
				class='ndbae-act-link add-new-h2 $class'>Active Elsewhere</a>";
		}
		return $actions;
	}

	/**
	 * Check if plugin is active in any blog
	 * 
	 * @param string $plug
	 * @return boolean
	 */
	private function get_network_plugins_active( $plug )
	{
		$active_in_blogs = array();
		foreach( $this->blogs as $blog )
		{
			$the_plugs = get_blog_option( $blog['blog_id'], 'active_plugins' );
			foreach( $the_plugs as $value )
			{
				if( $value == $plug )
					$active_in_blogs[] = $blog['name'];
			}
		}
		return $active_in_blogs;
	}
	

    private function get_blogs()
    {
        $this->blogs = get_transient( 'ndbae_blogs' );
        if( !$this->blogs )
        {
            // Store all blogs IDs
            global $wpdb;
            $blogs = $wpdb->get_results(
                    " SELECT blog_id, domain 
                    FROM {$wpdb->blogs}
                    WHERE site_id = '{$wpdb->siteid}'
                    AND spam = '0'
                    AND deleted = '0'
                    AND archived = '0' "
            );	
            $this->blogs = array();
            foreach( $blogs as $blog )
            {
                $this->blogs[] = array(
                    'blog_id' => $blog->blog_id,
                    'name'    => get_blog_option( $blog->blog_id, 'blogname' )
                );
            }
            $expiration = apply_filters( 'b5f_ndbae_transient_expiration', 60*60*24 );
            set_transient( 'ndbae_blogs', $this->blogs, $expiration );
        }
    }

} 