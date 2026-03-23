import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { fetchGeneral, fetchHome, fetchAbouts, fetchSection } from '$lib/server/data';
import { query } from '$lib/server/db';
import { encode as toToon } from '@toon-format/toon';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';
import loggerProvider from '../../../../otel/logger.js';

const toolSections: Record<string, string | undefined> = {
	search: undefined,
	get_home: undefined,
	get_about: 'about_enable',
	get_articles: 'articles_enable',
	get_projects: 'projects_enable',
	get_activity: 'contribute_enable',
	get_commits: 'contribute_enable',
	get_prs: 'contribute_enable',
	get_reviews: 'contribute_enable',
	get_issues: 'contribute_enable'
};

const noParams = { type: 'object', properties: {} } as const;

const dateParams = {
	type: 'object',
	properties: {
		startDate: {
			type: 'string',
			description: 'Filter results from this date (YYYY-MM-DD). Use this to answer time-based questions like "last week", "this month", etc.'
		},
		endDate: { type: 'string', description: 'Filter results up to this date (YYYY-MM-DD).' }
	}
} as const;

const searchParams = {
	type: 'object',
	properties: {
		keyword: {
			type: 'string',
			description: 'Search keyword to match against titles and descriptions across articles, projects, and GitHub activity (e.g. "3cat", "laravel", "discord").'
		},
		startDate: { type: 'string', description: 'Filter results from this date (YYYY-MM-DD).' },
		endDate: { type: 'string', description: 'Filter results up to this date (YYYY-MM-DD).' }
	},
	required: ['keyword']
} as const;

const toolDefinitions: Record<string, OpenAI.Chat.ChatCompletionTool> = {
	search: {
		type: 'function',
		function: {
			name: 'search',
			description:
				'Search across articles, projects, and GitHub activity by keyword. Use this when the user asks about a topic that may span multiple data sources (e.g. "tell me about 3cat", "what have I done with discord").',
			parameters: searchParams
		}
	},
	get_home: {
		type: 'function',
		function: { name: 'get_home', description: 'Get homepage title, description, site URL, and social links', parameters: noParams }
	},
	get_about: {
		type: 'function',
		function: { name: 'get_about', description: 'Get skills, education, employment history, services, and testimonials', parameters: noParams }
	},
	get_articles: {
		type: 'function',
		function: {
			name: 'get_articles',
			description: 'Get list of articles/blog posts with titles, descriptions, and dates. Use startDate/endDate to filter by time period.',
			parameters: dateParams
		}
	},
	get_projects: {
		type: 'function',
		function: {
			name: 'get_projects',
			description: 'Get list of projects with titles, descriptions, and categories. Use startDate/endDate to filter by time period.',
			parameters: dateParams
		}
	},
	get_activity: {
		type: 'function',
		function: {
			name: 'get_activity',
			description:
				'Get total GitHub activity (commits, PRs, reviews, issues) with stats broken down by repo, year, month, and week. Use startDate/endDate to filter by time period.',
			parameters: dateParams
		}
	},
	get_commits: {
		type: 'function',
		function: {
			name: 'get_commits',
			description: 'Get GitHub commit activity with stats broken down by repo, year, month, and week. Use startDate/endDate to filter by time period.',
			parameters: dateParams
		}
	},
	get_prs: {
		type: 'function',
		function: {
			name: 'get_prs',
			description: 'Get GitHub pull request activity with additions, deletions, and stats. Use startDate/endDate to filter by time period.',
			parameters: dateParams
		}
	},
	get_reviews: {
		type: 'function',
		function: {
			name: 'get_reviews',
			description: 'Get GitHub PR review activity with stats broken down by repo, year, month, and week. Use startDate/endDate to filter by time period.',
			parameters: dateParams
		}
	},
	get_issues: {
		type: 'function',
		function: {
			name: 'get_issues',
			description: 'Get GitHub issue activity with stats broken down by repo, year, month, and week. Use startDate/endDate to filter by time period.',
			parameters: dateParams
		}
	}
};

async function getEnabledToolNames(): Promise<string[]> {
	const section = await fetchSection();
	return Object.entries(toolSections)
		.filter(([, s]) => !s || section[s])
		.map(([name]) => name);
}

