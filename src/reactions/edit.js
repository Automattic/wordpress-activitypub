import { useBlockProps, RichText } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Reactions } from './reactions';

/**
 * Generate a whimsical name using an adjective and noun combination.
 *
 * @return {string} A whimsical name.
 */
const generateWhimsicalName = () => {
	const adjectives = [
		'Bouncy', 'Cosmic', 'Dancing', 'Fluffy', 'Giggly',
		'Hoppy', 'Jazzy', 'Magical', 'Nifty', 'Perky',
		'Quirky', 'Sparkly', 'Twirly', 'Wiggly', 'Zippy',
	];
	const nouns = [
		'Badger', 'Capybara', 'Dolphin', 'Echidna', 'Flamingo',
		'Giraffe', 'Hedgehog', 'Iguana', 'Jellyfish', 'Koala',
		'Lemur', 'Manatee', 'Narwhal', 'Octopus', 'Penguin',
	];
	
	const adjective = adjectives[Math.floor(Math.random() * adjectives.length)];
	const noun = nouns[Math.floor(Math.random() * nouns.length)];
	
	return `${adjective} ${noun}`;
};

/**
 * Generate a dummy reaction with a random letter and color.
 *
 * @param {number} index Index for color selection.
 * @return {Object}      Reaction object.
 */
const generateDummyReaction = ( index ) => {
	const colors = [
		'#FF6B6B', // Coral
		'#4ECDC4', // Turquoise
		'#45B7D1', // Sky Blue
		'#96CEB4', // Sage
		'#FFEEAD', // Cream
		'#D4A5A5', // Dusty Rose
		'#9B59B6', // Purple
		'#3498DB', // Blue
		'#E67E22', // Orange
	];
	
	const name = generateWhimsicalName();
	const color = colors[Math.floor(Math.random() * colors.length)];
	const letter = name.charAt(0);
	
	// Create a data URL for a colored circle with a letter.
	const canvas = document.createElement('canvas');
	canvas.width = 64;
	canvas.height = 64;
	const ctx = canvas.getContext('2d');
	
	// Draw colored circle.
	ctx.fillStyle = color;
	ctx.beginPath();
	ctx.arc(32, 32, 32, 0, 2 * Math.PI);
	ctx.fill();
	
	// Draw letter.
	ctx.fillStyle = '#FFFFFF';
	ctx.font = '32px sans-serif';
	ctx.textAlign = 'center';
	ctx.textBaseline = 'middle';
	ctx.fillText(letter, 32, 32);
	
	return {
		name,
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
	likes: {
		label: '9 likes',
		items: Array.from( { length: 9 }, ( _, i ) => generateDummyReaction( i ) ),
	},
	reposts: {
		label: '6 reposts',
		items: Array.from( { length: 6 }, ( _, i ) => generateDummyReaction( i + 9 ) ),
	},
} );

/**
 * Edit component for the Reactions block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute update callback.
 * @return {JSX.Element}                 Component to render.
 */
export default function Edit( { attributes, setAttributes, __unstableLayoutClassNames } ) {
	const blockProps = useBlockProps( { className: __unstableLayoutClassNames } );
	const [ dummyReactions ] = useState( generateDummyReactions() );

	const titleEditor = (
		<RichText
			tagName="h6"
			value={ attributes.title }
			onChange={ ( title ) => setAttributes( { title } ) }
			placeholder={ __( 'Fediverse reactions', 'activitypub' ) }
			disableLineBreaks={ true }
			allowedFormats={ [] }
		/>
	);

	return (
		<div { ...blockProps }>
			<Reactions
				titleComponent={ titleEditor }
				reactions={ dummyReactions }
			/>
		</div>
	);
}