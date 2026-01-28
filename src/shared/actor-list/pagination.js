import { __ } from '@wordpress/i18n';

/**
 * Component to display pagination navigation.
 *
 * @param {Object}   props          The component props.
 * @param {number}   props.page     The current page number.
 * @param {number}   props.pages    The total number of pages.
 * @param {Function} props.setPage  The function to set the page number.
 * @param {string}   props.navLabel The navigation label for screen readers.
 * @return {JSX.Element|null} The pagination component or null if not needed.
 */
export function Pagination( { page, pages, setPage, navLabel = __( 'Navigation', 'activitypub' ) } ) {
	if ( pages <= 1 ) {
		return null;
	}

	const disablePreviousLink = page <= 1;
	const disableNextLink = page >= pages;

	return (
		<nav className="activitypub-actor-list-pagination" role="navigation">
			<h1 className="screen-reader-text">{ navLabel }</h1>
			{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid -- Using anchor for visual consistency with frontend pagination */ }
			<a
				href="#pagination"
				className="pagination-previous"
				aria-disabled={ disablePreviousLink }
				aria-label={ __( 'Previous page', 'activitypub' ) }
				onClick={ ( event ) => {
					event.preventDefault();
					if ( ! disablePreviousLink ) {
						setPage( page - 1 );
					}
				} }
			>
				{ __( 'Previous', 'activitypub' ) }
			</a>

			<div className="pagination-info">{ `${ page } / ${ pages }` }</div>

			{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid -- Using anchor for visual consistency with frontend pagination */ }
			<a
				href="#pagination"
				className="pagination-next"
				aria-disabled={ disableNextLink }
				aria-label={ __( 'Next page', 'activitypub' ) }
				onClick={ ( event ) => {
					event.preventDefault();
					if ( ! disableNextLink ) {
						setPage( page + 1 );
					}
				} }
			>
				{ __( 'Next', 'activitypub' ) }
			</a>
		</nav>
	);
}
