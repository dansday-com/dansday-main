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
	get_about: 'about_enable'
};

const noParams = { type: 'object', properties: {} } as const;

const searchParams = {
	type: 'object',
	properties: {
		keyword: {
			type: 'string',
			description: 'Search keyword to match against titles and descriptions (e.g. "3cat", "laravel", "discord"). Omit to return all results.'
		},
		type: {
			type: 'string',
			enum: ['article', 'project', 'commit', 'pr', 'review', 'issue', 'skill', 'experience', 'service', 'testimonial'],
			description: 'Filter by data type. Omit to search all types.'
		},
		startDate: { type: 'string', description: 'Filter results from this date (YYYY-MM-DD).' },
		endDate: { type: 'string', description: 'Filter results up to this date (YYYY-MM-DD).' }
	}
} as const;

const toolDefinitions: Record<string, OpenAI.Chat.ChatCompletionTool> = {
	search: {
		type: 'function',
		function: {
			name: 'search',
			description:
				'Search across all data: articles, projects, skills, experiences, services, testimonials, and GitHub activity. Prefer this tool for general questions like "what did I do this week" or topic queries like "tell me about 3cat". Supports keyword filtering and/or date filtering.',
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
	}
};

async function getEnabledSections(): Promise<Record<string, any>> {
	return fetchSection();
}

function getEnabledToolNames(section: Record<string, any>): string[] {
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

async function executeTool(name: string, args: Record<string, any> = {}, section: Record<string, any> = {}): Promise<string> {
	switch (name) {
		case 'search': {
			const hasKeyword = Boolean(args.keyword);
			const keyword = `%${args.keyword ?? ''}%`;
			const df = buildDateFilter(args);
			const dateClause = df.clause;
			const dp = df.params;
			const kwFilter = hasKeyword ? ' AND (title LIKE ? OR description LIKE ?)' : '';
			const kwParams = hasKeyword ? [keyword, keyword] : [];
			const kwRepoFilter = hasKeyword ? ' AND (repo LIKE ? OR title LIKE ?)' : '';
			const t = args.type as string | undefined;
			const on = (key: string) => section[key] !== false && section[key] !== 0;
			const articlesOn = on('articles_enable');
			const projectsOn = on('projects_enable');
			const contributeOn = on('contribute_enable');
			const aboutOn = on('about_enable');
			const skillsOn = aboutOn && on('skills_enable');
			const experienceOn = aboutOn && on('experience_enable');
			const servicesOn = aboutOn && on('services_enable');
			const testimonialOn = aboutOn && on('testimonial_enable');

			const ghTypes = ['commit', 'pr', 'review', 'issue'];
			const wantAll = !t;
			const wantGh = contributeOn && (wantAll || ghTypes.includes(t!));
			const ghTypeFilter = !wantAll && wantGh ? ` AND type = "${t}"` : '';

			const queries = await Promise.all([
				articlesOn && (wantAll || t === 'article')
					? query<{ title: string; description: string; created_at: string }>(
							'SELECT title, description, created_at FROM articles WHERE enable = 1' + kwFilter + dateClause + ' ORDER BY created_at DESC',
							[...kwParams, ...dp]
						)
					: [],
				projectsOn && (wantAll || t === 'project')
					? query<{ title: string; description: string; category_id: number; created_at: string }>(
							'SELECT title, description, category_id, created_at FROM project WHERE enable = 1' + kwFilter + dateClause + ' ORDER BY created_at DESC',
							[...kwParams, ...dp]
						)
					: [],
				wantGh
					? query<{ repo: string; title: string; type: string; created_at: string }>(
							'SELECT repo, title, type, created_at FROM github_activity WHERE 1=1' + ghTypeFilter + kwRepoFilter + dateClause + ' ORDER BY created_at DESC',
							[...kwParams, ...dp]
						)
					: [],
				skillsOn && (wantAll || t === 'skill')
					? query<{ title: string; type: string }>(
							'SELECT title, type FROM skill' + (hasKeyword ? ' WHERE title LIKE ?' : '') + ' ORDER BY `order` ASC',
							hasKeyword ? [keyword] : []
						)
					: [],
				experienceOn && (wantAll || t === 'experience')
					? query<{ title: string; type: string; period: string; description: string }>(
							'SELECT title, type, period, description FROM experience' +
								(hasKeyword ? ' WHERE (title LIKE ? OR description LIKE ?)' : '') +
								' ORDER BY `order` ASC',
							hasKeyword ? [keyword, keyword] : []
						)
					: [],
				servicesOn && (wantAll || t === 'service')
					? query<{ title: string; description: string }>(
							'SELECT title, description FROM service' + (hasKeyword ? ' WHERE (title LIKE ? OR description LIKE ?)' : '') + ' ORDER BY `order` ASC',
							hasKeyword ? [keyword, keyword] : []
						)
					: [],
				testimonialOn && (wantAll || t === 'testimonial')
					? query<{ name: string; company: string; description: string }>(
							'SELECT name, company, description FROM testimonial' +
								(hasKeyword ? ' WHERE (name LIKE ? OR company LIKE ? OR description LIKE ?)' : '') +
								' ORDER BY `order` ASC',
							hasKeyword ? [keyword, keyword, keyword] : []
						)
					: [],
				query<{ id: number; name: string }>('SELECT id, name FROM project_category ORDER BY id ASC')
			]);

			const [articles, projects, activity, skills, experiences, services, testimonials, categories] = queries;
			const catMap = new Map((categories as any[]).map((c: any) => [c.id, c.name]));

			const result: Record<string, any> = {};
			if (skills.length > 0) result.skills = skills.map((s: any) => ({ title: s.title, type: s.type }));
			if (experiences.length > 0)
				result.experiences = experiences.map((e: any) => ({ title: e.title, type: e.type, period: e.period, description: e.description }));
			if (services.length > 0) result.services = services.map((s: any) => ({ title: s.title, description: s.description }));
			if (testimonials.length > 0) result.testimonials = testimonials.map((t: any) => ({ name: t.name, company: t.company, description: t.description }));
			if (articles.length > 0) result.articles = articles.map((a: any) => ({ title: a.title, description: a.description, created_at: a.created_at }));
			if (projects.length > 0)
				result.projects = projects.map((p: any) => ({ title: p.title, description: p.description, category: catMap.get(p.category_id) }));
			if (activity.length > 0)
				result.activity = {
					totalCount: activity.length,
					stats: buildStats(activity as any[], 'created_at'),
					items: (activity as any[]).map((r: any) => ({ repo: r.repo, title: r.title, type: r.type, date: r.created_at }))
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
		const toolGuidance = `\n\nTool Usage:\n- Use "search" for ANY data question. It searches articles, projects, skills, experiences, services, testimonials, and GitHub activity.\n- Always convert relative dates to absolute dates using today (${today}). "last week" = Monday to Sunday of the previous week. "this month" = first day to today.\n- Use the "type" param to narrow results (e.g. type:"commit" for commit-only, type:"article" for articles-only).\n- Use "get_home" only for homepage/site info. Use "get_about" only for full profile/resume info.\n- If data is empty for a time range, say so — never invent data.`;
		const systemContent = terminalPrompt.replaceAll('{{today}}', today) + toolGuidance;

		const section = await getEnabledSections();
		const enabledToolNames = getEnabledToolNames(section);
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
					const result = await executeTool(tc.function.name, toolArgs, section);
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
