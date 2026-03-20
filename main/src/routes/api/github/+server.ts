import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { query, queryOne } from '$lib/server/db';
import { redisGet, redisSet } from '$lib/server/redis';
import type { RequestHandler } from './$types';

const GITHUB_GRAPHQL = 'https://api.github.com/graphql';
const SYNC_INTERVAL_MS = 5 * 60 * 1000;
const STATS_TTL = 300;

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

async function getActivityFromDb(offset: number, limit: number) {
	const rows = await query<{ repo: string; title: string; committed_at: string; is_private: number }>(
		'SELECT repo, title, committed_at, is_private FROM github_activity ORDER BY committed_at DESC LIMIT ? OFFSET ?',
		[limit + 1, offset]
	);
	const hasMore = rows.length > limit;
	const items = rows.slice(0, limit).map((r) => {
		const isPrivate = !!r.is_private;
		return {
			repo: r.repo,
			title: isPrivate ? '*'.repeat(r.title.length) : r.title,
			date: r.committed_at,
			private: isPrivate
		};
	});
	return { items, hasMore };
}

async function saveActivityToDb(items: { repo: string; title: string; date: string; oid: string; private: boolean }[]) {
	if (!items.length) return;
	const values = items.map(() => '(?, ?, ?, ?, ?)').join(', ');
	const params: (string | number)[] = [];
	for (const item of items) {
		params.push(item.repo, item.title, item.date, item.oid, item.private ? 1 : 0);
	}
	await query(
		`INSERT IGNORE INTO github_activity (repo, title, committed_at, oid, is_private) VALUES ${values}`,
		params
	);
}

async function getLastSyncTime(): Promise<number> {
	const val = await redisGet('github:last_sync');
	return val ? parseInt(val, 10) : 0;
}

async function setLastSyncTime(): Promise<void> {
	await redisSet('github:last_sync', Date.now().toString());
}

async function getCachedStats(): Promise<any | null> {
	const val = await redisGet('github:stats');
	return val ? JSON.parse(val) : null;
}

async function setCachedStats(data: any): Promise<void> {
	await redisSet('github:stats', JSON.stringify(data), STATS_TTL);
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

type RawActivity = { repo: string; title: string; date: string; oid: string; private: boolean };

function commitQuery(owner: string, name: string, cursor: string | null) {
	const afterClause = cursor ? `, after: "${cursor}"` : '';
	return `
		query($owner: String!, $name: String!) {
			repository(owner: $owner, name: $name) {
				defaultBranchRef {
					target {
						... on Commit {
							history(first: 100${afterClause}) {
								nodes {
									message
									committedDate
									oid
									author { user { login } }
								}
								pageInfo { hasNextPage endCursor }
							}
						}
					}
				}
			}
		}
	`;
}

function extractActivity(username: string, repo: any, commits: any[]): RawActivity[] {
	const ownerLogin = repo.owner?.login ?? '';
	const isOwner = ownerLogin.toLowerCase() === username.toLowerCase();
	const items: RawActivity[] = [];

	for (const commit of commits) {
		const authorLogin = commit.author?.user?.login;
		if (!authorLogin || authorLogin.toLowerCase() !== username.toLowerCase()) continue;

		const message = (commit.message as string)?.split('\n')[0] ?? 'Commit';
		const isPrivate = repo.isPrivate ?? false;

		items.push({
			repo: isOwner ? repo.name : `${ownerLogin}/${repo.name}`,
			title: message,
			date: commit.committedDate ?? '',
			oid: commit.oid,
			private: isPrivate
		});
	}

	return items;
}

async function syncRepoActivity(username: string, token: string, repo: any) {
	const owner = repo.owner?.login;
	const name = repo.name;

	const d = await graphql(token, commitQuery(owner, name, null), { owner, name });
	const history = d.data?.repository?.defaultBranchRef?.target?.history;
	if (!history) return;

	const items = extractActivity(username, repo, history.nodes ?? []);
	await saveActivityToDb(items);

	let cursor = history.pageInfo?.endCursor;
	let hasNext = history.pageInfo?.hasNextPage;

	while (hasNext) {
		const next = await graphql(token, commitQuery(owner, name, cursor), { owner, name });
		const h = next.data?.repository?.defaultBranchRef?.target?.history;
		if (!h) break;

		const batch = extractActivity(username, repo, h.nodes ?? []);
		await saveActivityToDb(batch);

		hasNext = h.pageInfo?.hasNextPage;
		cursor = h.pageInfo?.endCursor;
	}
}

async function syncAllActivity(username: string, token: string, repos: any[]) {
	await Promise.all(
		repos.map((repo) => syncRepoActivity(username, token, repo).catch(() => {}))
	);
	await setLastSyncTime();
}

async function fetchTopLanguages(username: string, token: string, repos: any[]) {
	const results = await Promise.all(
		repos.map((repo) =>
			graphql(token, `query($owner: String!, $name: String!) { repository(owner: $owner, name: $name) { primaryLanguage { name } defaultBranchRef { target { ... on Commit { history(first: 10) { nodes { author { user { login } } } } } } } } }`, { owner: repo.owner?.login, name: repo.name })
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

async function fetchAndCacheStats(username: string, token: string) {
	const stats = await fetchContributionStats(username, token);
	const orgs = stats.user.organizations.map((o) => o.login);
	const repos = await fetchMyRepos(username, token, orgs);
	const languages = await fetchTopLanguages(username, token, repos);
	const data = { username, ...stats, languages };
	await setCachedStats(data);
	return { data, repos };
}

export const GET: RequestHandler = async ({ url }) => {
	const token = env.GITHUB_TOKEN;
	const username = env.GITHUB_USERNAME;

	if (!token || !username) {
		return json({ error: 'GitHub credentials not configured.' }, { status: 503 });
	}

	const page = parseInt(url.searchParams.get('page') ?? '1', 10);
	const limit = 10;
	const offset = (page - 1) * limit;

	try {
		if (page > 1) {
			const activity = await getActivityFromDb(offset, limit);
			return json(activity);
		}

		const cached = await getCachedStats();

		let statsData: any;
		let repos: any[] | null = null;

		if (cached) {
			statsData = cached;
		} else {
			const result = await fetchAndCacheStats(username, token);
			statsData = result.data;
			repos = result.repos;
		}

		const lastSync = await getLastSyncTime();
		if (Date.now() - lastSync > SYNC_INTERVAL_MS) {
			if (!repos) {
				const orgs = statsData.user.organizations.map((o: any) => o.login);
				repos = await fetchMyRepos(username, token, orgs);
			}
			syncAllActivity(username, token, repos).catch((err) => console.error('[GitHub sync]', err));
		}

		const activity = await getActivityFromDb(offset, limit);

		return json({ ...statsData, activity });
	} catch (err: any) {
		console.error('[GitHub API]', err);
		return json({ error: err.message ?? 'Failed to fetch GitHub data.' }, { status: 500 });
	}
};
