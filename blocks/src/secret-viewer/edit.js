/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './edit.scss';

/**
 * A static preview of the interstitial with editable copy.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @return {Element} The editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { heading, intro } = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-psst-secret-viewer is-editor-preview',
	} );

	return (
		<div { ...blockProps }>
			<Notice status="info" isDismissible={ false }>
				{ __(
					'Rendered for each secret at /s/{id}. Visiting the page without a secret shows the "no longer available" state.',
					'psst'
				) }
			</Notice>
			<RichText
				tagName="h1"
				className="psst-viewer__heading"
				value={ heading }
				onChange={ ( value ) => setAttributes( { heading: value } ) }
				placeholder={ __( 'Your Shared Secret', 'psst' ) }
				allowedFormats={ [] }
			/>
			<RichText
				tagName="p"
				className="psst-viewer__intro"
				value={ intro }
				onChange={ ( value ) => setAttributes( { intro: value } ) }
				placeholder={ __( 'Introductory text…', 'psst' ) }
				allowedFormats={ [ 'core/bold', 'core/italic' ] }
			/>
			<div className="wp-block-button psst-viewer__actions">
				<button
					type="button"
					className="wp-block-button__link wp-element-button"
					disabled
				>
					{ __( 'View Secret', 'psst' ) }
				</button>
			</div>
		</div>
	);
}
