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
			d="M10 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-3.3 0-6 1.8-6 4v2h9.3a6 6 0 0 1 .7-6.9c-1.1-.7-2.5-1.1-4-1.1Zm9 1v2h2v2h-2v2h-2v-2h-2v-2h2v-2h2Z"
			fill="currentColor"
		/>
	</svg>
);

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
} );
