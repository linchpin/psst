<?php
/**
 * Fields Class
 *
 * @package Psst\Controller\Admin\Fields
 */

namespace Psst\Controller\Admin\Fields;

use Psst\Core\View;
use Psst\Helper\Utils;

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	exit;
}

/**
 * Control all of our plugin Settings
 *
 * @since      1.0
 * @package    Psst
 * @subpackage Fields
 */

/**
 * Fields Class
 */
class Fields {

	/**
	 * FIELD CONTROLS
	 *
	 * Below you will find all field controls.
	 */

	/**
	 * Build out our settings fields as needed
	 *
	 * Echos our field html
	 *
	 * @since 1.0
	 *
	 * @param object $args An Object of our field customizations.
	 */
	public static function add_textfield( $args ) {

		/**
		 * Define our field defaults
		 */
		$defaults = array(
			'type'        => 'text',
			'class'       => '',
			'description' => '',
			'label'       => '',
			'disabled'    => false,
			'name'        => $args['section'] . '[' . $args['field'] . ']',
			'id'          => $args['section'] . '_' . $args['field'],
		);

		// Parse incoming $args into an array and merge it with $defaults.
		$args    = wp_parse_args( $args, $defaults );
		$options = get_option( $args['section'] );

		if ( true === $args['disabled'] ) {
			$args['disabled'] = 'disabled="disabled"';
		} else {
			$args['disabled'] = '';
		}
		?>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>" class="screen-reader-text"><?php echo esc_html( $args['label'] ); ?></label>
		<input type="<?php echo esc_attr( $args['type'] ); ?>" class="<?php echo esc_attr( $args['class'] ); ?>" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" <?php echo $args['disabled']; ?> value="<?php echo esc_attr( $options[ $args['field'] ] ); ?>">
		<?php
	}

	public static function add_cryptofield( $args ) {

		/**
		 * Define our field defaults
		 */
		$defaults = array(
			'type'        => 'text',
			'class'       => '',
			'description' => '',
			'label'       => '',
			'disabled'    => false,
			'name'        => $args['section'] . '[' . $args['field'] . ']',
			'id'          => $args['section'] . '_' . $args['field'],
		);

		// Parse incoming $args into an array and merge it with $defaults.
		$args    = wp_parse_args( $args, $defaults );
		$options = get_option( $args['section'] );

		$crypto_key_value = '';

		// If we have a crypto key in our config use that.
		if ( defined( 'PSST_CRYPTO_KEY' ) ) {
			$args['description'] = esc_html__( 'Great! It looks like you are storing your key properly', 'psst' );
			$crypto_key_value    = PSST_CRYPTO_KEY;
		} else {
			$crypto_key_value = $options[ $args['field'] ];
		}

		// Force the field to be disabled.
		$args['disabled'] = 'disabled="disabled"';

		?>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>" class="screen-reader-text"><?php echo esc_html( $args['label'] ); ?></label>
		<input type="<?php echo esc_attr( $args['type'] ); ?>" class="<?php echo esc_attr( $args['class'] ); ?>" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" <?php echo $args['disabled']; ?> value="<?php echo esc_attr( $crypto_key_value ); ?>">
		<?php
	}


	/**
	 * Used any time we need to add in a select field
	 *
	 * @since 1.0
	 *
	 * @param array $args Args for Customization.
	 */
	public static function add_select( $args ) {

		/**
		 * Define our field defaults
		 */
		$defaults = array(
			'class'   => '',
			'options' => array(),
			'name'    => $args['section'] . '[' . $args['field'] . ']',
			'id'      => $args['section'] . '_' . $args['field'],
		);

		// Parse incoming $args into an array and merge it with $defaults.
		$args = wp_parse_args( $args, $defaults );

		$options = get_option( $args['section'] );

		// @since 1.0
		// @todo this needs to be cleaned up to meet wpcs
		$select_options = ( ! empty( $args['options_cb'] ) && is_callable( $args['options_cb'] ) )
			? call_user_func_array( $args['options_cb'], $args )
			: $args['options'];

		?>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
		<select id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>">
			<?php foreach ( $select_options as $option ) : ?>
				<?php

				$selected = '';

				if ( ! empty( $options[ $args['field'] ] ) ) {
					$selected = selected( $options[ $args['field'] ], $option['value'], false );
				}

				if ( empty( $options[ $args['field'] ] ) && ( isset( $args['default'] ) && '' !== $args['default'] ) ) {
					$selected = selected( $option['value'], $args['default'], false );
				}
				?>
				<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php echo $selected; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $option['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Create a checkbox field
	 *
	 * @since 1.0
	 *
	 * @param array $args Customizations.
	 */
	public static function add_checkbox( $args ) {

		/**
		 * Define our field defaults
		 */
		$defaults = array(
			'class'       => '',
			'description' => '',
			'label'       => '',
			'options'     => '',
			'name'        => $args['section'] . '[' . $args['field'] . ']',
			'id'          => $args['section'] . '_' . $args['field'],
		);

		// Parse incoming $args into an array and merge it with $defaults.
		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['options'] ) ) { // If we don't have any option, die early.
			return;
		}

		$options = get_option( $args['options'] );
		$checked = false;

		if ( ! empty( $options[ $args['field'] ] ) ) {
			$checked = true;
		}

		$checkbox = new View();
		$checkbox->assign( 'checked', $checked );
		$checkbox->assign( 'args', $args );

		$checkbox->render( 'admin/fields/field-checkbox' );
	}
}
