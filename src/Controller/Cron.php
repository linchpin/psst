<?php

namespace Psst\Controller;

/**
 * Class Cron
 * @package Psst\Controller
 */
class Cron {

	public function register_actions() {
		add_action( 'secret_purge', [ $this, 'secret_purge' ] );
		add_action( 'secret_expire', [ $this, 'secret_expire' ] );
		add_filter( 'cron_schedules', [ $this, 'cron_schedules' ] );
	}

	/**
	 * Add a 5 minute cron to our schedules
	 *
	 * @param array $schedules
	 *
	 * @return mixed
	 */
	public function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['5min'] ) ) {
			$schedules['5min'] = [
				'interval' => 5 * 60,
				'display'  => esc_html__( 'Once every 5 minutes', 'psst' ),
			];
		}

		return apply_filters( 'psst_cron_schedule', $schedules );
	}

	/**
	 * Delete all secrets older than the oldest time available
	 */
	public function secret_purge() {
		$args = array(
			'post_type'      => 'secret',
			'offset'         => 0,
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'column' => 'post_date',
					'before' => '7 days ago',
				),
			),
		);

		$secrets_query = new \WP_Query( $args );

		while ( $secrets_query->have_posts() ) {
			foreach ( $secrets_query->posts as $post ) {
				wp_trash_post( $post );
			}

			$args['offset'] = $args['offset'] + $args['posts_per_page'];
			$notes_query    = new \WP_Query( $args );
		}

		wp_cache_delete( 'psst-secrets', 'psst' );
	}

	/**
	 * Expire secrets if their expiration date has passed.
	 */
	public function secret_expire() {
		$args = array(
			'post_type'      => 'secret',
			'offset'         => 0,
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_secret_expiration',
					'value'   => current_time( 'timestamp' ),
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			),
		);

		$secrets_query = new \WP_Query( $args );

		while ( $secrets_query->have_posts() ) {
			foreach ( $secrets_query->posts as $post ) {
				wp_update_post(
					array(
						'ID'          => $post,
						'post_status' => 'expired',
					)
				);
			}

			$args['offset'] = $args['offset'] + $args['posts_per_page'];
			$notes_query    = new \WP_Query( $args );
		}

		wp_cache_delete( 'psst-secrets', 'psst' );
	}
}
