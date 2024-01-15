import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

export default function ImageEdit( { imageId, setImageId } ) {
	const {
		imageUrl,
	} = useSelect( ( select ) => {
		const { getMedia } = select( 'core' );
		return {
			imageUrl: imageId ? getMedia( imageId )?.source_url : undefined,
		};
	} );

	return (
		<div>
			<MediaUploadCheck>
				<MediaUpload
					onSelect={ ( media ) => setImageId( media.id ) }
					allowedTypes={ [ 'image' ] }
					value={ imageId }
					render={ ( { open } ) => (
						<Button
							className={ imageId ? 'image-button' : 'button button-large' }
							onClick={ open }
						>
							{ ! imageId && __( 'Upload Image', 'activitypub' ) }
							{ imageId && ! imageUrl && __( 'Uploading...', 'activitypub' ) }
							{ imageId && imageUrl && <img src={ imageUrl } alt="" /> }
						</Button>
					) }
				/>
			</MediaUploadCheck>
			{ imageId && (
				<Button
					className="button button-large"
					onClick={ () => setImageId( undefined ) }
				>
					{ __( 'Remove Image', 'activitypub' ) }
				</Button>
			) }
		</div>
	);
}