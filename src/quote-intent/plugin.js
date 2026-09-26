import { registerIntentPlugin } from '../shared/intent-plugin';

registerIntentPlugin( {
	name: 'activitypub-quote-intent',
	param: 'quotation_of',
	blockName: 'activitypub/quote',
} );
