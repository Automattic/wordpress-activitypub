import classnames from 'classnames';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * The block supports for the Follow Me block in version 1.
 *
 * @type {{html: boolean, color: {gradients: boolean, link: boolean, __experimentalDefaultControls: {background: boolean, text: boolean, link: boolean}}, __experimentalBorder: {radius: boolean, width: boolean, color: boolean, style: boolean}, typography: {fontSize: boolean, __experimentalDefaultControls: {fontSize: boolean}}}}
 */
const v1BlockSupports = {
	html: false,
	color: {
		gradients: true,
		link: true,
		__experimentalDefaultControls: {
			background: true,
			text: true,
			link: true,
		},
	},
	__experimentalBorder: {
		radius: true,
		width: true,
		color: true,
		style: true,
	},
	typography: {
		fontSize: true,
		__experimentalDefaultControls: {
			fontSize: true,
		},
	},
};

const v2BlockSupports = v1BlockSupports;

/**
 * Migrates the buttonOnly attribute to a block style for the Follow Me block.
 *
 * @param {Object} attributes The block attributes.
 * @return {Object} The migrated block attributes.
 */
function migrateButtonOnly( { buttonOnly = false, className = '', ...newAttributes } ) {
	newAttributes.className = classnames( className, buttonOnly ? 'is-style-button-only' : 'is-style-default' );

	return newAttributes;
}

/**
 * Deprecation for the Follow Me block to use a core button block instead of the custom button.
 * This handles the migration of the buttonText and buttonSize attributes to the innerBlock.
 */
const v1 = {
	attributes: {
		buttonOnly: {
			type: 'boolean',
			default: false,
		},
		buttonText: {
			type: 'string',
			default: 'Follow',
		},
		selectedUser: {
			type: 'string',
			default: 'blog',
		},
	},

	supports: v1BlockSupports,

	/**
	 * Checks if the block is eligible for migration.
	 *
	 * @param {Object} attributes The block attributes.
	 *
	 * @return {boolean} Whether the block is eligible for migration.
	 */
	isEligible( { buttonText, buttonOnly } ) {
		// Run migration if buttonText or buttonOnly is set.
		return !! buttonText || !! buttonOnly;
	},

	/**
	 * Migrates the Follow Me block to use a core button block instead of the custom button.
	 *
	 * @param {Object} attributes The block attributes.
	 *
	 * @return {[Object, Array]} An array with the new block attributes and inner blocks.
	 */
	migrate( { buttonText, ...newAttributes } ) {
		const buttonBlock = createBlock( 'core/button', {
			text: buttonText,
		} );

		return [ migrateButtonOnly( newAttributes ), [ buttonBlock ] ];
	},
};

/**
 * Deprecation for the Follow Me block.
 * Handles the transition from using the buttonOnly attribute to using block styles.
 */
const v2 = {
	attributes: {
		selectedUser: {
			type: 'string',
			default: 'blog',
		},
		buttonOnly: {
			type: 'boolean',
			default: false,
		},
	},

	supports: v2BlockSupports,

	/**
	 * Checks if the block is eligible for migration.
	 *
	 * @param {Object} attributes The block attributes.
	 *
	 * @return {boolean} Whether the block is eligible for migration.
	 */
	isEligible( { buttonOnly } ) {
		return !! buttonOnly;
	},

	/**
	 * Migrates the Follow Me block to use a block style instead of the buttonOnly attribute.
	 *
	 * @param {Object} attributes The block attributes.
	 *
	 * @return {[Object, Array]} An array with the new block attributes and inner blocks.
	 */
	migrate: migrateButtonOnly,

	/**
	 * Save function for the Follow Me block.
	 *
	 * @return {JSX.Element} React element to save.
	 */
	save() {
		const blockProps = useBlockProps.save();
		const innerBlocksProps = useInnerBlocksProps.save( blockProps );

		return <div { ...innerBlocksProps } />;
	},
};

/**
 * Deprecation for the Follow Me block.
 * Handles the case where the button HTML is stripped due to unfiltered_html capability restrictions.
 */
const v3 = {
	attributes: {
		selectedUser: {
			type: 'string',
			default: 'blog',
		},
	},

	supports: v2BlockSupports,

	/**
	 * Checks if the block is eligible for migration.
	 *
	 * @param {Object} attributes The block attributes.
	 * @param {array} innerBlocks The inner blocks.
	 *
	 * @return {boolean} Whether the block is eligible for migration.
	 */
	isEligible( attributes, innerBlocks ) {
		return innerBlocks.length === 1 && 'button' === innerBlocks[ 0 ].attributes.tagName;
	},

	/**
	 * Migrates the Follow Me block to fix the broken button.
	 *
	 * @param {Object} attributes The block attributes.
	 * @param {array} innerBlocks The inner blocks.
	 *
	 * @return {[Object, Array]} An array with the new block attributes and inner blocks.
	 */
	migrate( attributes, innerBlocks ) {
		const { tagName, ...buttonAttributes } = innerBlocks[ 0 ].attributes;
		const text = innerBlocks[ 0 ].originalContent.replace( /<[^>]*>/g, '' ) ?? __( 'Follow', 'activitypub' );

		// Create a proper button block with the correct structure and the extracted text
		const buttonBlock = createBlock( 'core/button', { ...buttonAttributes, text } );

		return [ attributes, [ buttonBlock ] ];
	},

	/**
	 * Save function for the Follow Me block.
	 *
	 * @return {JSX.Element} React element to save.
	 */
	save() {
		const blockProps = useBlockProps.save();
		const innerBlocksProps = useInnerBlocksProps.save( blockProps );

		return <div { ...innerBlocksProps } />;
	},
};

export default [ v3, v2, v1 ];
