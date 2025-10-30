/**
 * Feed Post Inspector
 *
 * Detail view for a single feed post in the side panel
 */

import { Button, Card, CardBody } from '@wordpress/components';
import { Page } from '../../components/page';
import { useSocialWebData } from '../../hooks/use-social-web-data';

interface FeedInspectorProps {
	id: number;
	onClose: () => void;
}

export default function FeedInspector( { id, onClose }: FeedInspectorProps ) {
	const { items: post, isLoading = false } = useSocialWebData( 'feed', id ) || {};

	if ( isLoading ) {
		return <div>Loading...</div>;
	}

	if ( ! post ) {
		return <div>Post not found</div>;
	}

	const postDate = post.date ? new Date( post.date ) : new Date();
	const modifiedDate = post.modified ? new Date( post.modified ) : new Date();

	return (
		<Page
			title={ post.title?.rendered || '(No title)' }
			hasPadding={ true }
			actions={
				<Button size="small" onClick={ onClose }>
					Close
				</Button>
			}
		>
			<Card>
				<CardBody>
					<h3>Content</h3>
					<div
						dangerouslySetInnerHTML={ {
							__html: post.content?.rendered || post.excerpt?.rendered || '',
						} }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3>Details</h3>
					{ post.actor && (
						<>
							<p>
								<strong>Author:</strong> { post.actor.post_title }
							</p>
							{ post.actor.guid && (
								<p>
									<strong>Profile:</strong>{ ' ' }
									<a href={ post.actor.guid } target="_blank" rel="noopener noreferrer">
										{ post.actor.guid }
									</a>
								</p>
							) }
						</>
					) }
					<p>
						<strong>Status:</strong> { post.status }
					</p>
					<p>
						<strong>Type:</strong> { post.type }
					</p>
					<p>
						<strong>Slug:</strong> { post.slug }
					</p>
					<p>
						<strong>Published:</strong> { postDate.toLocaleString() }
					</p>
					<p>
						<strong>Modified:</strong> { modifiedDate.toLocaleString() }
					</p>
					{ post.link && (
						<p>
							<a href={ post.link } target="_blank" rel="noopener noreferrer">
								View post
							</a>
						</p>
					) }
				</CardBody>
			</Card>
		</Page>
	);
}
