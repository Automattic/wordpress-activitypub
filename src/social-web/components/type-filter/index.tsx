/**
 * Type Filter Component
 *
 * A segmented control for filtering posts by ActivityPub object type
 */

import { ButtonGroup, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface TypeFilterProps {
	value?: number | null;
	onChange: ( value: number | null ) => void;
	types: Array< { id: number; name: string; count: number } >;
}

export function TypeFilter( { value, onChange, types }: TypeFilterProps ) {
	return (
		<ButtonGroup style={ { marginLeft: 'auto' } }>
			<Button variant={ value === null ? 'primary' : 'secondary' } onClick={ () => onChange( null ) }>
				{ __( 'All', 'activitypub' ) }
			</Button>
			{ types.map( ( type ) => (
				<Button
					key={ type.id }
					variant={ value === type.id ? 'primary' : 'secondary' }
					onClick={ () => onChange( type.id ) }
				>
					{ type.name }
				</Button>
			) ) }
		</ButtonGroup>
	);
}
