/**
 * WordPress dependencies
 */
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * The block renders on the server; only the FAQ inner blocks are saved.
 *
 * @return {Element} Inner block content.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
