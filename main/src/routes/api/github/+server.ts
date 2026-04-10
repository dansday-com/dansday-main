import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { query } from '$lib/server/db';
import { redisGet, redisSet } from '$lib/server/redis';
import type { RequestHandler } from './$types';

const GITHUB_GRAPHQL = 'https://api.github.com/graphql';
const SYNC_INTERVAL_MS = 6 * 60 * 1000;

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
	const rows = await query<{
		repo: string;
		title: string;
		type: string;
		created_at: string;
		is_private: number;
		additions: number | null;
		deletions: number | null;
	}>('SELECT repo, title, type, created_at, is_private, additions, deletions FROM github_activity ORDER BY created_at DESC LIMIT ? OFFSET ?', [
		limit + 1,
		offset
	]);
	const hasMore = rows.length > limit;
	const items = rows.slice(0, limit).map((r) => {
		const isPrivate = !!r.is_private;
		return {
			repo: r.repo,
			title: r.title,
			type: r.type,
			date: r.created_at,
			private: isPrivate,
			additions: r.additions,
			deletions: r.deletions
		};
	});
	return { items, hasMore };
}

async function saveActivityToDb(
	items: { repo: string; title: string; type: string; date: string; oid: string; private: boolean; additions?: number; deletions?: number }[]
) {
	if (!items.length) return;
	const values = items.map(() => '(?, ?, ?, ?, ?, ?, ?, ?)').join(', ');
	const params: (string | number | null)[] = [];
	for (const item of items) {
		params.push(item.repo, item.title, item.type, item.date, item.oid, item.private ? 1 : 0, item.additions ?? null, item.deletions ?? null);
	}
	await query(`INSERT IGNORE INTO github_activity (repo, title, type, created_at, oid, is_private, additions, deletions) VALUES ${values}`, params);
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
	await redisSet('github:stats', JSON.stringify(data));
}

