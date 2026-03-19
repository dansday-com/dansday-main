import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import type { RequestHandler } from './$types';

const GITHUB_GRAPHQL = 'https://api.github.com/graphql';
const GITHUB_API = 'https://api.github.com';

function getHeaders(token: string) {
	return {
		Authorization: `Bearer ${token}`,
		'Content-Type': 'application/json',
		'User-Agent': 'portfolio-app'
	};
}

async function fetchContributionStats(username: string, token: string) {
	const now = new Date();
	const yearStart = new Date(now.getFullYear(), 0, 1).toISOString();
	const monthStart = new Date(now.getFullYear(), now.getMonth(), 1).toISOString();
	const weekStart = new Date(now.getTime() - 6 * 24 * 60 * 60 * 1000);
	weekStart.setHours(0, 0, 0, 0);

	// Two contributionsCollections: scoped to this year + all-time (no date args)
	const query = `
		query($username: String!, $from: DateTime!, $to: DateTime!) {
			user(login: $username) {
				name
				avatarUrl
				bio
				organizations(first: 10) {
					nodes { login name avatarUrl url }
				}
				contributionsCollection(from: $from, to: $to) {
					totalCommitContributions
					totalPullRequestContributions
					totalIssueContributions
					contributionCalendar {
						totalContributions
						weeks {
							contributionDays { contributionCount date }
						}
					}
				}
				allTimeContributions: contributionsCollection {
					contributionCalendar { totalContributions }
				}
			}
		}
	`;

	const res = await fetch(GITHUB_GRAPHQL, {
		method: 'POST',
		headers: getHeaders(token),
		body: JSON.stringify({ query, variables: { username, from: yearStart, to: now.toISOString() } })
	});

	if (!res.ok) throw new Error(`GitHub GraphQL error: ${res.status}`);
	const data = await res.json();
	if (data.errors) throw new Error(data.errors[0]?.message ?? 'GraphQL error');

	const user = data.data?.user;
	const calendar = user?.contributionsCollection?.contributionCalendar;
	const allDays: { date: string; count: number }[] = [];

	for (const week of calendar?.weeks ?? []) {
		for (const day of week.contributionDays) {
			allDays.push({ date: day.date, count: day.contributionCount });
		}
	}

	const weekStartStr = weekStart.toISOString().slice(0, 10);
	const monthStartStr = monthStart.slice(0, 10);

	const weekCommits = allDays.filter((d) => d.date >= weekStartStr).reduce((s, d) => s + d.count, 0);
	const monthCommits = allDays.filter((d) => d.date >= monthStartStr).reduce((s, d) => s + d.count, 0);
	const yearCommits = calendar?.totalContributions ?? 0;
	const allTimeCommits = user?.allTimeContributions?.contributionCalendar?.totalContributions ?? 0;

	return {
		user: {
			name: user?.name ?? username,
			avatarUrl: user?.avatarUrl ?? '',
			bio: user?.bio ?? '',
			totalRepos: user?.repositories?.totalCount ?? 0,
			organizations: (user?.organizations?.nodes ?? []).map((o: any) => ({
				login: o.login,
				name: o.name ?? o.login,
				avatarUrl: o.avatarUrl,
				url: o.url
			}))
		},
		stats: {
			week: weekCommits,
			month: monthCommits,
			year: yearCommits,
			allTime: allTimeCommits,
			totalCommits: user?.contributionsCollection?.totalCommitContributions ?? 0,
			totalPRs: user?.contributionsCollection?.totalPullRequestContributions ?? 0,
			totalIssues: user?.contributionsCollection?.totalIssueContributions ?? 0
		},
		calendar: allDays
	};
}

async function fetchRecentActivity(username: string, token: string) {
	// Events API — single call, includes private events, push + PR events
	const res = await fetch(
		`${GITHUB_API}/users/${username}/events?per_page=100`,
		{ headers: getHeaders(token) }
	);
	if (!res.ok) return [];

	const events = await res.json();
	if (!Array.isArray(events)) return [];

	const activity: {
		type: 'commit' | 'pr';
		repo: string;
		repoUrl: string;
		title: string;
		url: string;
		date: string;
		private: boolean;
	}[] = [];

	for (const event of events) {
		const repoName = (event.repo?.name as string) ?? '';
		const repoShort = repoName.split('/')[1] ?? repoName;
		const isPrivate = event.public === false;
		const repoUrl = `https://github.com/${repoName}`;

		if (event.type === 'PushEvent') {
			const commits: any[] = event.payload?.commits ?? [];
			for (const c of commits.slice(0, 3)) {
				activity.push({
					type: 'commit',
					repo: repoShort,
					repoUrl,
					title: (c.message as string)?.split('\n')[0] ?? 'Commit',
					url: `https://github.com/${repoName}/commit/${c.sha}`,
					date: event.created_at ?? '',
					private: isPrivate
				});
			}
		} else if (event.type === 'PullRequestEvent') {
			const pr = event.payload?.pull_request;
			if (pr) {
				activity.push({
					type: 'pr',
					repo: repoShort,
					repoUrl,
					title: pr.title ?? 'Pull Request',
					url: pr.html_url ?? repoUrl,
					date: event.created_at ?? '',
					private: isPrivate
				});
			}
		}

		if (activity.length >= 30) break;
	}

	return activity
		.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
		.slice(0, 30);
}

async function fetchTopLanguages(token: string) {
	const res = await fetch(
		`${GITHUB_API}/user/repos?per_page=100&type=all&affiliation=owner`,
		{ headers: getHeaders(token) }
	);
	if (!res.ok) return [];
	const repos = await res.json();
	if (!Array.isArray(repos)) return [];

	const langMap: Record<string, number> = {};
	for (const repo of repos) {
		if (repo.language) {
			langMap[repo.language] = (langMap[repo.language] ?? 0) + 1;
		}
	}

	return Object.entries(langMap)
		.sort((a, b) => b[1] - a[1])
		.slice(0, 8)
		.map(([name, count]) => ({ name, count }));
}

export const GET: RequestHandler = async () => {
	const token = env.GITHUB_TOKEN;
	const username = env.GITHUB_USERNAME;

	if (!token || !username) {
		return json({ error: 'GitHub credentials not configured.' }, { status: 503 });
	}

	try {
		const [contributions, activity, languages] = await Promise.all([
			fetchContributionStats(username, token),
			fetchRecentActivity(username, token),
			fetchTopLanguages(token)
		]);

		return json({ username, ...contributions, activity, languages });
	} catch (err: any) {
		console.error('[GitHub API]', err);
		return json({ error: err.message ?? 'Failed to fetch GitHub data.' }, { status: 500 });
	}
};
