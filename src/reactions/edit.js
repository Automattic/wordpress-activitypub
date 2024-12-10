import { useBlockProps, RichText } from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Reactions } from './reactions';

const { namespace } = window._activityPubOptions;

export default function Edit( { attributes, setAttributes } ) {
	const postId = useSelect( ( select ) => {
		return select( 'core/editor' )?.getCurrentPostId();
	}, [] );

	const blockProps = useBlockProps();

	if ( ! postId ) {
		return (
			<div { ...blockProps }>
				<Notice
					status="warning"
					isDismissible={ false }
				>
					{ __( 'This block can only be used in a single post or page context.', 'activitypub' ) }
				</Notice>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<Reactions
				postId={ postId }
				isEditing={ true }
				title={ attributes.title }
				setTitle={ ( title ) => setAttributes( { title } ) }
			/>
		</div>
	);
}