function getISOWeek(dateStr: string): string {
	const d = new Date(dateStr);
	const jan1 = new Date(d.getFullYear(), 0, 1);
	const week = Math.ceil(((d.getTime() - jan1.getTime()) / 86400000 + jan1.getDay() + 1) / 7);
	return `${d.getFullYear()}-W${String(week).padStart(2, '0')}`;
}

function buildStats(rows: Record<string, any>[], dateKey: string) {
	const yearly = new Map<string, number>();
	const monthly = new Map<string, number>();
	const weekly = new Map<string, number>();

	for (const row of rows) {
		const date = String(row[dateKey] ?? '');
		const repo = row.repo ?? 'unknown';
		const year = date.slice(0, 4);
		const month = date.slice(0, 7);
		const week = getISOWeek(date);

		const yk = `${year}|${repo}`;
		yearly.set(yk, (yearly.get(yk) ?? 0) + 1);
		const mk = `${month}|${repo}`;
		monthly.set(mk, (monthly.get(mk) ?? 0) + 1);
		const wk = `${week}|${repo}`;
		weekly.set(wk, (weekly.get(wk) ?? 0) + 1);
	}

	const toArr = (map: Map<string, number>) =>
		Array.from(map.entries())
			.map(([key, count]) => {
				const [period, repo] = key.split('|');
				return { period, repo, count };
			})
			.sort((a, b) => b.period.localeCompare(a.period) || b.count - a.count);

	return { yearly: toArr(yearly), monthly: toArr(monthly), weekly: toArr(weekly) };
}

function buildDateFilter(args: Record<string, any>): { clause: string; params: any[] } {
	const conditions: string[] = [];
	const params: any[] = [];
	if (args.startDate) {
		conditions.push('created_at >= ?');
		params.push(args.startDate);
	}
	if (args.endDate) {
		conditions.push('created_at <= ?');
		params.push(args.endDate + ' 23:59:59');
	}
	return { clause: conditions.length > 0 ? ' AND ' + conditions.join(' AND ') : '', params };
}

