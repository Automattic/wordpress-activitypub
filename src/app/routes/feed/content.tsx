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
import Panel from '../../components/panel';
import FeedStage from './stage';
import FeedInspector from './inspector';

export function stage(): ReactNode {
	return (
		<AppShell>
			<Panel>
				<FeedStage />
			</Panel>
		</AppShell>
	);
}

export function inspector(): ReactNode {
	return (
		<Panel className="panel--inspector">
			<FeedInspector />
		</Panel>
	);
}
