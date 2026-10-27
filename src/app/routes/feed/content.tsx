/**
 * Feed Route Content Module
 *
 * Exports stage and inspector components for @wordpress/boot.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * Internal dependencies
 */
import { AppShell } from '../../index';
import FeedHeader from '../../components/feed-header';
import FeedStage from './stage';
import FeedInspector from './inspector';

// Boot's stage column is the surface; the feed renders straight into it.
export function stage(): ReactNode {
	return (
		<AppShell>
			<FeedHeader />
			<FeedStage />
		</AppShell>
	);
}

/*
 * Boot's inspector column is already a surface (background, corner radius,
 * scrolling), so the inspector renders straight into it.
 */
export function inspector(): ReactNode {
	return <FeedInspector />;
}
