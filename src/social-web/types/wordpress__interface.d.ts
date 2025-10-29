/**
 * Type declarations for @wordpress/interface
 *
 * This package doesn't ship with TypeScript definitions,
 * so we declare the types we need here.
 */

declare module '@wordpress/interface' {
	import { ComponentType, ReactNode } from 'react';

	export interface InterfaceSkeletonProps {
		content?: ReactNode;
		className?: string;
		header?: ReactNode;
		sidebar?: ReactNode;
		secondarySidebar?: ReactNode;
		footer?: ReactNode;
		actions?: ReactNode;
		labels?: {
			header?: string;
			body?: string;
			sidebar?: string;
			actions?: string;
			footer?: string;
		};
	}

	export const InterfaceSkeleton: ComponentType< InterfaceSkeletonProps >;
}
