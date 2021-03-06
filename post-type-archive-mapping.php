<?php
/*
Plugin Name: Archive Mapping and Post Selector Gutenberg Block
Plugin URI: https://mediaron.com/portfolio/post-type-archive-mapping/
Description: Map your post type archives to a page and use our Gutenberg block to show posts
Author: Ronald Huereca
Version: 2.0.5
Requires at least: 5.0
Author URI: https://mediaron.com
Contributors: ronalfy
Text Domain: post-type-archive-mapping
Domain Path: /languages
Credit: Forked from https://github.com/bigwing/post-type-archive-mapping
Credit: Gutenberg block based on Atomic Blocks
*/
define( 'PTAM_VERSION', '2.0.5' );

class PostTypeArchiveMapping {
	private static $instance = null;
	private $paged = null;
	private $paged_reset = false;

	/**
	 * Return an instance of the class
	 *
	 * Return an instance of the PostTypeArchiveMapping Class.
	 *
	 * @since 1.0.0
	 * @access public static
	 *
	 * @return PostTypeArchiveMapping class instance.
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	} //end get_instance

	/**
	 * Class constructor.
	 *
	 * Initialize plugin and load text domain for internationalization
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ), 9 );
		load_plugin_textdomain( 'post-type-archive-mapping', false, basename( dirname( __FILE__ ) ) . '/languages' );
		include 'blocks/class-post-type-select-posts.php';
	} //end constructor

	/**
	 * Main plugin initialization
	 *
	 * Initialize admin menus, options,and scripts
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @see __construct
	 *
	 */
	public function init() {
		//Admin Settings
		add_action( 'admin_init', array( $this, 'init_admin_settings' ) );
		add_action( 'pre_get_posts', array( $this, 'maybe_override_archive' ) );
	} //end init

	public function maybe_override_archive( $query ) {
		if( is_admin() ) {
			return $query;
		}

		// Maybe Redirect
		if( is_page() ) {
			$object_id = get_queried_object_id();
			$meta = get_post_meta( $object_id, '_post_type_mapped', true );
			if( $meta ) {
				wp_redirect( get_post_type_archive_link( $meta ) );
				exit();
			} else {
				if( get_query_var('paged' ) ) {
					$query->set( 'paged', get_query_var('paged' ) );
				}
				return;
			}
		}

		// trigger this once after running the main query.
		if ( true === $this->paged_reset ) {
			$query->set( 'paged', $this->paged );
			set_query_var( 'paged', $this->paged );

			$this->paged_reset = false;
		}

		$post_types = get_option( 'post-type-archive-mapping', array() );
		if ( empty( $post_types ) || is_admin() ) {
			return;
		}

		// trigger this the first time to get the current page.
		if ( is_null( $this->paged ) ) {
			$this->paged = get_query_var( 'paged' );
		}

		foreach( $post_types as $post_type => $post_id ) {
			if ( is_post_type_archive( $post_type ) && 'default' != $post_id && $query->is_main_query() ) {
				$post_id = absint( $post_id );
				$query->set( 'post_type', 'page' );
				$query->set( 'page_id', $post_id );
				$query->set( 'paged', $this->paged );
				$query->is_archive = false;
				$query->is_single = true;
				$query->is_singular = true;
				$query->is_post_type_archive = false;

				$this->paged_reset = true;
			}
		}
	}

	public static function get_plugin_dir( $path = '' ) {
		$dir = rtrim( plugin_dir_path(__FILE__), '/' );
		if ( !empty( $path ) && is_string( $path) )
			$dir .= '/' . ltrim( $path, '/' );
		return $dir;
	}
	//Returns the plugin url
	public static function get_plugin_url( $path = '' ) {
		$dir = rtrim( plugin_dir_url(__FILE__), '/' );
		if ( !empty( $path ) && is_string( $path) )
			$dir .= '/' . ltrim( $path, '/' );
		return $dir;
	}

	public function post_type_save( $args ) {
		if( ! is_array( $args ) ) return $args;
		global $wpdb;
		$query = "delete from {$wpdb->postmeta} where meta_key = '_post_type_mapped'";
		$wpdb->query( $query );
		foreach( $args as $post_type => $page_id ) {
			update_post_meta( $page_id, '_post_type_mapped', $post_type );
		}
		return $args;
	}

	/**
	 * Initialize options
	 *
	 * Initialize page settings, fields, and sections and their callbacks
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @see init
	 *
	 */
	public function init_admin_settings() {
		register_setting(
			'reading',
			'post-type-archive-mapping',
			array(
				'sanitize_callback' => array( $this, 'post_type_save' ),
			)
		);

		add_settings_section( 'post-type-archive-mapping', _x( 'Post Type Archive Mapping', 'plugin settings heading' , 'post-type-archive-mapping' ), array( $this, 'settings_section' ), 'reading' );


		add_settings_field(
			'post-type-archive-mapping',
			__( 'Post Type Archive Mapping', 'post-type-archive-mapping' ),
			array( $this, 'add_settings_post_types' ),
			'reading',
			'post-type-archive-mapping'
		);

	}

	function add_settings_post_types( $args ) {
		$output = get_option( 'post-type-archive-mapping', array() );
		$posts = get_posts( array(
			'post_status' => array( 'publish' ),
			'posts_per_page' => 1000,
			'post_type' => 'page',
			'orderby' => 'name',
			'order' => 'ASC'
		) );
		$post_types = get_post_types(
			array(
				'public' => true,
				'has_archive' => true
			)
		);
		foreach( $post_types as $index => $post_type ) {
			$selection = 'default';
			if ( isset( $output[ $post_type ] ) ) {
				$selection = $output[ $post_type ];
			}
			?>
			<p><strong><?php echo esc_html( $post_type ); ?></strong></p>
			<select name="post-type-archive-mapping[<?php echo esc_html( $post_type ); ?>]">
				<option value="default"><?php esc_html_e( 'Default', 'post-type-archive-mapping' ); ?></option>
				<?php
				foreach( $posts as $post ) {
					printf( '<option value="%d" %s>%s</option>', absint( $post->ID ), selected( $selection, $post->ID, false ), esc_html( $post->post_title ) );
				}
				?>
			</select>
			<?php
		}
		?>
		<?php
	}

	/**
	 * Output settings HTML
	 *
	 * Output any HTML required to go into a settings section
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @see init_admin_settings
	 *
	 */
	public function settings_section() {
	}

}

add_action( 'plugins_loaded', function() {
	PostTypeArchiveMapping::get_instance();
} );