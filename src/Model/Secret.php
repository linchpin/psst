<?php

namespace Psst\Model;

class Secret {

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var array
	 */
	private $meta = array();

	/**
	 * Secret constructor.
	 *
	 */
	public function __construct() {
	}

	/**
	 * Returns the metaboxes and associated field data
	 * @return array Metaboxes and fields
	 * @since 1.0.0
	 */
	public function get_meta() {
		return $this->meta;
	}

}
