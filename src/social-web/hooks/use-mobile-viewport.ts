/**
 * Mobile viewport detection hook.
 *
 * Uses WordPress standard breakpoints for consistent behavior
 * with the Site Editor and other WordPress admin interfaces.
 */

/**
 * WordPress dependencies
 */
import { useViewportMatch } from '@wordpress/compose';

/**
 * Hook to detect mobile viewport.
 *
 * Matches WordPress Site Editor breakpoint (782px / 'medium').
 * Returns true when viewport is smaller than medium breakpoint.
 *
 * @return {boolean} True if viewport is smaller than medium breakpoint.
 */
export function useMobileViewport(): boolean {
	return useViewportMatch( 'medium', '<' );
}
