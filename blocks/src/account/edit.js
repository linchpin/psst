/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	Notice,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './edit.scss';

/**
 * A static preview with one sample row. The real list is a per-user query and
 * has nothing to show in the editor.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @return {Element} The editor element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { heading, perPage, showSignOut } = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-psst-account is-editor-preview',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'History', 'psst' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Secrets per page', 'psst' ) }
						value={ perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
						min={ 5 }
						max={ 100 }
						step={ 5 }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the sign out link', 'psst' ) }
						checked={ showSignOut }
						onChange={ ( value ) =>
							setAttributes( { showSignOut: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Metadata only. A secret cannot be read from here, or from anywhere else on the server.',
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
					placeholder={ __( 'Your account', 'psst' ) }
					allowedFormats={ [] }
				/>

				<table className="psst-history">
					<thead>
						<tr>
							<th>{ __( 'Secret', 'psst' ) }</th>
							<th>{ __( 'For', 'psst' ) }</th>
							<th>{ __( 'Sent', 'psst' ) }</th>
							<th>{ __( 'Status', 'psst' ) }</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								<code>a7f3c1d9</code>
							</td>
							<td>{ 'ops@example.com' }</td>
							<td>{ __( '2 hours ago', 'psst' ) }</td>
							<td>{ __( 'Waiting to be read', 'psst' ) }</td>
						</tr>
					</tbody>
				</table>
			</div>
		</>
	);
}
