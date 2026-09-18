/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	InnerBlocks,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Disabled,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './edit.scss';

const FAQ_TEMPLATE = [
	[ 'core/heading', { level: 3, content: __( 'Need some help?', 'psst' ) } ],
	[
		'core/heading',
		{ level: 4, content: __( 'Why would I shred a secret?', 'psst' ) },
	],
	[
		'core/paragraph',
		{
			content: __(
				'Shredding deletes a secret before it has been read. If you send someone a link and shred the secret before they open it, the link stops working.',
				'psst'
			),
		},
	],
	[
		'core/heading',
		{
			level: 4,
			content: __( 'Why can I only see the link once?', 'psst' ),
		},
	],
	[
		'core/paragraph',
		{
			content: __(
				'The link carries the only key that can decrypt your secret. It is not stored anywhere, so copy it before you leave this page.',
				'psst'
			),
		},
	],
];

/**
 * The editor view: a static preview of the form plus the editable FAQ that
 * appears after a link has been created.
 *
 * Deliberately static. Running the real form in the editor would encrypt and
 * store secrets from inside the block editor.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @return {Element} The editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { defaultExpiry, showPassphrase, showTip, tipHeading, tipText } =
		attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-psst-secret-form is-editor-preview',
	} );

	const expiryChoices = window.psstEditor?.ttlOptions || {
		10080: __( '1 Week', 'psst' ),
		4320: __( '3 Days', 'psst' ),
		1440: __( '1 Day', 'psst' ),
		720: __( '12 Hours', 'psst' ),
		360: __( '6 Hours', 'psst' ),
		240: __( '4 Hours', 'psst' ),
		120: __( '2 Hours', 'psst' ),
		60: __( '1 Hour', 'psst' ),
		30: __( '30 Minutes', 'psst' ),
		15: __( '15 Minutes', 'psst' ),
		5: __( '5 Minutes', 'psst' ),
	};

	const expiryOptions = [
		{ value: 0, label: __( 'Site default', 'psst' ) },
		...Object.entries( expiryChoices )
			.map( ( [ minutes, label ] ) => ( {
				value: Number( minutes ),
				label,
			} ) )
			.sort( ( a, b ) => b.value - a.value ),
	];

	return (
		<>
			<InspectorControls group="settings">
				<PanelBody
					title={ __( 'Secret form', 'psst' ) }
					initialOpen={ true }
				>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Default expiration', 'psst' ) }
						value={ defaultExpiry }
						options={ expiryOptions }
						onChange={ ( value ) =>
							setAttributes( { defaultExpiry: Number( value ) } )
						}
						help={ __(
							'Pre-selected in the expiration menu. Senders can still choose another.',
							'psst'
						) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Offer a pass phrase', 'psst' ) }
						checked={ showPassphrase }
						onChange={ ( value ) =>
							setAttributes( { showPassphrase: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the tip', 'psst' ) }
						checked={ showTip }
						onChange={ ( value ) =>
							setAttributes( { showTip: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<Disabled>
					<div className="psst-form__field">
						<label htmlFor="psst-preview-message">
							{ __( 'Secret Message', 'psst' ) }
						</label>
						<textarea
							id="psst-preview-message"
							rows="6"
							readOnly
							placeholder={ __(
								'The secret goes here…',
								'psst'
							) }
						/>
					</div>
				</Disabled>

				{ showTip && (
					<div className="psst-callout" role="note">
						<RichText
							tagName="strong"
							className="psst-callout__heading"
							value={ tipHeading }
							onChange={ ( value ) =>
								setAttributes( { tipHeading: value } )
							}
							placeholder={ __( 'Quick Tip!', 'psst' ) }
							allowedFormats={ [] }
						/>
						<RichText
							tagName="p"
							value={ tipText }
							onChange={ ( value ) =>
								setAttributes( { tipText: value } )
							}
							placeholder={ __( 'Tip text…', 'psst' ) }
							allowedFormats={ [ 'core/bold', 'core/italic' ] }
						/>
					</div>
				) }

				<Disabled>
					{ showPassphrase && (
						<div className="psst-form__field">
							<label htmlFor="psst-preview-passphrase">
								{ __( 'Pass Phrase', 'psst' ) }
							</label>
							<input
								id="psst-preview-passphrase"
								type="text"
								readOnly
							/>
						</div>
					) }
					<div className="psst-form__field">
						<label htmlFor="psst-preview-expiry">
							{ __( 'Expiration', 'psst' ) }
						</label>
						<select id="psst-preview-expiry" disabled>
							{ expiryOptions.slice( 1 ).map( ( option ) => (
								<option
									key={ option.value }
									value={ option.value }
								>
									{ option.label }
								</option>
							) ) }
						</select>
					</div>
					<div className="wp-block-button psst-form__actions">
						<button
							type="button"
							className="wp-block-button__link wp-element-button"
						>
							{ __( 'Create Secret Link', 'psst' ) }
						</button>
					</div>
				</Disabled>

				<div className="psst-form__faq-editor">
					<p className="psst-form__faq-label">
						{ __(
							'Help shown after a link is created (editable):',
							'psst'
						) }
					</p>
					<InnerBlocks
						template={ FAQ_TEMPLATE }
						allowedBlocks={ [
							'core/heading',
							'core/paragraph',
							'core/list',
						] }
					/>
				</div>
			</div>
		</>
	);
}
