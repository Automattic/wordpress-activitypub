import { registerIntentPlugin } from '../shared/intent-plugin';

registerIntentPlugin( {
	name: 'activitypub-reply-intent',
	param: 'in_reply_to',
	blockName: 'activitypub/reply',
} );