async function fetchContributionStats(username: string, token: string) {
	const now = new Date();
	const yearStart = new Date(now.getFullYear(), 0, 1).toISOString();
	const yearEnd = new Date(now.getFullYear(), 11, 31, 23, 59, 59).toISOString();

	const startOfWeek = new Date(now);
	const day = startOfWeek.getDay();
	const diffToMonday = day === 0 ? 6 : day - 1;
	startOfWeek.setDate(now.getDate() - diffToMonday);

	const endOfWeek = new Date(startOfWeek);
	endOfWeek.setDate(startOfWeek.getDate() + 6);

	const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
	const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);

	const weekStartStr = startOfWeek.toISOString().slice(0, 10);
	const weekEndStr = endOfWeek.toISOString().slice(0, 10);
	const monthStartStr = startOfMonth.toISOString().slice(0, 10);
	const monthEndStr = endOfMonth.toISOString().slice(0, 10);

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
					totalPullRequestReviewContributions
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

	const data = await graphql(token, yearQuery, { username, from: yearStart, to: yearEnd });
	if (data.errors) throw new Error(data.errors[0]?.message ?? 'GraphQL error');

	const user = data.data?.user;
	const calendar = user?.contributionsCollection?.contributionCalendar;
	const allDays: { date: string; count: number }[] = [];

	for (const week of calendar?.weeks ?? []) {
		for (const day of week.contributionDays) {
			allDays.push({ date: day.date, count: day.contributionCount });
		}
	}

	const weekCommits = allDays.filter((d) => d.date >= weekStartStr && d.date <= weekEndStr).reduce((s, d) => s + d.count, 0);
	const monthCommits = allDays.filter((d) => d.date >= monthStartStr && d.date <= monthEndStr).reduce((s, d) => s + d.count, 0);
	const yearCommits = calendar?.totalContributions ?? 0;

	const createdYear = new Date(user?.createdAt ?? yearStart).getFullYear();
	const currentYear = now.getFullYear();

	const perYearQuery = `
		query($username: String!, $from: DateTime!, $to: DateTime!) {
			user(login: $username) {
				contributionsCollection(from: $from, to: $to) {
					totalCommitContributions
					totalPullRequestContributions
					totalPullRequestReviewContributions
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
			to: new Date(yr, 11, 31, 23, 59, 59).toISOString()
		});
	}

	const yearResults = await Promise.all(
		yearRanges.map(({ from, to }) => graphql(token, perYearQuery, { username, from, to }).then((d) => d.data?.user?.contributionsCollection ?? null))
	);

	let allTime = 0;
	const yearTotals: Record<number, number> = {};
	for (let i = 0; i < yearResults.length; i++) {
		const yc = yearResults[i];
		const yr = createdYear + i;
		const total = yc?.contributionCalendar?.totalContributions ?? 0;
		yearTotals[yr] = total;
		allTime += total;
	}

	const thisYearData = user?.contributionsCollection;
	const totalCommits = thisYearData?.totalCommitContributions ?? 0;
	const totalPRs = thisYearData?.totalPullRequestContributions ?? 0;
	const totalReviews = thisYearData?.totalPullRequestReviewContributions ?? 0;
	const totalIssues = thisYearData?.totalIssueContributions ?? 0;

	const fmt = (d: Date) => `${d.getDate()} ${d.toLocaleString('en', { month: 'short' })}`;
	const weekRangeStr = `${fmt(startOfWeek)} - ${fmt(endOfWeek)}`;
	const monthRangeStr = `${fmt(startOfMonth)} - ${fmt(endOfMonth)}`;
	const yearRangeStr = `1 Jan - 31 Dec`;

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
			totalReviews,
			totalIssues,
			weekRange: weekRangeStr,
			monthRange: monthRangeStr,
			yearRange: yearRangeStr,
			allTimeRange: `${createdYear} - ${now.getFullYear()}`
		},
		calendar: allDays,
		createdYear,
		currentYear: now.getFullYear(),
		yearTotals
	};
}

async function fetchCalendarForYear(username: string, token: string, year: number) {
	const from = new Date(year, 0, 1).toISOString();
	const to = new Date(year, 11, 31, 23, 59, 59).toISOString();

	const q = `
		query($username: String!, $from: DateTime!, $to: DateTime!) {
			user(login: $username) {
				contributionsCollection(from: $from, to: $to) {
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

	const data = await graphql(token, q, { username, from, to });
	if (data.errors) throw new Error(data.errors[0]?.message ?? 'GraphQL error');

	const calendar = data.data?.user?.contributionsCollection?.contributionCalendar;
	const days: { date: string; count: number }[] = [];
	for (const week of calendar?.weeks ?? []) {
		for (const day of week.contributionDays) {
			days.push({ date: day.date, count: day.contributionCount });
		}
	}

	return { calendar: days, total: calendar?.totalContributions ?? 0 };
}

async function fetchMyRepos(username: string, token: string, createdYear: number) {
	const now = new Date();
	const repoMap = new Map<string, any>();

	for (let yr = createdYear; yr <= now.getFullYear(); yr++) {
		const from = new Date(yr, 0, 1).toISOString();
		const to = new Date(yr, 11, 31, 23, 59, 59).toISOString();

		const q = `
			query($username: String!, $from: DateTime!, $to: DateTime!) {
				user(login: $username) {
					contributionsCollection(from: $from, to: $to) {
						commitContributionsByRepository(maxRepositories: 100) {
							repository { name owner { login } isPrivate pushedAt }
						}
						pullRequestContributionsByRepository(maxRepositories: 100) {
							repository { name owner { login } isPrivate pushedAt }
						}
						issueContributionsByRepository(maxRepositories: 100) {
							repository { name owner { login } isPrivate pushedAt }
						}
					}
				}
			}
		`;

		const d = await graphql(token, q, { username, from, to }).catch(() => null);
		const col = d?.data?.user?.contributionsCollection;
		if (!col) continue;

		const allRepos = [
			...(col.commitContributionsByRepository ?? []).map((c: any) => c.repository),
			...(col.pullRequestContributionsByRepository ?? []).map((c: any) => c.repository),
			...(col.issueContributionsByRepository ?? []).map((c: any) => c.repository)
		];

		for (const repo of allRepos) {
			if (!repo) continue;
			const key = `${repo.owner?.login}/${repo.name}`;
			if (!repoMap.has(key)) repoMap.set(key, repo);
		}
	}

	return Array.from(repoMap.values());
}

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

function prQuery(owner: string, name: string, cursor: string | null) {
	const afterClause = cursor ? `, after: "${cursor}"` : '';
	return `
		query($owner: String!, $name: String!) {
			repository(owner: $owner, name: $name) {
				pullRequests(first: 50, states: MERGED, orderBy: {field: UPDATED_AT, direction: DESC}${afterClause}) {
					nodes {
						number
						title
						additions
						deletions
						mergedAt
						author { login }
					}
					pageInfo { hasNextPage endCursor }
				}
			}
		}
	`;
}

function issueContribQuery(username: string, from: string, to: string, cursor: string | null) {
	const afterClause = cursor ? `, after: "${cursor}"` : '';
	return `
		query($username: String!, $from: DateTime!, $to: DateTime!) {
			user(login: $username) {
				contributionsCollection(from: $from, to: $to) {
					issueContributions(first: 50${afterClause}) {
						nodes {
							issue {
								number
								title
								createdAt
								repository { name owner { login } isPrivate }
							}
						}
						pageInfo { hasNextPage endCursor }
					}
				}
			}
		}
	`;
}

function reviewQuery(username: string, from: string, to: string, cursor: string | null) {
	const afterClause = cursor ? `, after: "${cursor}"` : '';
	return `
		query($username: String!, $from: DateTime!, $to: DateTime!) {
			user(login: $username) {
				contributionsCollection(from: $from, to: $to) {
					pullRequestReviewContributions(first: 50${afterClause}) {
						nodes {
							pullRequestReview {
								createdAt
								pullRequest {
									number
									title
									repository { name owner { login } isPrivate }
								}
							}
						}
						pageInfo { hasNextPage endCursor }
					}
				}
			}
		}
	`;
}

function repoName(username: string, repo: any): string {
	const ownerLogin = repo.owner?.login ?? '';
	const isOwner = ownerLogin.toLowerCase() === username.toLowerCase();
	return isOwner ? repo.name : `${ownerLogin}/${repo.name}`;
}

function extractCommits(username: string, repo: any, commits: any[]) {
	const items: { repo: string; title: string; type: string; date: string; oid: string; private: boolean }[] = [];
	for (const commit of commits) {
		const authorLogin = commit.author?.user?.login;
		if (!authorLogin || authorLogin.toLowerCase() !== username.toLowerCase()) continue;
		items.push({
			repo: repoName(username, repo),
			title: (commit.message as string)?.split('\n')[0] ?? 'Commit',
			type: 'commit',
			date: commit.committedDate ?? '',
			oid: commit.oid,
			private: repo.isPrivate ?? false
		});
	}
	return items;
}

function extractPRs(username: string, repo: any, prs: any[]) {
	const items: { repo: string; title: string; type: string; date: string; oid: string; private: boolean; additions: number; deletions: number }[] = [];
	for (const pr of prs) {
		if (!pr.author?.login || pr.author.login.toLowerCase() !== username.toLowerCase()) continue;
		items.push({
			repo: repoName(username, repo),
			title: pr.title ?? '',
			type: 'pr',
			date: pr.mergedAt ?? '',
			oid: `pr:${repo.owner?.login}/${repo.name}#${pr.number}`,
			private: repo.isPrivate ?? false,
			additions: pr.additions ?? 0,
			deletions: pr.deletions ?? 0
		});
	}
	return items;
}

function extractIssueContribs(username: string, nodes: any[]) {
	const items: { repo: string; title: string; type: string; date: string; oid: string; private: boolean }[] = [];
	for (const node of nodes) {
		const issue = node.issue;
		if (!issue?.repository) continue;
		const repo = issue.repository;
		const ownerLogin = repo.owner?.login ?? '';
		const isOwner = ownerLogin.toLowerCase() === username.toLowerCase();
		const repoLabel = isOwner ? repo.name : `${ownerLogin}/${repo.name}`;
		items.push({
			repo: repoLabel,
			title: issue.title ?? '',
			type: 'issue',
			date: issue.createdAt ?? '',
			oid: `issue:${ownerLogin}/${repo.name}#${issue.number}`,
			private: repo.isPrivate ?? false
		});
	}
	return items;
}

function extractReviews(username: string, nodes: any[]) {
	const items: { repo: string; title: string; type: string; date: string; oid: string; private: boolean }[] = [];
	for (const node of nodes) {
		const review = node.pullRequestReview;
		if (!review?.pullRequest) continue;
		const pr = review.pullRequest;
		const repo = pr.repository;
		const ownerLogin = repo.owner?.login ?? '';
		const isOwner = ownerLogin.toLowerCase() === username.toLowerCase();
		const repoLabel = isOwner ? repo.name : `${ownerLogin}/${repo.name}`;
		items.push({
			repo: repoLabel,
			title: `Review: ${pr.title ?? ''}`,
			type: 'review',
			date: review.createdAt ?? '',
			oid: `review:${ownerLogin}/${repo.name}#${pr.number}:${review.createdAt}`,
			private: repo.isPrivate ?? false
		});
	}
	return items;
}

async function syncRepoCommits(username: string, token: string, repo: any) {
	const owner = repo.owner?.login;
	const name = repo.name;

	const d = await graphql(token, commitQuery(owner, name, null), { owner, name });
	const history = d.data?.repository?.defaultBranchRef?.target?.history;
	if (!history) return;

	await saveActivityToDb(extractCommits(username, repo, history.nodes ?? []));

	let cursor = history.pageInfo?.endCursor;
	let hasNext = history.pageInfo?.hasNextPage;

	while (hasNext) {
		const next = await graphql(token, commitQuery(owner, name, cursor), { owner, name });
		const h = next.data?.repository?.defaultBranchRef?.target?.history;
		if (!h) break;

		await saveActivityToDb(extractCommits(username, repo, h.nodes ?? []));
		hasNext = h.pageInfo?.hasNextPage;
		cursor = h.pageInfo?.endCursor;
	}
}

async function syncRepoPRs(username: string, token: string, repo: any) {
	const owner = repo.owner?.login;
	const name = repo.name;

	const d = await graphql(token, prQuery(owner, name, null), { owner, name });
	const prs = d.data?.repository?.pullRequests;
	if (!prs) return;

	await saveActivityToDb(extractPRs(username, repo, prs.nodes ?? []));

	let cursor = prs.pageInfo?.endCursor;
	let hasNext = prs.pageInfo?.hasNextPage;

	while (hasNext) {
		const next = await graphql(token, prQuery(owner, name, cursor), { owner, name });
		const p = next.data?.repository?.pullRequests;
		if (!p) break;

		await saveActivityToDb(extractPRs(username, repo, p.nodes ?? []));
		hasNext = p.pageInfo?.hasNextPage;
		cursor = p.pageInfo?.endCursor;
	}
}

async function syncIssues(username: string, token: string, createdYear: number) {
	const now = new Date();
	const currentYear = now.getFullYear();
	for (let yr = createdYear; yr <= currentYear; yr++) {
		const from = new Date(yr, 0, 1).toISOString();
		const to = new Date(yr, 11, 31, 23, 59, 59).toISOString();
		const d = await graphql(token, issueContribQuery(username, from, to, null), { username, from, to });
		const issues = d.data?.user?.contributionsCollection?.issueContributions;
		if (!issues) continue;

		await saveActivityToDb(extractIssueContribs(username, issues.nodes ?? []));

		let cursor = issues.pageInfo?.endCursor;
		let hasNext = issues.pageInfo?.hasNextPage;

		while (hasNext) {
			const next = await graphql(token, issueContribQuery(username, from, to, cursor), { username, from, to });
			const i = next.data?.user?.contributionsCollection?.issueContributions;
			if (!i) break;
			await saveActivityToDb(extractIssueContribs(username, i.nodes ?? []));
			hasNext = i.pageInfo?.hasNextPage;
			cursor = i.pageInfo?.endCursor;
		}
	}
}

async function syncReviews(username: string, token: string, createdYear: number) {
	const now = new Date();
	const currentYear = now.getFullYear();
	for (let yr = createdYear; yr <= currentYear; yr++) {
		const from = new Date(yr, 0, 1).toISOString();
		const to = new Date(yr, 11, 31, 23, 59, 59).toISOString();
		const d = await graphql(token, reviewQuery(username, from, to, null), { username, from, to });
		const reviews = d.data?.user?.contributionsCollection?.pullRequestReviewContributions;
		if (!reviews) continue;

		await saveActivityToDb(extractReviews(username, reviews.nodes ?? []));

		let cursor = reviews.pageInfo?.endCursor;
		let hasNext = reviews.pageInfo?.hasNextPage;

		while (hasNext) {
			const next = await graphql(token, reviewQuery(username, from, to, cursor), { username, from, to });
			const r = next.data?.user?.contributionsCollection?.pullRequestReviewContributions;
			if (!r) break;
			await saveActivityToDb(extractReviews(username, r.nodes ?? []));
			hasNext = r.pageInfo?.hasNextPage;
			cursor = r.pageInfo?.endCursor;
		}
	}
}

async function syncAllActivity(username: string, token: string, repos: any[], createdYear: number) {
	await Promise.all([
		...repos.map((repo) => Promise.all([syncRepoCommits(username, token, repo), syncRepoPRs(username, token, repo)]).catch(() => {})),
		syncReviews(username, token, createdYear).catch(() => {}),
		syncIssues(username, token, createdYear).catch(() => {})
	]);
	await setLastSyncTime();
}

async function fetchAndCacheStats(username: string, token: string) {
	const stats = await fetchContributionStats(username, token);
	const repos = await fetchMyRepos(username, token, stats.createdYear);
	const data = { username, ...stats };
	await setCachedStats(data);
	return { data, repos, createdYear: stats.createdYear };
}

async function backgroundSync() {
	const token = env.GITHUB_TOKEN;
	const username = env.GITHUB_USERNAME;
	if (!token || !username) return;

	const lastSync = await getLastSyncTime();
	if (Date.now() - lastSync > SYNC_INTERVAL_MS) {
		try {
			const result = await fetchAndCacheStats(username, token);
			syncAllActivity(username, token, result.repos, result.createdYear).catch((err) => console.error('[GitHub sync]', err));
		} catch (err) {
			console.error('[GitHub background sync]', err);
		}
	}
}

setInterval(backgroundSync, SYNC_INTERVAL_MS);
backgroundSync();

export const GET: RequestHandler = async ({ url }) => {
	const token = env.GITHUB_TOKEN;
	const username = env.GITHUB_USERNAME;

	if (!token || !username) {
		return json({ error: 'GitHub credentials not configured.' }, { status: 503 });
	}

	const page = parseInt(url.searchParams.get('page') ?? '1', 10);
	const calendarYear = url.searchParams.get('calendarYear');
	const limit = 10;
	const offset = (page - 1) * limit;

	try {
		if (calendarYear) {
			const year = parseInt(calendarYear, 10);
			const cacheKey = `github:calendar:${year}`;
			const cached = await redisGet(cacheKey);
			if (cached) return json(JSON.parse(cached));
			try {
				const result = await fetchCalendarForYear(username, token, year);
				await redisSet(cacheKey, JSON.stringify(result));
				return json(result);
			} catch (err: any) {
				console.error('[GitHub Calendar API]', err);
				return json({ calendar: [], total: 0 }, { status: 500 });
			}
		}

		if (page > 1) {
			const activity = await getActivityFromDb(offset, limit);
			return json(activity);
		}

		const cached = await getCachedStats();
		const activity = await getActivityFromDb(offset, limit);

		if (cached) {
			return json({ ...cached, activity });
		}

		try {
			const result = await fetchAndCacheStats(username, token);
			return json({ ...result.data, activity });
		} catch (apiErr: any) {
			console.error('[GitHub API]', apiErr);
			return json({
				username,
				user: { name: username, avatarUrl: '', bio: '', organizations: [] },
				stats: {
					week: 0,
					month: 0,
					year: 0,
					allTime: 0,
					totalCommits: 0,
					totalPRs: 0,
					totalReviews: 0,
					totalIssues: 0,
					weekRange: '',
					monthRange: '',
					yearRange: '',
					allTimeRange: ''
				},
				calendar: [],
				createdYear: new Date().getFullYear(),
				currentYear: new Date().getFullYear(),
				activity
			});
		}
	} catch (err: any) {
		console.error('[GitHub API]', err);
		return json({ error: err.message ?? 'Failed to fetch GitHub data.' }, { status: 500 });
	}
};
