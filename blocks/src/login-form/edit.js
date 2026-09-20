/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './edit.scss';

/**
 * A static preview. The real form is built on the server so that it can carry
 * a nonce and whatever fields other plugins add to a login form.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @return {Element} The editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { heading, showRegisterLink, showLostPassword } = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-psst-login-form is-editor-preview',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Links', 'psst' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show the create an account link',
							'psst'
						) }
						help={ __(
							'Only ever shown when registration is actually open.',
							'psst'
						) }
						checked={ showRegisterLink }
						onChange={ ( value ) =>
							setAttributes( { showRegisterLink: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show the forgotten password link',
							'psst'
						) }
						checked={ showLostPassword }
						onChange={ ( value ) =>
							setAttributes( { showLostPassword: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<Notice status="info" isDismissible={ false }>
					{ __(
						'WordPress checks the password. This page only collects it.',
						'psst'
					) }
				</Notice>

				<RichText
					tagName="h2"
					className="psst-account__heading"
					value={ heading }
					onChange={ ( value ) =>
						setAttributes( { heading: value } )
					}
					placeholder={ __( 'Sign in', 'psst' ) }
					allowedFormats={ [] }
				/>

				<div className="psst-account__field">
					<span className="psst-account__label">
						{ __( 'Email Address', 'psst' ) }
					</span>
					<span className="psst-account__input" />
				</div>
				<div className="psst-account__field">
					<span className="psst-account__label">
						{ __( 'Password', 'psst' ) }
					</span>
					<span className="psst-account__input" />
				</div>
				<div className="wp-block-button">
					<span className="wp-block-button__link wp-element-button">
						{ __( 'Sign In', 'psst' ) }
					</span>
				</div>
			</div>
		</>
	);
}
