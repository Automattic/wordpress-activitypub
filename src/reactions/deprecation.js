import { InnerBlocks } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';

/**
 * v2: Handle save format change from Interactivity API commit (c0c84100)
 *
 * Old format: <><InnerBlocks.Content /><div className="activitypub-reactions-block"></div></>
 * New format: <div {...useBlockProps.save()}><InnerBlocks.Content /></div>
 */
const v2 = {
	attributes: {},

	supports: {
		html: false,
		align: true,
		layout: {
			default: {
				type: 'constrained',
				orientation: 'vertical',
				justifyContent: 'center',
			},
		},
	},

	save() {
		return (
			<>
				<InnerBlocks.Content />
				<div className="activitypub-reactions-block"></div>
			</>
		);
	},
};

/**
 * v1: Handle title attribute migration to heading innerBlock (92e196e9)
 * Original block (77ae436c) was fully dynamic with no save output.
 */
const v1 = {
	attributes: {
		title: {
			type: 'string',
			default: 'Fediverse reactions',
		},
	},

	supports: {
		html: false,
		align: true,
		layout: {
			default: {
				type: 'constrained',
				orientation: 'vertical',
				justifyContent: 'center',
			},
		},
	},

	// Original block had no save function - dynamic block only
	save() {
		return null;
	},

	/**
	 * Checks if the block is eligible for migration.
	 *
	 * @param {Object} attributes       The block attributes.
	 * @param {string} attributes.title The block title.
	 * @return {boolean} Whether the block is eligible for migration.
	 */
	isEligible( { title } ) {
		return !! title;
	},

	/**
	 * Migrates the block to use a core heading block instead of the custom heading attribute.
	 *
	 * @param {Object} attributes       The attributes for the block.
	 * @param {string} attributes.title The block title.
	 * @return {Array} The new attributes and inner blocks.
	 */
	migrate( { title, ...newAttributes } ) {
		const headingBlock = createBlock( 'core/heading', {
			content: title,
			level: 6,
		} );

		return [ newAttributes, [ headingBlock ] ];
	},
};

export default [ v2, v1 ];
