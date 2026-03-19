import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { query, queryOne } from '$lib/server/db';
import type { RequestHandler } from './$types';

const GITHUB_GRAPHQL = 'https://api.github.com/graphql';
const CACHE_TTL_MS = 5 * 60 * 1000;

function getHeaders(token: string) {
	return {
		Authorization: `Bearer ${token}`,
		'Content-Type': 'application/json',
		'User-Agent': 'portfolio-app'
	};
}

async function graphql(token: string, q: string, variables?: Record<string, any>) {
	const res = await fetch(GITHUB_GRAPHQL, {
		method: 'POST',
		headers: getHeaders(token),
		body: JSON.stringify({ query: q, variables })
	});
	if (!res.ok) throw new Error(`GitHub GraphQL error: ${res.status}`);
	return res.json();
}

async function getCache(key: string): Promise<{ data: any; updatedAt: Date } | null> {
	const row = await queryOne<{ data: string; updated_at: string }>(
		'SELECT data, updated_at FROM github_cache WHERE cache_key = ?',
		[key]
	);
	if (!row) return null;
	return { data: JSON.parse(row.data), updatedAt: new Date(row.updated_at) };
}

async function setCache(key: string, data: any): Promise<void> {
	const jsonData = JSON.stringify(data);
	await query(
		'INSERT INTO github_cache (cache_key, data, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()',
		[key, jsonData]
	);
}

function isCacheStale(updatedAt: Date): boolean {
	return Date.now() - updatedAt.getTime() > CACHE_TTL_MS;
}

async function fetchContributionStats(username: string, token: string) {
	const now = new Date();
	const yearStart = new Date(now.getFullYear(), 0, 1).toISOString();
	const monthStart = new Date(now.getFullYear(), now.getMonth(), 1).toISOString();
	const weekStart = new Date(now.getTime() - 6 * 24 * 60 * 60 * 1000);
	weekStart.setHours(0, 0, 0, 0);

	const yearQuery = `
		query($username: String!, $from: DateTime!, $to: DateTime!) {
			user(login: $username) {
				name
				avatarUrl
				bio
				createdAt
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
			}
		}
	`;

	const data = await graphql(token, yearQuery, { username, from: yearStart, to: now.toISOString() });
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

	const createdYear = new Date(user?.createdAt ?? yearStart).getFullYear();
	const currentYear = now.getFullYear();

	const perYearQuery = `
		query($username: String!, $from: DateTime!, $to: DateTime!) {
			user(login: $username) {
				contributionsCollection(from: $from, to: $to) {
					totalCommitContributions
					totalPullRequestContributions
					totalIssueContributions
					contributionCalendar { totalContributions }
				}
			}
		}
	`;

	const yearRanges: { from: string; to: string }[] = [];
	for (let yr = createdYear; yr <= currentYear; yr++) {
		yearRanges.push({
			from: new Date(yr, 0, 1).toISOString(),
			to: yr === currentYear ? now.toISOString() : new Date(yr, 11, 31, 23, 59, 59).toISOString()
		});
	}

	const yearResults = await Promise.all(
		yearRanges.map(({ from, to }) =>
			graphql(token, perYearQuery, { username, from, to })
				.then((d) => d.data?.user?.contributionsCollection ?? null)
		)
	);

	let allTime = 0;
	for (const yc of yearResults) {
		if (!yc) continue;
		allTime += yc.contributionCalendar?.totalContributions ?? 0;
	}

	const thisYearData = user?.contributionsCollection;
	const totalCommits = thisYearData?.totalCommitContributions ?? 0;
	const totalPRs = thisYearData?.totalPullRequestContributions ?? 0;
	const totalIssues = thisYearData?.totalIssueContributions ?? 0;

	return {
		user: {
			name: user?.name ?? username,
			avatarUrl: user?.avatarUrl ?? '',
			bio: user?.bio ?? '',
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
			allTime,
			totalCommits,
			totalPRs,
			totalIssues
		},
		calendar: allDays
	};
}

async function fetchMyRepos(username: string, token: string, orgs: string[]) {
	const listQuery = `
		query($username: String!) {
			user(login: $username) {
				repositories(first: 100, orderBy: {field: PUSHED_AT, direction: DESC}, affiliations: [OWNER, COLLABORATOR]) {
					nodes { name owner { login } isPrivate pushedAt }
				}
			}
		}
	`;

	const orgQueries = orgs.map((org) =>
		graphql(token, `query($org: String!) { organization(login: $org) { repositories(first: 50, orderBy: {field: PUSHED_AT, direction: DESC}) { nodes { name owner { login } isPrivate pushedAt } } } }`, { org })
			.then((d) => d.data?.organization?.repositories?.nodes ?? [])
			.catch(() => [])
	);

	const [userData, ...orgResults] = await Promise.all([
		graphql(token, listQuery, { username })
			.then((d) => d.data?.user?.repositories?.nodes ?? [])
			.catch(() => []),
		...orgQueries
	]);

	const repoMap = new Map<string, any>();
	for (const repo of userData) {
		repoMap.set(`${repo.owner?.login}/${repo.name}`, repo);
	}
	for (const orgRepos of orgResults) {
		for (const repo of orgRepos) {
			const key = `${repo.owner?.login}/${repo.name}`;
			if (!repoMap.has(key)) repoMap.set(key, repo);
		}
	}

	return Array.from(repoMap.values())
		.filter((r) => r.pushedAt)
		.sort((a, b) => new Date(b.pushedAt).getTime() - new Date(a.pushedAt).getTime());
}

