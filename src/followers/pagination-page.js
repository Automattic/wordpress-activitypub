import classNames from 'classnames';

export function PaginationPage( { active, children, page, pageClick, className } ) {
	const handleClick = event => {
		event.preventDefault();
		! active && pageClick( page );
	};

	const classes = classNames( 'wp-block activitypub-pager', className , {
		'current': active,
	} );

	return (
		<a className={ classes }onClick={ handleClick }>
			{ children }
		</a>
	);
}