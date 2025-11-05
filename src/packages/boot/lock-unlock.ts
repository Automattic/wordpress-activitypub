/**
 * Simple lock/unlock utility without WordPress private APIs
 */
export function lock< T >( object: T ): T {
	return object;
}

export function unlock< T >( object: T ): T {
	return object;
}
