<?php
/*
Plugin Name: Better Github Gists Widget
Description: A highly configurable github gists widget.
Version: 1.0
Author: Andrius Virbičianskas
Author URI: http://a.ndri.us/
Author Email: a@ndri.us
Text Domain: better-github-gists-widget-locale
Domain Path: /lang/
Network: false
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2013  Andrius Virbičianskas (a@ndri.us)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Better_Github_Gists_Widget extends WP_Widget {

	/*--------------------------------------------------*/
	/* Constructor
	/*--------------------------------------------------*/

	/**
	 * Specifies the classname and description, instantiates the widget,
	 * loads localization files, and includes necessary stylesheets and JavaScript.
	 */
	public function __construct() {

		// load plugin text domain
		add_action( 'init', array( $this, 'widget_textdomain' ) );

		// Hooks fired when the Widget is activated and deactivated
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		parent::__construct(
			'better-github-gists-widget-id',
			__( 'Better Github Gists Widget', 'better-github-gists-widget-locale' ),
			array(
				'classname'		=>	'better-github-gists-widget-class',
				'description'	=>	__( 'A highly configurable github gists widget.', 'better-github-gists-widget-locale' )
			)
		);

		// Register admin styles and scripts
		add_action( 'admin_print_styles', array( $this, 'register_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );

		// Register site styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_scripts' ) );

	} // end constructor

	/*--------------------------------------------------*/
	/* Widget API Functions
	/*--------------------------------------------------*/

	/**
	 * Outputs the content of the widget.
	 *
	 * @param	array	args		The array of form elements
	 * @param	array	instance	The current instance of the widget
	 */
	public function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );

		$username = $instance['username'];
		$count = $instance['count'];
		$length = $instance['length'];
		$show_comments = $instance['show_comments'];
		$show_date = $instance['show_date'];
		$show_icons = $instance['show_icons'];

		if ( false === ( $data = get_transient( 'better_github_gists_widget_' . $username ) ) ) {
			$request	= new WP_Http;
			$url		= 'https://api.github.com/users/' . urlencode( $username ) . '/gists?&per_page=' . $count;
			$data		= wp_remote_get( $url );

			set_transient( 'better_github_gists_widget_' . $username, $data, 60 * 60 );
		}

		$gist_list = json_decode( $data['body'] );

		echo $before_widget;

		if ( true === empty( $instance['title'] ) ) {
			$title = '';
		} else {
			$title = apply_filters( 'widget_title', $instance['title'] );
		}
		
		if ( true === !empty( $title ) ) {
			echo $before_title . $title . $after_title; 
		}

		$list = array();
        $extention_whitelist = array('C#', 'C', 'C++', 'ColdFusion', 'CSS', 'HTML', 'Java', 'JavaScript', 'Lua', 'PHP', 'Python', 'Ruby', 'SQL', 'XML');

		foreach ( $gist_list as $gist ) {
			$types		= array();
			$filenames	= '';

			foreach ( $gist->files as $filename => $filedata ) {
				$filenames .= $filename . ', ';

				if ( true === in_array( $filedata->language, $extention_whitelist ) && false === in_array( $filedata->language, $types )) {
					array_push( $types, str_replace( '#', '_sharp', $filedata->language ) );
				}
			}

			if ( null === $gist->description ) {
				$description = mb_substr( $filenames, 0, -2 );
			} else {
				$description = $gist->description;
			}

			if ( $length < mb_strlen( $description ) ) {
				$description_short = mb_substr( $description, 0, $length ) . '...';
			} else {
				$description_short = $description;
			}

			$comments = $gist->comments;

			if ( $comments > 1 ) {
				$output = str_replace( '%', number_format_i18n( $comments ), ( false === '% comments' ) ? __( '% Comments' ) : '% comments' );
			} elseif ( 0 === $comments ) {
				$output = ( false === '0 comments' ) ? __(' No Comments' ) : '0 comments';
			} else {
				$output = ( false === '1 comment' ) ? __( '1 Comment' ) : '1 comment';
			}
			
			array_push(
				$list,
				array(
				'description' => $description,
				'description_short' => $description_short,
				'gist_id' => $gist->id,
				'url' => $gist->html_url,
				'created' => date_i18n( get_option( 'date_format' ), strtotime( $gist->created_at ) ),
				'comments' => apply_filters( 'comments_number', $output, $comments ),
				'types' => $types,
				)
			);
		}

		include( plugin_dir_path( __FILE__ ) . '/views/widget.php' );

		echo $after_widget;

	} // end widget

	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param	array	new_instance	The previous instance of values before the update.
	 * @param	array	old_instance	The new instance of values to be generated via the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		$instance['title']			= strip_tags($new_instance['title']);
        $instance['username']		= strip_tags($new_instance['username']);
        $instance['count']			= strip_tags($new_instance['count']);
		$instance['length']			= strip_tags($new_instance['length']);
		$instance['show_comments']	= (true === !empty($new_instance['show_comments'])) ? true : false;
		$instance['show_date']		= (true === !empty($new_instance['show_date'])) ? true : false;
		$instance['show_icons']		= (true === !empty($new_instance['show_icons'])) ? true : false;
		
		return $instance;

	} // end widget

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param	array	instance	The array of keys and values for the widget.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args(
			(array) $instance,
			array(
			'title'			=> 'My Github Gists',
			'username'		=> '',
			'count'			=> 5,
			'length'		=> 70,
			'show_date'		=> true,
			'show_comments'	=> true,
			'show_icons'	=> true,
			)
		);

		$title			= $instance['title'];
		$username		= $instance['username'];
		$count			= $instance['count'];
		$length			= $instance['length'];
		$show_date		= $instance['show_date'];
		$show_comments	= $instance['show_comments'];
		$show_icons		= $instance['show_icons'];
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>

		<p>
			<label for="<?php echo $this->get_field_id( 'username' ); ?>"><?php _e( 'Github username' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'username' ); ?>" name="<?php echo $this->get_field_name( 'username' ); ?>" type="text" value="<?php echo esc_attr( $username ); ?>" />
        </p>

		<p>
			<label for="<?php echo $this->get_field_id( 'count' ); ?>"><?php _e( 'Number of gists to show:' ); ?></label>
			<input size="3" id="<?php echo $this->get_field_id( 'count' ); ?>" name="<?php echo $this->get_field_name( 'count' ); ?>" type="text" value="<?php echo esc_attr( $count ); ?>" />
        </p>

		<p>
			<label for="<?php echo $this->get_field_id( 'length' ); ?>"><?php _e( 'Length of the description:' ); ?></label>
			<input size="3" id="<?php echo $this->get_field_id( 'length' ); ?>" name="<?php echo $this->get_field_name( 'length' ); ?>" type="text" value="<?php echo esc_attr( $length ); ?>" />
        </p>

		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_comments, true) ?> id="<?php echo $this->get_field_id( 'show_comments' ); ?>" name="<?php echo $this->get_field_name( 'show_comments' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'show_comments' ); ?>"><?php _e( 'Show comment count' ); ?></label>
        </p>

		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_date, true) ?> id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Show creation date' ); ?></label>
        </p>

		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_icons, true) ?> id="<?php echo $this->get_field_id( 'show_icons' ); ?>" name="<?php echo $this->get_field_name( 'show_icons' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'show_icons' ); ?>"><?php _e( 'Show icons' ); ?></label>
        </p>

		<?php

		// Display the admin form
		include( plugin_dir_path(__FILE__) . '/views/admin.php' );

	} // end form

	/*--------------------------------------------------*/
	/* Public Functions
	/*--------------------------------------------------*/

	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function widget_textdomain() {

		load_plugin_textdomain( 'better-github-gists-widget-locale', false, plugin_dir_path( __FILE__ ) . '/lang/' );

	} // end widget_textdomain

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param		boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public function activate( $network_wide ) {

	} // end activate

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {

	} // end deactivate

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {

		wp_enqueue_style( 'better-github-gists-widget-admin-styles', plugins_url( 'better-github-gists-widget/css/admin.css' ) );

	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {

		wp_enqueue_script( 'better-github-gists-widget-admin-script', plugins_url( 'better-github-gists-widget/js/admin.js' ), array('jquery') );

	} // end register_admin_scripts

	/**
	 * Registers and enqueues widget-specific styles.
	 */
	public function register_widget_styles() {

		wp_enqueue_style( 'better-github-gists-widget-widget-styles', plugins_url( 'better-github-gists-widget/css/widget.css' ) );

	} // end register_widget_styles

	/**
	 * Registers and enqueues widget-specific scripts.
	 */
	public function register_widget_scripts() {

		wp_enqueue_script( 'better-github-gists-widget-script', plugins_url( 'better-github-gists-widget/js/widget.js' ), array('jquery') );

	} // end register_widget_scripts

} // end class

add_action( 'widgets_init', create_function( '', 'register_widget("Better_Github_Gists_Widget");' ) );