async function executeTool(name: string, args: Record<string, any> = {}): Promise<string> {
	switch (name) {
		case 'search': {
			const keyword = `%${args.keyword ?? ''}%`;
			const df = buildDateFilter(args);
			const dateClause = df.clause;
			const dp = df.params;

			const [articles, projects, activity, skills, experiences, services, testimonials, categories] = await Promise.all([
				query<{ title: string; description: string; created_at: string }>(
					'SELECT title, description, created_at FROM articles WHERE enable = 1 AND (title LIKE ? OR description LIKE ?)' +
						dateClause +
						' ORDER BY created_at DESC',
					[keyword, keyword, ...dp]
				),
				query<{ title: string; description: string; category_id: number; created_at: string }>(
					'SELECT title, description, category_id, created_at FROM project WHERE enable = 1 AND (title LIKE ? OR description LIKE ?)' +
						dateClause +
						' ORDER BY created_at DESC',
					[keyword, keyword, ...dp]
				),
				query<{ repo: string; title: string; type: string; created_at: string }>(
					'SELECT repo, title, type, created_at FROM github_activity WHERE (repo LIKE ? OR title LIKE ?)' + dateClause + ' ORDER BY created_at DESC',
					[keyword, keyword, ...dp]
				),
				query<{ title: string; type: string }>('SELECT title, type FROM skill WHERE title LIKE ? ORDER BY `order` ASC', [keyword]),
				query<{ title: string; type: string; period: string; description: string }>(
					'SELECT title, type, period, description FROM experience WHERE (title LIKE ? OR description LIKE ?) ORDER BY `order` ASC',
					[keyword, keyword]
				),
				query<{ title: string; description: string }>(
					'SELECT title, description FROM service WHERE (title LIKE ? OR description LIKE ?) ORDER BY `order` ASC',
					[keyword, keyword]
				),
				query<{ name: string; company: string; description: string }>(
					'SELECT name, company, description FROM testimonial WHERE (name LIKE ? OR company LIKE ? OR description LIKE ?) ORDER BY `order` ASC',
					[keyword, keyword, keyword]
				),
				query<{ id: number; name: string }>('SELECT id, name FROM project_category ORDER BY id ASC')
			]);

			const catMap = new Map(categories.map((c) => [c.id, c.name]));

			const result: Record<string, any> = {};
			if (skills.length > 0) result.skills = skills.map((s) => ({ title: s.title, type: s.type }));
			if (experiences.length > 0) result.experiences = experiences.map((e) => ({ title: e.title, type: e.type, period: e.period, description: e.description }));
			if (services.length > 0) result.services = services.map((s) => ({ title: s.title, description: s.description }));
			if (testimonials.length > 0) result.testimonials = testimonials.map((t) => ({ name: t.name, company: t.company, description: t.description }));
			if (articles.length > 0) result.articles = articles.map((a) => ({ title: a.title, description: a.description, created_at: a.created_at }));
			if (projects.length > 0) result.projects = projects.map((p) => ({ title: p.title, description: p.description, category: catMap.get(p.category_id) }));
			if (activity.length > 0)
				result.activity = {
					totalCount: activity.length,
					stats: buildStats(activity, 'created_at'),
					items: activity.map((r) => ({ repo: r.repo, title: r.title, type: r.type, date: r.created_at }))
				};

			return toToon(result);
		}
		case 'get_home': {
			const [home, general] = await Promise.all([fetchHome(), fetchGeneral()]);
			const siteUrl = (env.BASE_URL ?? '').replace(/\/+$/, '');
			return toToon({ title: home.title, description: home.description, site_url: siteUrl, social_links: general.social_links });
		}
		case 'get_about': {
			const about = await fetchAbouts();
			return toToon({
				design_skills: about.design_skills.map((s) => s.title),
				dev_skills: about.dev_skills.map((s) => s.title),
				education: about.edu_experiences.map((e) => ({ title: e.title, period: e.period, description: e.description })),
				employment: about.emp_experiences.map((e) => ({ title: e.title, period: e.period, description: e.description })),
				services: about.services.map((s) => ({ title: s.title, description: s.description })),
				testimonials: about.testimonials.map((t) => ({ name: t.name, company: t.company, description: t.description }))
			});
		}
		case 'get_articles': {
			const df = buildDateFilter(args);
			const articles = await query<{ title: string; description: string; created_at: string }>(
				'SELECT title, description, created_at FROM articles WHERE enable = 1' + df.clause + ' ORDER BY created_at DESC',
				df.params
			);
			return toToon(articles.map((a) => ({ title: a.title, description: a.description, created_at: a.created_at })));
		}
		case 'get_projects': {
			const df = buildDateFilter(args);
			const projects = await query<{ title: string; description: string; category_id: number; created_at: string }>(
				'SELECT title, description, category_id, created_at FROM project WHERE enable = 1' + df.clause + ' ORDER BY created_at DESC',
				df.params
			);
			const categories = await query<{ id: number; name: string }>('SELECT id, name FROM project_category ORDER BY id ASC');
			const catMap = new Map(categories.map((c) => [c.id, c.name]));
			return toToon(projects.map((p) => ({ title: p.title, description: p.description, category: catMap.get(p.category_id) })));
		}
		case 'get_activity': {
			const df = buildDateFilter(args);
			const rows = await query<{ repo: string; title: string; type: string; created_at: string }>(
				'SELECT repo, title, type, created_at FROM github_activity WHERE 1=1' + df.clause + ' ORDER BY created_at DESC',
				df.params
			);
			const stats = buildStats(rows, 'created_at');
			return toToon({
				totalCount: rows.length,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: rows.map((r) => ({ repo: r.repo, title: r.title, type: r.type, date: r.created_at }))
			});
		}
		case 'get_commits': {
			const df = buildDateFilter(args);
			const rows = await query<{ repo: string; title: string; created_at: string }>(
				'SELECT repo, title, created_at FROM github_activity WHERE type = "commit"' + df.clause + ' ORDER BY created_at DESC',
				df.params
			);
			const stats = buildStats(rows, 'created_at');
			return toToon({
				totalCount: rows.length,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: rows.map((r) => ({ repo: r.repo, title: r.title, date: r.created_at }))
			});
		}
		case 'get_prs': {
			const df = buildDateFilter(args);
			const rows = await query<{ repo: string; title: string; additions: number; deletions: number; created_at: string }>(
				'SELECT repo, title, additions, deletions, created_at FROM github_activity WHERE type = "pr"' + df.clause + ' ORDER BY created_at DESC',
				df.params
			);
			const stats = buildStats(rows, 'created_at');
			return toToon({
				totalCount: rows.length,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: rows.map((r) => ({ repo: r.repo, title: r.title, additions: r.additions, deletions: r.deletions, mergedAt: r.created_at }))
			});
		}
		case 'get_reviews': {
			const df = buildDateFilter(args);
			const rows = await query<{ repo: string; title: string; created_at: string }>(
				'SELECT repo, title, created_at FROM github_activity WHERE type = "review"' + df.clause + ' ORDER BY created_at DESC',
				df.params
			);
			const stats = buildStats(rows, 'created_at');
			return toToon({
				totalCount: rows.length,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: rows.map((r) => ({ repo: r.repo, title: r.title, date: r.created_at }))
			});
		}
		case 'get_issues': {
			const df = buildDateFilter(args);
			const rows = await query<{ repo: string; title: string; created_at: string }>(
				'SELECT repo, title, created_at FROM github_activity WHERE type = "issue"' + df.clause + ' ORDER BY created_at DESC',
				df.params
			);
			const stats = buildStats(rows, 'created_at');
			return toToon({
				totalCount: rows.length,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: rows.map((r) => ({ repo: r.repo, title: r.title, date: r.created_at }))
			});
		}
		default:
			return '{}';
	}
}

