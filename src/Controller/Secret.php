<?php

namespace Psst\Controller;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Psst\Helper\Utils;
use Psst\Model\Post_Type\Secret as Secret_Post_Type;
use Psst\Model\Secret as Secret_Model;
use Psst\Core\View;

/**
 * Class Secret
 * @package Psst\Controller
 */
class Secret {

	public function register_actions() {
		add_action( 'init', [ $this, 'register_custom_post_type' ] );
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'cmb2_admin_init', [ $this, 'register_fields' ] );

		// Handle Secret Creation
		add_action( 'admin_post_psst_create_secret', [ $this, 'create_secret' ] );
		add_action( 'admin_post_nopriv_psst_create_secret', [ $this, 'create_secret' ] );

		// Handle Secret Deletion
		add_action( 'admin_post_psst_delete_secret', [ $this, 'delete_secret' ] );
		add_action( 'admin_post_nopriv_psst_delete_secret', [ $this, 'delete_secret' ] );

		// Handle Secret View
		add_action( 'admin_post_psst_view_secret', [ $this, 'view_secret' ] );
		add_action( 'admin_post_nopriv_psst_view_secret', [ $this, 'view_secret' ] );

		add_action( 'wp', [ $this, 'track_viewed_secret' ] );

		add_action( 'the_post', [ $this, 'the_post' ] );
		add_action( 'loop_end', [ $this, 'loop_end' ] );

		add_action( 'pre_get_posts', [ $this, 'display_confirmation' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 11 );
		add_action( 'after_setup_theme', [ $this, 'add_editor_styles' ] );

		// Filters
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_filter( 'post_password_required', [ $this, 'skip_password_on_confirm' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'display_secret_content' ], 2, 1 );

		// Shortcodes
		add_shortcode( 'secret_form', [ $this, 'secret_form' ] );
	}

	/**
	 * Add or remove the wpautop filter
	 *
	 * @since 1.0.0
	 * @param mixed $post Current Post Object.
	 */
	public function the_post( $post ) {

		if ( 'secret' !== $post->post_type ) {
			return;
		}

		remove_filter( 'the_content', 'wpautop' );
	}

	/**
	 * Reset everything back to normal
	 */
	public function loop_end() {
		if ( ! has_filter( 'the_content', 'wpautop' ) ) {
			add_filter( 'the_content', 'wpautop' );
		}
	}

	/**
	 * Add custom editor styles when creating a new secret
	 *
	 * @todo this isn't working just yet.
	 *
	 * @since 1.0.0
	 */
	public function add_editor_styles() {
		$font_url = str_replace( ',', '%2C', '//fonts.googleapis.com/css?family=Lato:300,400,700' );
		add_editor_style( $font_url );
	}

	/**
	 * Enqueue our scripts but only on secrets
	 * @since 1.0.0
	 */
	public function wp_enqueue_scripts() {

		global $post;

		if ( is_single() && 'secret' === $post->post_type ) {
			wp_enqueue_script( 'clipboard', PSST_PLUGIN_URL . 'js/clipboard.min.js', [], PSST_VERSION, true );
			wp_enqueue_script( 'psst', PSST_PLUGIN_URL . 'js/psst.js', [ 'clipboard' ], PSST_VERSION, true );
		}
	}

