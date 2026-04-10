/**
 * Local replacement for @wordpress/views useView hook.
 *
 * The @wordpress/views package uses __dangerousOptInToUnstableAPIsOnlyForCoreModules
 * which blocks non-core consumers. This reimplements the same hook using only
 * public APIs so the Social Web page works without the Gutenberg plugin.
 */

/**
 * WordPress dependencies
 */
import { useCallback, useMemo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type View = Record< string, any >;

interface UseViewConfig {
	kind: string;
	name: string;
	slug: string;
	defaultView: View;
}

export function useView( config: UseViewConfig ) {
	const { kind, name, slug, defaultView } = config;
	const preferenceKey = `dataviews-${ kind }-${ name }-${ slug }`;

	const persistedView = useSelect(
		( select ) => {
			return (
				select( preferencesStore ) as {
					get: ( scope: string, key: string ) => View | undefined;
				}
			 ).get( 'core/views', preferenceKey );
		},
		[ preferenceKey ]
	);

	const { set } = useDispatch( preferencesStore );

	const view = useMemo( () => {
		return { ...defaultView, ...( persistedView ?? {} ) };
	}, [ defaultView, persistedView ] );

	const updateView = useCallback(
		( newView: View ) => {
			set( 'core/views', preferenceKey, newView );
		},
		[ set, preferenceKey ]
	);

	return {
		view,
		isModified: !! persistedView,
		updateView,
		resetToDefault: useCallback( () => {
			set( 'core/views', preferenceKey, undefined );
		}, [ set, preferenceKey ] ),
	};
}