export const POST: RequestHandler = async ({ request }) => {
	try {
		const { messages } = await request.json();

		if (!Array.isArray(messages) || messages.length === 0) {
			return json({ error: 'Invalid messages array' }, { status: 400 });
		}

		const generalData = await fetchGeneral();
		const openaiUrl = generalData.ai_url as string | null;
		const openaiKey = generalData.ai_key as string | null;
		const openaiModel = generalData.ai_model as string | null;
		const terminalPrompt = (generalData.ai_terminal_prompt as string | null)?.trim() ?? '';
		const terminalReasoning = (generalData.ai_terminal_reasoning as string | null) ?? 'none';

		const hasUrl = Boolean(openaiUrl && openaiUrl.trim() !== '');
		const hasKey = Boolean(openaiKey && openaiKey.trim() !== '');
		const hasModel = Boolean(openaiModel && openaiModel.trim() !== '');

		if (!hasUrl || !hasKey || !hasModel) {
			return json({
				response: 'Error: AI Terminal is not configured. Please set the OpenAI URL, Key, and Model in the admin settings.'
			});
		}

		let baseURL = (openaiUrl as string).trim().replace(/\/+$/, '');

		if (baseURL.endsWith('/chat/completions')) {
			baseURL = baseURL.replace('/chat/completions', '');
		}

		const openai = new OpenAI({
			baseURL: baseURL,
			apiKey: (openaiKey as string).trim()
		});

		const today = new Date().toISOString().slice(0, 10);
		const systemContent = terminalPrompt.replaceAll('{{today}}', today);

		const enabledToolNames = await getEnabledToolNames();
		const tools = enabledToolNames.map((name) => toolDefinitions[name]).filter(Boolean);

		const loop: OpenAI.Chat.ChatCompletionMessageParam[] = [{ role: 'system', content: systemContent }, ...messages];

		let aiReply = 'No response from AI.';

		for (let i = 0; i < 10; i++) {
			const completion = await openai.chat.completions.create({
				model: openaiModel.trim(),
				messages: loop,
				tools: tools.length > 0 ? tools : undefined,
				tool_choice: tools.length > 0 ? 'auto' : undefined,
				reasoning_effort: terminalReasoning === 'none' ? undefined : (terminalReasoning as any)
			});

			const message = completion.choices?.[0]?.message;
			if (!message) break;

			loop.push(message);

			if (!message.tool_calls || message.tool_calls.length === 0) {
				aiReply = message.content || 'No response from AI.';
				break;
			}

			const toolResults = await Promise.all(
				message.tool_calls.map(async (tc: any) => {
					const toolArgs = tc.function.arguments ? JSON.parse(tc.function.arguments) : {};
					const result = await executeTool(tc.function.name, toolArgs);
					return {
						role: 'tool' as const,
						tool_call_id: tc.id,
						content: result
					};
				})
			);

			loop.push(...toolResults);
		}

		if (loggerProvider) {
			const logger = loggerProvider.getLogger('terminal');
			const userMessage = messages[messages.length - 1]?.content ?? '';
			logger.emit({
				body: 'AI Terminal Interaction',
				attributes: {
					'terminal.user_input': userMessage,
					'terminal.system_prompt': systemContent,
					'terminal.ai_response': aiReply
				}
			});
		}

		return json({ response: aiReply });
	} catch (error: any) {
		console.error('Terminal API Error:', error);
		return json({ response: `Error: ${error.message}` });
	}
};
