// Adapted from: https://github.com/Automattic/wp-calypso/tree/trunk/client/components/pagination
// Markup adapted to imitate the core query-pagination component so we can inherit those styles.
import classnames from 'classnames';
import { PaginationPage } from './pagination-page';

const PaginationVariant = {
	outlined: 'outlined',
	minimal: 'minimal',
};

export function Pagination( { compact, nextLabel, page, pageClick, perPage, prevLabel, total, variant = PaginationVariant.outlined } ) {
	const getPageList = ( page, pageCount ) => {
		let pageList = [ 1, page - 2, page - 1, page, page + 1, page + 2, pageCount ];
		pageList.sort( ( a, b ) => a - b );

		// Remove pages less than 1, or greater than total number of pages, and remove duplicates
		pageList = pageList.filter( ( pageNumber, index, originalPageList ) => {
			return (
				pageNumber >= 1 &&
				pageNumber <= pageCount &&
				originalPageList.lastIndexOf( pageNumber ) === index
			);
		} );

		for ( let i = pageList.length - 2; i >= 0; i-- ) {
			if ( pageList[ i ] === pageList[ i + 1 ] ) {
				pageList.splice( i + 1, 1 );
			}
		}

		return pageList;
	};

	const pageList = getPageList( page, Math.ceil( total / perPage ) );
	const className = classnames( 'alignwide wp-block-query-pagination is-content-justification-space-between is-layout-flex wp-block-query-pagination-is-layout-flex', `is-${ variant }`, {
		'is-compact': compact,
	} );

	return (
		<nav className={ className }>
			{ prevLabel && (
				<PaginationPage
					key="prev"
					page={ page - 1 }
					pageClick={ pageClick }
					active={ page === 1 }
					aria-label={ prevLabel }
					className="wp-block-query-pagination-previous block-editor-block-list__block"
				>
					{ prevLabel }
				</PaginationPage>
			) }
			<div className="block-editor-block-list__block wp-block wp-block-query-pagination-numbers">
				{ pageList.map( pageNumber => (
					<PaginationPage
						key={ pageNumber }
						page={ pageNumber }
						pageClick={ pageClick }
						active={ pageNumber === page }
						className="page-numbers"
					>
						{ pageNumber }
					</PaginationPage>
				) ) }
			</div>
			{ nextLabel && (
				<PaginationPage
					key="next"
					page={ page + 1 }
					pageClick={ pageClick }
					active={ page === Math.ceil( total / perPage ) }
					aria-label={ nextLabel }
					className="wp-block-query-pagination-next block-editor-block-list__block"
				>
					{ nextLabel }
				</PaginationPage>
			) }
		</nav>
	);
}
