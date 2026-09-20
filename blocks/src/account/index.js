/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import './style.scss';
import Edit from './edit';
import metadata from './block.json';

const icon = (
	<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path
			d="M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm4 4a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-3 7h6v-.5c0-1.4-1.3-2.5-3-2.5s-3 1.1-3 2.5V16Zm9-6h6v1.5h-6V10Zm0 3h6v1.5h-6V13Z"
			fill="currentColor"
		/>
	</svg>
);

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
} );