async function fetchRecentActivity(username: string, token: string, repos: any[], before?: string, limit = 10) {
	const untilArg = before ? `, until: "${before}"` : '';
	const perRepo = limit + 5;

	const results = await Promise.all(
		repos.map((repo) => {
			const owner = repo.owner?.login;
			const name = repo.name;
			const q = `
				query($owner: String!, $name: String!) {
					repository(owner: $owner, name: $name) {
						defaultBranchRef {
							target {
								... on Commit {
									history(first: ${perRepo}${untilArg}) {
										nodes {
											message
											committedDate
											oid
											author { user { login } }
										}
										pageInfo { hasNextPage }
									}
								}
							}
						}
					}
				}
			`;
			return graphql(token, q, { owner, name })
				.then((d) => ({ repo, history: d.data?.repository?.defaultBranchRef?.target?.history }))
				.catch(() => ({ repo, history: null }));
		})
	);

	const all: ActivityItem[] = [];
	let anyRepoHasMore = false;

	for (const { repo, history } of results) {
		const commits = history?.nodes ?? [];
		if (history?.pageInfo?.hasNextPage) anyRepoHasMore = true;

		const ownerLogin = repo.owner?.login ?? '';
		const isOwner = ownerLogin.toLowerCase() === username.toLowerCase();

		for (const commit of commits) {
			const authorLogin = commit.author?.user?.login;
			if (authorLogin && authorLogin.toLowerCase() !== username.toLowerCase()) continue;

			const message = (commit.message as string)?.split('\n')[0] ?? 'Commit';
			const isPrivate = repo.isPrivate ?? false;
			const wordCount = message.split(/\s+/).filter(Boolean).length;

			all.push({
				repo: isOwner ? repo.name : `${ownerLogin}/${repo.name}`,
				title: isPrivate ? '*'.repeat(wordCount) : message,
				date: commit.committedDate ?? '',
				private: isPrivate
			});
		}
	}

	all.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());

	const items = all.slice(0, limit);
	const hasMore = all.length > limit || anyRepoHasMore;

	return { items, hasMore };
}

type ActivityItem = { repo: string; title: string; date: string; private: boolean };

async function fetchTopLanguages(username: string, token: string, repos: any[]) {
	const results = await Promise.all(
		repos.map((repo) =>
			graphql(token, `query($owner: String!, $name: String!) { repository(owner: $owner, name: $name) { primaryLanguage { name } defaultBranchRef { target { ... on Commit { history(first: 1) { nodes { author { user { login } } } } } } } } }`, { owner: repo.owner?.login, name: repo.name })
				.then((d) => d.data?.repository)
				.catch(() => null)
		)
	);

	const langMap: Record<string, number> = {};
	for (const r of results) {
		if (!r) continue;
		const lang = r.primaryLanguage?.name;
		if (!lang) continue;
		const commits = r.defaultBranchRef?.target?.history?.nodes ?? [];
		const hasMyCommit = commits.some((c: any) => {
			const login = c.author?.user?.login;
			return !login || login.toLowerCase() === username.toLowerCase();
		});
		if (!hasMyCommit) continue;
		langMap[lang] = (langMap[lang] ?? 0) + 1;
	}

	return Object.entries(langMap)
		.sort((a, b) => b[1] - a[1])
		.slice(0, 8)
		.map(([name, count]) => ({ name, count }));
}

async function fetchFreshData(username: string, token: string) {
	const contributions = await fetchContributionStats(username, token);
	const orgs = contributions.user.organizations.map((o) => o.login);
	const repos = await fetchMyRepos(username, token, orgs);

	const [activity, languages] = await Promise.all([
		fetchRecentActivity(username, token, repos, undefined, 10),
		fetchTopLanguages(username, token, repos)
	]);

	const response = { username, ...contributions, activity, languages };
	return { response, repos };
}

export const GET: RequestHandler = async ({ url }) => {
	const token = env.GITHUB_TOKEN;
	const username = env.GITHUB_USERNAME;

	if (!token || !username) {
		return json({ error: 'GitHub credentials not configured.' }, { status: 503 });
	}

	const before = url.searchParams.get('before') ?? undefined;

	if (before) {
		try {
			const cached = await getCache('github_main');
			const repos = cached?.data?.repos;
			if (repos?.length) {
				const activity = await fetchRecentActivity(username, token, repos, before, 10);
				return json(activity);
			}
			const contributions = await fetchContributionStats(username, token);
			const orgs = contributions.user.organizations.map((o) => o.login);
			const freshRepos = await fetchMyRepos(username, token, orgs);
			const activity = await fetchRecentActivity(username, token, freshRepos, before, 10);
			return json(activity);
		} catch (err: any) {
			console.error('[GitHub API]', err);
			return json({ error: err.message ?? 'Failed to fetch GitHub data.' }, { status: 500 });
		}
	}

	try {
		const cached = await getCache('github_main');

		if (cached && !isCacheStale(cached.updatedAt)) {
			return json(cached.data.response);
		}

		if (cached) {
			fetchFreshData(username, token).then(async ({ response, repos }) => {
				await setCache('github_main', { response, repos });
			}).catch((err) => console.error('[GitHub sync]', err));

			return json(cached.data.response);
		}

		const { response, repos } = await fetchFreshData(username, token);
		await setCache('github_main', { response, repos });

		return json(response);
	} catch (err: any) {
		console.error('[GitHub API]', err);
		return json({ error: err.message ?? 'Failed to fetch GitHub data.' }, { status: 500 });
	}
};