	/**
	 * Check to see if we are viewing a secret creation confirmation page.
	 *
	 * @since  1.0.4
	 * @return bool
	 */
	private function is_confirmation() {

		global $post;

		if ( 'secret' === $post->post_type &&
			is_single() &&
			in_the_loop() &&
			is_main_query() &&
			get_query_var( 'confirm_secret_key' )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Check to see if we are viewing the "click to view" page.
	 *
	 * Usage: This is used to determine if we are viewing the click to view page
	 * vs the actual secret.
	 *
	 * @since  1.0.4
	 * @return bool
	 */
	private function is_click_to_view() {
		global $post;

		if ( 'secret' === $post->post_type &&
			is_single() &&
			in_the_loop() &&
			is_main_query() &&
			get_query_var( 'confirm_secret_click' )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Check to see if we clicked the view secret button
	 *
	 * @since  1.0.4
	 * @return bool
	 */
	private function can_view_secret() {
		global $post;

		if ( 'secret' === $post->post_type &&
			is_single() &&
			in_the_loop() &&
			is_main_query() &&
			'true' === get_query_var( 'confirm_secret_view' )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Determine when to show the confirmation page, view secret confirmation or the secret itself.
	 *
	 * @since 1.0.0
	 */
	public function display_secret_content( $content ) {

		global $post;

		// If it's not a secret then don't filter anything
		if ( 'secret' !== $post->post_type ) {
			return $content;
		}

		if ( $this->is_confirmation() ) {

			wp_enqueue_script( 'clipboard', PSST_PLUGIN_URL . 'js/clipboard.min.js', [], PSST_VERSION, true );

			$confirmation = new View();
			$timestamp    = get_post_meta( $post->ID, '_psst_secret_expiration', true );
			$date         = date_i18n(
				get_option( 'date_format' ),
				$timestamp
			);
			$time         = date_i18n(
				get_option( 'time_format' ),
				$timestamp
			);

			$datetime = sprintf( '%1$s @ %2$s', $date, $time );
			$datetime = apply_filters( 'psst_date_time_format', $datetime );

			$confirmation->assign( 'secret_expiration_date', $datetime );
			$confirmation->assign( 'secret_confirm_key', get_post_meta( $post->ID, '_psst_secret_confirm_key', true ) );

			return $confirmation->get_text_view( 'secret-confirmation' );
		}

		$refresh_warning = '';

		if ( $this->can_view_secret() ) {
			if ( ! post_password_required() ) {
				$key     = Key::loadFromAsciiSafeString( PSST_CRYPTO_KEY );
				$content = Crypto::decrypt( $content, $key );

				$warning         = new View();
				$refresh_warning = $warning->get_text_view( 'secret-refresh-warning' );
				$refresh_warning = apply_filters( 'psst_refresh_warning', $refresh_warning );
			}
		} else {
			// Show the OK button
			if ( ! post_password_required() ) {
				$secret_view = new View();
				$content     = $secret_view->get_text_view( 'secret' );
				$content     = apply_filters( 'psst_secret_view', $content );
			}
		}

		$content = $content . $refresh_warning;

		return $content;
	}

	/**
	 * Display the confirmation
	 *
	 * @param $query
	 */
	public function display_confirmation( $query ) {

		$secret_confirm_key = get_query_var( 'confirm_secret_key' );

		if ( ! is_admin() &&
			( $query->is_main_query() &&
				'true' === get_query_var( 'confirm_secret' ) &&
				'secret' === $query->query_vars['post_type'] ) ) {

			if ( ! empty( $secret_confirm_key ) ) {

				$meta_query = [
					[
						'key'     => '_psst_secret_confirm_key',
						'value'   => $secret_confirm_key,
						'compare' => '=',
					],
				];

				$query->is_single     = true;
				$query->is_home       = false;
				$query->is_front_page = false;
				$query->set( 'meta_query', $meta_query );
			}
		}
	}

	/**
	 * Do not password protect on the confirmation view.
	 *
	 * @param $protect
	 * @param $post
	 *
	 * @return mixed
	 */
	public function skip_password_on_confirm( $protect, $post ) {

		if ( ! get_query_var( 'confirm_secret' ) ) {
			return $protect;
		}

		if ( ! is_404() && 'secret' !== $post->post_type ) {
			return $protect;
		}
	}

	/**
	 * Allow for a new custom query var
	 *
	 * @param $qvars
	 *
	 * @return array
	 */
	public function query_vars( $qvars ) {
		$qvars[] = 'confirm_secret';
		$qvars[] = 'confirm_secret_key';
		$qvars[] = 'confirm_secret_click';
		$qvars[] = 'confirm_secret_view';
		return $qvars;
	}

	/**
	 * Track that a secret has been viewed so it can be deleted.
	 * Be sure to exclude if you are viewing the password protected form.
	 *
	 * @since 1.0.0
	 */
	public function track_viewed_secret() {

		global $post;

		if ( is_admin() ) {
			return;
		}

		if ( $post && 'secret' !== $post->post_type ) {
			return;
		}

		// 'Slackbot-LinkExpanding 1.0'

		// If the post isn't protected, delete it after it's been viewed.
		// Also make sure that we aren't viewing the confirmation page.
		if ( ! post_password_required() &&
			'true' !== get_query_var( 'confirm_secret' ) &&
			'true' === get_query_var( 'confirm_secret_view' ) &&
			! is_404() ) {

			wp_delete_post( $post->ID, true );
		}
	}

	/**
	 * Create custom rewrite rule for secrets.
	 *
	 * @since 1.0.0
	 */
	public function add_rewrite_rules() {
		add_rewrite_tag( '%secret_id%', '([0-9A-Za-z]+)' );
		add_rewrite_tag( '%confirm_secret_key%', '([0-9A-Za-z]+)' );
		add_rewrite_rule( 'secret/confirm/(.*)/?', 'index.php?&post_type=secret&confirm_secret=true&confirm_secret_key=$matches[1]', 'top' );
		add_rewrite_rule( 'secret/view/(.*)/?', 'index.php?&secret=$matches[1]&confirm_secret_view=true', 'top' );
		add_rewrite_rule( 'secret/(.*)/?', 'index.php?&secret=$matches[1]&confirm_secret_click=true', 'top' );
		add_rewrite_rule( 'secret/removed/?', 'index.php?&removed_secret=true', 'top' );
	}

	/**
	 * Register the 'secret' post type
	 *
	 * @wp-hook init
	 * @since 1.0.0
	 */
	public function register_custom_post_type() {
		$post_type_model = new Secret_Post_Type();
		register_post_type( $post_type_model->name, $post_type_model->get_args() );
	}

	/**
	 * Register the CMB2 meta fields
	 *
	 * @wp-hook cmb2_admin_init
	 * @since 1.0.0
	 */
	public function register_fields() {
		$model = new Secret_Model();

		$meta = $model->get_meta();

		// Loop over each metabox
		foreach ( $meta as $key => $value ) {
			$metabox = new_cmb2_box( $meta[ $key ]['metabox'] );

			// Loop over each field for the metabox
			foreach ( $meta[ $key ]['fields'] as $field ) {
				$metabox->add_field( $field );
			}
		}
	}

	/**
	 * Render our secret form shortcode
	 * @since 1.0.0
	 *
	 * @param array  $atts
	 * @param string $content
	 *
	 * @return string
	 */
	public function secret_form( $atts = [], $content = '' ) {

		$secret_editor_options = [
			'media_buttons' => false,
			'teeny'         => true,
			'editor_height' => 180,
			'quick_tags'    => false,
			'tinymce'       => [
				'toolbar1'              => 'bold,italic,underline,separator,link,unlink',
				'toolbar2'              => '',
				'toolbar3'              => '',
				'statusbar'             => false,
				'autoresize_min_height' => 180,
				'wp_autoresize_on'      => true,
				'plugins'               => 'wpautoresize',
			],
		];

		$secret_editor_options = apply_filters( 'psst_secrect_editor_options', $secret_editor_options );

		$shortcode = new View();
		$shortcode->assign( 'secret_editor_options', $secret_editor_options );

		return $shortcode->get_text_view( 'secret-form' );
	}

	/**
	 * Create our secret Post on submission
	 * @since 1.0.0
	 */
	public function create_secret() {

		wp_verify_nonce( 'check_secret_nonce', $_POST['check_secret'] );

		$generated_post_name   = Utils::create_unique_slug();
		$generated_confirm_key = Utils::create_unique_slug();

		$key     = Key::loadFromAsciiSafeString( PSST_CRYPTO_KEY );
		$message = Crypto::encrypt( wp_kses( $_POST['secret'], Utils::get_safe_markup() ), $key );

		$secret_post = [
			'post_content' => $message,
			'post_name'    => $generated_post_name,
			'post_title'   => $generated_post_name,
			'post_type'    => 'secret',
			'post_status'  => 'publish',
		];

		if ( isset( $_POST['passphrase'] ) && '' !== $_POST['passphrase'] ) {
			$secret_post['post_password'] = sanitize_text_field( $_POST['passphrase'] );
		}

		$new_secret_id = wp_insert_post( $secret_post );

		if ( ! is_wp_error( $new_secret_id ) ) {

			// Store that we are confirming our secret.
			update_post_meta( $new_secret_id, '_psst_secret_confirm', true );
			update_post_meta( $new_secret_id, '_psst_secret_confirm_key', $generated_confirm_key );

			// If the Expiration is set (As it always should be)
			if ( isset( $_POST['expiration'] ) && '' !== $_POST['expiration'] ) {

				$expiration = (int) $_POST['expiration'];

				$expire_date = new \DateTime();
				date_add( $expire_date, new \DateInterval( "PT{$expiration}M" ) );

				update_post_meta( $new_secret_id, '_psst_secret_expiration', $expire_date->getTimestamp() );
			}

			$confirm_url = site_url( 'secret/confirm/' . $generated_confirm_key );

			wp_safe_redirect( $confirm_url, 301, esc_attr__( 'Psst', 'psst' ) );
			exit();
		}
	}

	/**
	 * Create our secret Post on submission
	 *
	 * @since 1.0.0
	 */
	public function view_secret() {

		wp_verify_nonce( 'view_secret_nonce', $_POST['view_secret_nonce'] );

		$secret_key = trim( $_POST[ '_wp_http_referer' ], '/' ); // Get the key from the referrering page
		$secret_key = explode( '/', $secret_key );
		$secret     = site_url( '/secret/view/' . $secret_key[1] );

		wp_safe_redirect( $secret, 301, esc_attr__( 'Psst', 'psst' ) );
		exit();
	}

	/**
	 * Delete our secret Post on submission
	 * @since 1.0.0
	 */
	public function delete_secret() {

		wp_verify_nonce( 'delete_secret_nonce', $_POST['delete_secret'] );

		if ( ! isset( $_POST['secret_confirm_key'] ) && '' === $_POST['secret_confirm_key'] ) {
			return;
		}

		$secret_args = [
			'post_type'      => 'secret',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_key'       => '_psst_secret_confirm_key',
			'meta_value'     => sanitize_text_field( $_POST['secret_confirm_key'] ),
		];

		$secrets = new \WP_Query( $secret_args );

		if ( $secrets->have_posts() ) {
			$secret_id = $secrets->posts[0]->ID;

			if ( ! empty( $secret_id ) ) {
				$deleted_post = wp_delete_post( $secret_id, true );

				if ( $deleted_post ) {
					wp_safe_redirect( site_url( '/secret/removed/' ), 301, esc_attr__( 'Psst', 'psst' ) );
					exit();
				}
			}
		} else {
			wp_die( 'no secret' );
		}
	}
}
