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
 * A static preview with editable copy.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @return {Element} The editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { heading, intro, showLoginLink } = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-psst-register-form is-editor-preview',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Links', 'psst' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the sign in link', 'psst' ) }
						checked={ showLoginLink }
						onChange={ ( value ) =>
							setAttributes( { showLoginLink: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Shown only while registration is open, in both Psst and WordPress.',
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
					placeholder={ __( 'Create an account', 'psst' ) }
					allowedFormats={ [] }
				/>
				<RichText
					tagName="p"
					value={ intro }
					onChange={ ( value ) => setAttributes( { intro: value } ) }
					placeholder={ __( 'Why someone would want one…', 'psst' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
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
				<div className="psst-account__field">
					<span className="psst-account__label">
						{ __( 'Confirm Password', 'psst' ) }
					</span>
					<span className="psst-account__input" />
				</div>
				<div className="wp-block-button">
					<span className="wp-block-button__link wp-element-button">
						{ __( 'Create Account', 'psst' ) }
					</span>
				</div>
			</div>
		</>
	);
}
