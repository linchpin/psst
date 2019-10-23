<?php

namespace Psst\Model;

class Config {

	private $properties = array();

	public function __construct() {
		$this->setup_plugin_config();
	}

	private function setup_plugin_config() {
		$config = wp_cache_get( 'config', 'psst' );

		if ( $config !== false ) {
			return $config;
		}

		$this->set( 'plugin_base_name', plugin_basename( PSST_FILE ) );

		$plugin_headers = get_file_data(
			PSST_FILE,
			array(
				'plugin_name'      => 'Plugin Name',
				'plugin_uri'       => 'Plugin URI',
				'description'      => 'Description',
				'author'           => 'Author',
				'version'          => 'Version',
				'author_uri'       => 'Author URI',
				'textdomain'       => 'Text Domain',
				'text_domain_path' => 'Domain Path',
			)
		);

		$this->import( $plugin_headers );

		$this->set( 'prefix', '_psst_' );
		$this->set( 'plugin_path', PSST_PATH );
		$this->set( 'plugin_file', PSST_FILE );
		$this->set( 'plugin_url', PSST_PLUGIN_URL );
		$this->set( 'namespace', 'Psst' );

		wp_cache_set( 'config', $config, 'psst' );

		return $this->config = $config;
	}

	public function get( $name ) {
		if ( isset( $this->properties[ $name ] ) ) {
			return $this->properties[ $name ];
		}

		return false;
	}

	public function set( $name, $value ) {
		$this->properties[ $name ] = $value;

		return $this;
	}

	public function import( $var ) {
		if ( ! is_array( $var ) && ! is_object( $var ) ) {
			return false;
		}

		foreach ( $var as $name => $value ) {
			$this->properties[ $name ] = $value;
		}

		return $this;
	}

}
