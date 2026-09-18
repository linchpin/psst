/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import './style.scss';
import Edit from './edit';
import save from './save';
import metadata from './block.json';

const icon = (
	<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path
			d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 5a3 3 0 1 1 6 0v3H9V7Zm3 7a1.5 1.5 0 0 1 .75 2.8V18h-1.5v-1.2A1.5 1.5 0 0 1 12 14Z"
			fill="currentColor"
		/>
	</svg>
);

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save,
} );
