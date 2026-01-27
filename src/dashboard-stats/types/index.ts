export interface StatComparison {
	current: number;
	change: number;
}

export interface Comparison {
	followers?: StatComparison;
	posts?: StatComparison;
	// Dynamic keys for engagement types (like, repost, quote, comment, etc.)
	[ key: string ]: StatComparison | undefined;
}

export interface MonthData {
	month: number;
	year?: number;
	posts_count: number;
	engagement: number;
	// Dynamic keys for engagement type counts (like_count, repost_count, quote_count, etc.)
	[ key: string ]: number | undefined;
}

export interface CommentType {
	slug: string;
	label: string;
	singular: string;
}

export interface Multiplicator {
	name: string;
	url: string;
	count: number;
}

export interface TopPost {
	post_id: number;
	title: string;
	url: string;
	engagement_count: number;
}

export interface Stats {
	posts_count: number;
	followers_total: number;
	top_posts: TopPost[];
	top_multiplicator: Multiplicator | null;
}

export interface StatsResponse {
	stats: Stats;
	comparison: Comparison;
	monthly: MonthData[];
	comment_types: Record< string, CommentType >;
}
