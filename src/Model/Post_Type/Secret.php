<?php

namespace Psst\Model\Post_Type;

use \Psst\Model\Config;

class Secret {

	private $config;

	/**
	 * @var string
	 */
	public $name = 'secret';

	/**
	 * @var array|mixed|void
	 */
	private $labels = array();

	/**
	 * @var array|mixed|void
	 */
	private $args = array();

	/**
	 * Secret constructor.
	 */
	public function __construct() {
		$this->config = new Config();

		$default_labels = array(
			'name'                  => _x( 'Secrets', 'General Name', 'psst' ),
			'singular_name'         => _x( 'Secret', 'Singular Name', 'psst' ),
			'menu_name'             => __( 'Secrets', 'psst' ),
			'name_admin_bar'        => __( 'Secret', 'psst' ),
			'archives'              => __( 'Secret Archives', 'psst' ),
			'attributes'            => __( 'Secret Attributes', 'psst' ),
			'parent_item_colon'     => __( 'Parent Secret:', 'psst' ),
			'all_items'             => __( 'All Secrets', 'psst' ),
			'add_new_item'          => __( 'Add New Secret', 'psst' ),
			'add_new'               => __( 'Add New', 'psst' ),
			'new_item'              => __( 'New Secret', 'psst' ),
			'edit_item'             => __( 'Edit Secret', 'psst' ),
			'update_item'           => __( 'Update Secret', 'psst' ),
			'view_item'             => __( 'View Secret', 'psst' ),
			'view_items'            => __( 'View Secrets', 'psst' ),
			'search_items'          => __( 'Search Secrets', 'psst' ),
			'not_found'             => __( 'Not found', 'psst' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'psst' ),
			'featured_image'        => __( 'Featured Image', 'psst' ),
			'set_featured_image'    => __( 'Set featured image', 'psst' ),
			'remove_featured_image' => __( 'Remove featured image', 'psst' ),
			'use_featured_image'    => __( 'Use as featured image', 'psst' ),
			'insert_into_item'      => __( 'Insert into secret', 'psst' ),
			'uploaded_to_this_item' => __( 'Uploaded to this secret', 'psst' ),
			'items_list'            => __( 'Secrets list', 'psst' ),
			'items_list_navigation' => __( 'Secrets list navigation', 'psst' ),
			'filter_items_list'     => __( 'Filter secrets list', 'psst' ),
		);

		$this->labels = apply_filters( 'psst_secret_labels', $default_labels );

		$default_args = array(
			'label'               => __( 'Secret', 'psst' ),
			'description'         => __( 'Secrets', 'psst' ),
			'labels'              => $this->labels,
			'supports'            => array( 'title', 'editor' ),
			'taxonomies'          => array(),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'capability_type'     => 'page',
			'rewrite'             => array(
				'slug'       => 'secret',
				'with_front' => false,
			),

		);

		$this->args = apply_filters( 'psst_secret_args', $default_args );
	}

	/**
	 * Get the arguments for the post type
	 *
	 * @return array|mixed|void
	 */
	public function get_args() {
		return $this->args;
	}
}
