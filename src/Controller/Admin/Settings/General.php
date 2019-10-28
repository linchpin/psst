<?php
/**
 * Control all of our plugin Settings
 *
 * @since   1.0
 * @package Psst\Controller\Admin\Settings
 */

namespace Psst\Controller\Admin\Settings;

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	exit;
}

use \Psst\Controller\Admin\Fields\Fields as Fields;

/**
 * Settings Class.
 */
class General {

	/**
	 * Define our settings page
	 *
	 * @var string
	 */
	public static $settings_page = 'psst';

	/**
	 * Give our plugin a name
	 *
	 * @var string
	 */
	public static $plugin_name = PSST_PLUGIN_NAME;

	/**
	 * Initialize our plugin settings
	 *
	 * @since 1.0
	 */
	public static function register_actions() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings_init' ) );

		add_filter( 'plugin_action_links', array( __CLASS__, 'add_settings_link' ), 10, 5 );
	}

	/**
	 * Add the options page to our settings menu
	 *
	 * @since 1.0
	 */
	public static function add_admin_menu() {
		add_options_page( PSST_PLUGIN_NAME, PSST_PLUGIN_NAME, 'manage_options', self::$settings_page, array( __CLASS__, 'add_settings_page' ) );
	}

	/**
	 * Add settings link
	 *
	 * @since 1.0
	 *
	 * @param array  $actions     Actions.
	 * @param string $plugin_file Plugin file.
	 *
	 * @return array
	 */
	public static function add_settings_link( $actions, $plugin_file ) {
		static $plugin;

		if ( ! isset( $plugin ) ) {
			$plugin = 'psst/psst.php';
		}

		if ( $plugin === $plugin_file ) {

			$settings  = array( 'settings' => '<a href="options-general.php?page=' . esc_attr( self::$settings_page ) . '">' . esc_html__( 'Settings', 'psst' ) . '</a>' );
			$site_link = array( 'faq' => '<a href="https://linchpin.com/plugins/psst/" target="_blank">' . esc_html__( 'FAQ', 'psst' ) . '</a>' );

			$actions = array_merge( $settings, $actions );
			$actions = array_merge( $site_link, $actions );

		}

		return $actions;
	}

	/**
	 * Create our settings section
	 *
	 * @since 1.0
	 *
	 * @param array $args Array of arguments.
	 */
	public static function create_section( $args ) {
		?>
		<div class="gray-bg negative-bg">
			<div class="wrapper">
				<h2 class="color-darkpurple light-weight">
					<?php echo esc_html( $args['title'] ); ?>
				</h2>
			</div>
		</div>
		<?php
	}

	/**
	 * Add all of our settings from the API
	 *
	 * @since 1.0
	 */
	public static function settings_init() {

		// If we have save our settings flush the rewrite rules for our new structure.
		if ( delete_transient( 'psst_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}

		// Setup General Settings.
		self::setup_general_settings();
	}

	/**
	 * Get our general settings registered
	 *
	 * @since 1.0
	 */
	private static function setup_general_settings() {
		$tab_section = 'psst_settings';

		register_setting( $tab_section, $tab_section );

		// Default Settings Section.
		add_settings_section(
			'psst_general_settings_section',
			'',
			array( __CLASS__, 'create_section' ),
			$tab_section
		);

		add_settings_field(
			'crypto_key',
			esc_html__( 'Encryption Key', 'psst' ),
			array( '\Psst\Controller\Admin\Fields\Fields', 'add_cryptofield' ),
			$tab_section,
			'psst_general_settings_section',
			array(
				'field'       => 'crypto_key',
				'section'     => $tab_section,
				'options'     => 'psst_settings',
				'class'       => 'widefat',
				'label'       => esc_html__( 'Encryption Key', 'psst' ),
				'description' => esc_html__( 'We highly recommend adding this encryption key to your wp-config.php instead of storing this information in the database.', 'psst' ),
			)
		);

		/**
		 * Add settings field
		 *
		 * @todo this doesn't do anything yet.
		 */
		add_settings_field(
			'uninstall',
			esc_html__( 'Remove All Data on Uninstall?', 'psst' ),
			array( '\Psst\Controller\Admin\Fields\Fields', 'add_checkbox' ),
			$tab_section,
			'psst_general_settings_section',
			array(
				'field'   => 'uninstall',
				'section' => $tab_section,
				'options' => 'psst_settings',
				'label'   => esc_html__( 'Yes clear data', 'psst' ),
			)
		);
	}

	/**
	 * Add our options page wrapper Form
	 *
	 * @since 1.0
	 */
	public static function add_settings_page() {

		$tabs        = self::get_tabs();
		$default_tab = self::get_default_tab_slug();
		$active_tab  = isset( $_GET['tab'] ) && array_key_exists( sanitize_text_field( wp_unslash( $_GET['tab'] ) ), $tabs ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $default_tab; // phpcs:ignore WordPress.Security.NonceVerification

		require_once PSST_PATH . 'templates/admin/settings.php';
	}

	/**
	 * Allow filtering of the settings tabs
	 *
	 * @since 1.0
	 *
	 * @param array $default_settings Default settings array.
	 *
	 * @return array
	 */
	private static function apply_tab_slug_filters( $default_settings ) {

		$extended_settings[] = array();
		$extended_tabs       = self::get_tabs();

		foreach ( $extended_tabs as $tab_slug => $tab_desc ) {

			$options = isset( $default_settings[ $tab_slug ] ) ? $default_settings[ $tab_slug ] : array();

			$extended_settings[ $tab_slug ] = apply_filters( 'psst_' . $tab_slug, $options );
		}

		return $extended_settings;
	}

	/**
	 * Get the default tab slug
	 *
	 * @since 1.0
	 *
	 * @return mixed
	 */
	public static function get_default_tab_slug() {
		return key( self::get_tabs() );
	}

	/**
	 * Retrieve settings tabs
	 *
	 * @since 1.0
	 *
	 * @return array $tabs Settings tabs
	 */
	public static function get_tabs() {
		$tabs = array(
			'settings'  => array(
				'label'    => esc_html__( 'General Settings', 'psst' ),
				'sub_tabs' => array(),
			),
			'about'     => array(
				'label'    => esc_html__( 'About Psst!', 'psst' ),
				'sub_tabs' => array(),
			),
			'new'       => array(
				'label'    => esc_html__( "What's New", 'psst' ),
				'sub_tabs' => array(),
			),
			'changelog' => array(
				'label'    => esc_html__( 'Change Log', 'psst' ),
				'sub_tabs' => array(),
			),
			'linchpin'  => array(
				'label'    => esc_html__( 'About Linchpin', 'psst' ),
				'sub_tabs' => array(),
			),
		);

		return apply_filters( 'psst_settings_tabs', $tabs );
	}

	/**
	 * Build out our submenu if we have one.
	 * Allow for this to be extended by addons.
	 *
	 * @since 1.0
	 *
	 * @param string $parent_tab The parent of the sub tab to retrieve.
	 *
	 * @return mixed
	 */
	public static function get_sub_tabs( $parent_tab ) {

		$sub_tabs = self::get_tabs();

		return $sub_tabs[ $parent_tab ]['sub_tabs'];
	}

	/**
	 * Utility Method to get a request parameter within the admin
	 * Strip it of malicious things.
	 *
	 * @since 1.0
	 *
	 * @param string $key     The parameter key.
	 * @param string $default The default value.
	 *
	 * @return string
	 */
	public static function get_request_param( $key, $default = '' ) {
		// If request not set.
		if ( ! isset( $_REQUEST[ $key ] ) || empty( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $default;
		}

		// It's set, so process it.
		return wp_strip_all_tags( (string) wp_unslash( $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
}
