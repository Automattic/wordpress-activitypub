import classNames from 'classnames';
import { Button } from '@wordpress/components'

export function PaginationPage( { active, children, disabled, page, pageClick } ) {
	const handleClick = event => {
		event.preventDefault();
		pageClick( page );
	};

	const listClass = classNames( 'pagination__list-item', {
		'is-active': active,
		'is-disabled': disabled,
	} );

	return (
		<li className={ listClass }>
			<Button
				className="pagination__list-button"
				borderless
				onClick={ handleClick }
				disabled={ disabled }
			>
				{ children }
			</Button>
		</li>
	);
}