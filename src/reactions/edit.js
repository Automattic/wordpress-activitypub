import { useBlockProps } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Reactions } from './reactions';

/**
 * Generate a dummy reaction with a random letter and color.
 *
 * @param {number} index Index for color selection.
 * @return {Object}      Reaction object.
 */
const generateDummyReaction = ( index ) => {
	const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const colors = [
		'#FF6B6B',
		'#4ECDC4',
		'#45B7D1',
		'#96CEB4',
		'#FFEEAD',
		'#D4A5A5',
		'#9B59B6',
		'#3498DB',
		'#E67E22',
	];
	const letter = letters[ Math.floor( Math.random() * letters.length ) ];
	// random color
	const color = colors[ Math.floor( Math.random() * colors.length ) ];
	
	// Create a data URL for a colored circle with a letter.
	const canvas = document.createElement( 'canvas' );
	canvas.width = 64;
	canvas.height = 64;
	const ctx = canvas.getContext( '2d' );
	
	// Draw colored circle.
	ctx.fillStyle = color;
	ctx.beginPath();
	ctx.arc( 32, 32, 32, 0, 2 * Math.PI );
	ctx.fill();
	
	// Draw letter.
	ctx.fillStyle = '#FFFFFF';
	ctx.font = 'bold 32px sans-serif';
	ctx.textAlign = 'center';
	ctx.textBaseline = 'middle';
	ctx.fillText( letter, 32, 32 );
	
	return {
		name: `User ${ index }`,
		url: '#',
		avatar: canvas.toDataURL(),
	};
};

/**
 * Generate dummy reactions for editor preview.
 *
 * @return {Object} Reactions data.
 */
const generateDummyReactions = () => ( {
	likes: Array.from( { length: 9 }, ( _, i ) => generateDummyReaction( i ) ),
	reposts: Array.from( { length: 6 }, ( _, i ) => generateDummyReaction( i + 9 ) ),
} );

/**
 * Edit component for the Reactions block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute update callback.
 * @return {JSX.Element}                 Component to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const [ dummyReactions ] = useState( generateDummyReactions() );

	return (
		<div { ...blockProps }>
			<Reactions
				isEditing={ true }
				title={ attributes.title }
				setTitle={ ( title ) => setAttributes( { title } ) }
				reactions={ dummyReactions }
			/>
		</div>
	);
}