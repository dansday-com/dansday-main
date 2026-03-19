import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import type { RequestHandler } from './$types';

const GITHUB_API = 'https://api.github.com';
const GITHUB_GRAPHQL = 'https://api.github.com/graphql';

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

	const query = `
		query($username: String!, $from: DateTime!, $to: DateTime!) {
			user(login: $username) {
				name
				avatarUrl
				bio
				followers { totalCount }
				following { totalCount }
				repositories(ownerAffiliations: OWNER, privacy: PUBLIC) { totalCount }
				contributionsCollection(from: $from, to: $to) {
					totalCommitContributions
					totalPullRequestContributions
					totalIssueContributions
					totalRepositoryContributions
					contributionCalendar {
						totalContributions
						weeks {
							contributionDays {
								contributionCount
								date
							}
						}
					}
				}
			}
		}
	`;

	const res = await fetch(GITHUB_GRAPHQL, {
		method: 'POST',
		headers: getHeaders(token),
		body: JSON.stringify({
			query,
			variables: { username, from: yearStart, to: now.toISOString() }
		})
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

	return {
		user: {
			name: user?.name ?? username,
			avatarUrl: user?.avatarUrl ?? '',
			bio: user?.bio ?? '',
			followers: user?.followers?.totalCount ?? 0,
			following: user?.following?.totalCount ?? 0,
			publicRepos: user?.repositories?.totalCount ?? 0
		},
		stats: {
			week: weekCommits,
			month: monthCommits,
			year: yearCommits,
			totalCommits: user?.contributionsCollection?.totalCommitContributions ?? 0,
			totalPRs: user?.contributionsCollection?.totalPullRequestContributions ?? 0,
			totalIssues: user?.contributionsCollection?.totalIssueContributions ?? 0
		},
		calendar: allDays
	};
}

async function fetchRecentRepos(username: string, token: string) {
	const res = await fetch(
		`${GITHUB_API}/users/${username}/repos?sort=pushed&per_page=6&type=owner`,
		{ headers: getHeaders(token) }
	);
	if (!res.ok) return [];
	const repos = await res.json();
	return repos.map((r: any) => ({
		name: r.name,
		description: r.description ?? '',
		url: r.html_url,
		stars: r.stargazers_count,
		forks: r.forks_count,
		language: r.language ?? null,
		updatedAt: r.pushed_at
	}));
}

async function fetchTopLanguages(username: string, token: string) {
	const res = await fetch(
		`${GITHUB_API}/users/${username}/repos?per_page=100&type=owner`,
		{ headers: getHeaders(token) }
	);
	if (!res.ok) return [];
	const repos = await res.json();

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
		const [contributions, repos, languages] = await Promise.all([
			fetchContributionStats(username, token),
			fetchRecentRepos(username, token),
			fetchTopLanguages(username, token)
		]);

		return json({ username, ...contributions, repos, languages });
	} catch (err: any) {
		console.error('[GitHub API]', err);
		return json({ error: err.message ?? 'Failed to fetch GitHub data.' }, { status: 500 });
	}
};
