export interface Actor {
	id: number;
	label: string;
}

export interface Settings {
	actors: Actor[];
}

export interface StatComparison {
	current: number;
	change: number;
}

export interface Comparison {
	followers?: StatComparison;
	posts?: StatComparison;
	like?: StatComparison;
	repost?: StatComparison;
}

export interface MonthData {
	month: number;
	posts_count: number;
	engagement: number;
	like_count?: number;
	repost_count?: number;
	comment_count?: number;
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
