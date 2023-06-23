// Adapted from: https://github.com/Automattic/wp-calypso/tree/trunk/client/components/pagination
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

	return (
		<nav className={ classnames( 'followers-pagination', `is-${ variant }`, { 'is-compact': compact } ) }>
			<ul className="pagination__list">
				{ prevLabel && (
					<PaginationPage
						key="prev"
						page={ page - 1 }
						pageClick={ pageClick }
						disabled={ page === 1 }
						aria-label={ prevLabel }
					>
						{ prevLabel }
					</PaginationPage>
				) }
				{ pageList.map( pageNumber => (
					<PaginationPage
						key={ pageNumber }
						page={ pageNumber }
						pageClick={ pageClick }
						active={ pageNumber === page }
					>
						{ pageNumber }
					</PaginationPage>
				) ) }
				{ nextLabel && (
					<PaginationPage
						key="next"
						page={ page + 1 }
						pageClick={ pageClick }
						disabled={ page === Math.ceil( total / perPage ) }
						aria-label={ nextLabel }
					>
						{ nextLabel }
					</PaginationPage>
				) }
			</ul>
		</nav>
	);
}
