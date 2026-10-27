/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { postContent, audio, file, calendar, image, comment, page, pin, video } from '@wordpress/icons';

interface ObjectTypeConfigItem {
	label: string;
	icon: typeof postContent;
}

// Shared labels and icons for sidebar shortcuts and the type filter.
export const objectTypeConfig: Record< string, ObjectTypeConfigItem > = {
	// @see Base_Object::TYPES
	Article: { label: __( 'Articles', 'activitypub' ), icon: postContent },
	Note: { label: __( 'Notes & Updates', 'activitypub' ), icon: comment },
	Image: { label: __( 'Photos & Images', 'activitypub' ), icon: image },
	Event: { label: __( 'Events & Meetups', 'activitypub' ), icon: calendar },
	Video: { label: __( 'Videos', 'activitypub' ), icon: video },
	Audio: { label: __( 'Music & Podcasts', 'activitypub' ), icon: audio },
	Document: { label: __( 'Documents & Files', 'activitypub' ), icon: file },
	Page: { label: __( 'Pages', 'activitypub' ), icon: page },
	Place: { label: __( 'Places & Locations', 'activitypub' ), icon: pin },
};
