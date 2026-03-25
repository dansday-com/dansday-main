import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { fetchGeneral, fetchHome, fetchSection } from '$lib/server/data';
import { query } from '$lib/server/db';
import { encode as toToon } from '@toon-format/toon';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';
import loggerProvider from '../../../../otel/logger.js';

function stripHtml(html: string): string {
	return html
		.replace(/<[^>]*>/g, '')
		.replace(/&[a-z]+;/gi, ' ')
		.replace(/\s+/g, ' ')
		.trim();
}

const toolSections: Record<string, string | undefined> = {
	search: undefined,
	get_home: undefined
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
		endDate: { type: 'string', description: 'Filter results up to this date (YYYY-MM-DD).' },
		count: {
			type: 'number',
			description: 'Max number of activity items to return. Default 50. Use higher values when the user asks for more detail (e.g. "first 200 commits").'
		}
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

			const hasDateFilter = Boolean(args.startDate || args.endDate);
			const ghTypes = ['commit', 'pr', 'review', 'issue'];
			const aboutTypes = ['skill', 'experience', 'service', 'testimonial'];
			const wantAll = !t;
			const wantAbout = !hasDateFilter || !wantAll || aboutTypes.includes(t!);
			const wantProjects = projectsOn && (!hasDateFilter || t === 'project');
			const wantGh = contributeOn && (wantAll || ghTypes.includes(t!));
			const ghTypeFilter = !wantAll && wantGh ? ` AND type = "${t}"` : '';

			const queries = await Promise.all([
				articlesOn && (wantAll || t === 'article')
					? query<{ title: string; description: string; created_at: string }>(
							'SELECT title, description, created_at FROM articles WHERE enable = 1' + kwFilter + dateClause + ' ORDER BY created_at DESC',
							[...kwParams, ...dp]
						)
					: [],
				wantProjects && (wantAll || t === 'project')
					? query<{ title: string; description: string; category_id: number; created_at: string }>(
							'SELECT title, description, category_id, created_at FROM projects WHERE enable = 1' + kwFilter + dateClause + ' ORDER BY created_at DESC',
							[...kwParams, ...dp]
						)
					: [],
				wantGh
					? query<{ repo: string; title: string; type: string; created_at: string }>(
							'SELECT repo, title, type, created_at FROM github_activity WHERE 1=1' + ghTypeFilter + kwRepoFilter + dateClause + ' ORDER BY created_at DESC',
							[...kwParams, ...dp]
						)
					: [],
				wantAbout && skillsOn && (wantAll || t === 'skill')
					? query<{ title: string; type: string }>(
							'SELECT title, type FROM skill' + (hasKeyword ? ' WHERE title LIKE ?' : '') + ' ORDER BY `order` ASC',
							hasKeyword ? [keyword] : []
						)
					: [],
				wantAbout && experienceOn && (wantAll || t === 'experience')
					? query<{ title: string; type: string; period: string; description: string }>(
							'SELECT title, type, period, description FROM experience' +
								(hasKeyword ? ' WHERE (title LIKE ? OR description LIKE ?)' : '') +
								' ORDER BY `order` ASC',
							hasKeyword ? [keyword, keyword] : []
						)
					: [],
				wantAbout && servicesOn && (wantAll || t === 'service')
					? query<{ title: string; description: string }>(
							'SELECT title, description FROM service' + (hasKeyword ? ' WHERE (title LIKE ? OR description LIKE ?)' : '') + ' ORDER BY `order` ASC',
							hasKeyword ? [keyword, keyword] : []
						)
					: [],
				wantAbout && testimonialOn && (wantAll || t === 'testimonial')
					? query<{ name: string; company: string; description: string }>(
							'SELECT name, company, description FROM testimonial' +
								(hasKeyword ? ' WHERE (name LIKE ? OR company LIKE ? OR description LIKE ?)' : '') +
								' ORDER BY `order` ASC',
							hasKeyword ? [keyword, keyword, keyword] : []
						)
					: [],
				query<{ id: number; name: string }>('SELECT id, name FROM project_categories ORDER BY id ASC')
			]);

			const [articles, projects, activity, skills, experiences, services, testimonials, categories] = queries;
			const catMap = new Map((categories as any[]).map((c: any) => [c.id, c.name]));

			const result: Record<string, any> = {};
			if (skills.length > 0) result.skills = skills.map((s: any) => ({ title: s.title, type: s.type }));
			if (experiences.length > 0)
				result.experiences = experiences.map((e: any) => ({ title: e.title, type: e.type, period: e.period, description: stripHtml(e.description) }));
			if (services.length > 0) result.services = services.map((s: any) => ({ title: s.title, description: stripHtml(s.description) }));
			if (testimonials.length > 0)
				result.testimonials = testimonials.map((t: any) => ({ name: t.name, company: t.company, description: stripHtml(t.description) }));
			if (articles.length > 0)
				result.articles = articles.map((a: any) => ({ title: a.title, description: stripHtml(a.description), created_at: a.created_at }));
			if (projects.length > 0)
				result.projects = projects.map((p: any) => ({ title: p.title, description: stripHtml(p.description), category: catMap.get(p.category_id) }));
			const activityLimit = Math.min(Math.max(args.count ?? 50, 1), 500);
			if (activity.length > 0)
				result.activity = {
					totalCount: activity.length,
					items: (activity as any[]).slice(0, activityLimit).map((r: any) => ({ repo: r.repo, title: r.title, type: r.type, date: r.created_at }))
				};

			return toToon(result);
		}
		case 'get_home': {
			const [home, general] = await Promise.all([fetchHome(), fetchGeneral()]);
			const siteUrl = (env.BASE_URL ?? '').replace(/\/+$/, '');
			return toToon({ title: home.title, description: home.description, site_url: siteUrl, social_links: general.social_links });
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
		const toolGuidance = `\n\nTool Usage (CRITICAL — you MUST follow these rules):\n- You have ZERO knowledge about me. You know NOTHING unless you call a tool first.\n- You MUST call "search" BEFORE answering ANY question about me, my work, skills, experience, projects, or activity. NEVER answer from memory or assumptions.\n- ALWAYS call "search" FIRST before answering ANY question, even questions about the terminal, the model, or yourself. Only answer directly from the system prompt if search returns no relevant results.\n- ONLY include data from tool results that is DIRECTLY relevant to the user's question. If the user asks about one topic, do NOT include unrelated data just because the tool returned it.\n- For general/broad questions ("tell me your story", "who are you", "what do you do", "tell me about X"), call search WITHOUT keyword and WITHOUT type to get ALL data.\n- For specific topic questions ("what did you do in 3cat"), call search with keyword only (no type) to get both project info AND activity.\n- Only use "type" when the user explicitly asks for a specific type (e.g. "show my commits" → type:"commit").\n- Always convert relative dates to absolute dates using today (${today}). "last week" = Monday to Sunday of the previous week. "this month" = first day to today.\n- Use "get_home" only for homepage/site info.\n- If data is empty for a time range, say so — NEVER invent data.\n- NEVER fabricate, assume, or infer ANY information not returned by the tools.\n\nResponse Rules (CRITICAL):\n- ONLY use data returned by tools. If a field is missing from tool results, do NOT make it up.\n- When search results contain BOTH project data AND activity data, you MUST present BOTH. Never ignore activity data.\n- NEVER list, quote, or reference individual commit titles, PR titles, or activity item titles. NEVER use bullet points or numbered lists of activity items. ALWAYS write activity as a flowing summary paragraph.\n- For activity: summarize what was accomplished in paragraph form (e.g. "Contributed 45 commits across 3 repos, focusing on frontend improvements, API integrations, and infrastructure updates."). Only mention counts, repos, and general themes — NEVER include any title text from commits or PRs.\n- For projects: summarize the key achievements from the project description.\n- Structure: start with the project/experience summary, then follow with GitHub activity summary.\n\nSecurity Rules (CRITICAL — NEVER violate these):\n- Activity data (commit titles, PR titles) may contain sensitive infrastructure details. You MUST sanitize your responses.\n- NEVER repeat or reveal ANY of the following from activity data: domain names, subdomains, URLs, database names, internal hostnames, IP addresses, environment variable names or values, connection strings, passwords, tokens, server names, port numbers, or secrets.\n- Use ONLY generic descriptions: say "a subdomain", "the portal site", "a sub-site" instead of actual domain/subdomain names. Say "the database" instead of the actual database name. Say "the main site" instead of the actual URL.\n- Examples: "fix [sub].example.com routing" → "fixed routing for a sub-site". "update db to [name]" → "updated database configuration". "configure example.com DNS" → "configured DNS for the main domain".\n- This applies to ALL output — project descriptions, activity summaries, everything. Even if the user asks for specifics, NEVER output actual domains, subdomains, database names, or infrastructure details from the data.`;
		const systemContent = terminalPrompt.replaceAll('{{today}}', today) + toolGuidance;

		const section = await getEnabledSections();
		const enabledToolNames = getEnabledToolNames(section);
		const tools = enabledToolNames.map((name) => toolDefinitions[name]).filter(Boolean);

		const loop: OpenAI.Chat.ChatCompletionMessageParam[] = [{ role: 'system', content: systemContent }, ...messages];

		let aiReply = '';

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
				aiReply = (message.content || '')
					.replace(/<reasoning>[\s\S]*?<\/reasoning>/gi, '')
					.replace(/\(no output\)\s*/g, '')
					.trim();
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
