import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { fetchGeneral, fetchHome, fetchArticles, fetchProjects, fetchAbouts, fetchSection } from '$lib/server/data';
import { query } from '$lib/server/db';
import { encode as toToon } from '@toon-format/toon';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';
import loggerProvider from '../../../../otel/logger.js';

const toolSections: Record<string, string | undefined> = {
	get_home: undefined,
	get_about: 'about_enable',
	get_articles: 'articles_enable',
	get_projects: 'projects_enable',
	get_activity: 'contribute_enable',
	get_prs: 'contribute_enable',
	get_reviews: 'contribute_enable',
	get_issues: 'contribute_enable'
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
		const repo = row.is_private ? 'private-repo' : (row.repo ?? 'unknown');
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

async function executeTool(name: string): Promise<string> {
	switch (name) {
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
			const articles = await fetchArticles();
			return toToon(articles.map((a) => ({ title: a.title, description: a.description, created_at: a.created_at })));
		}
		case 'get_projects': {
			const { projects, projects_categories } = await fetchProjects();
			const catMap = new Map(projects_categories.map((c) => [c.id, c.name]));
			return toToon(projects.map((p) => ({ title: p.title, description: p.description, category: catMap.get(p.category_id) })));
		}
		case 'get_activity': {
			const rows = await query<{ repo: string; title: string; committed_at: string; is_private: number }>(
				'SELECT repo, title, committed_at, is_private FROM github_activity WHERE type = "commit" ORDER BY committed_at DESC'
			);
			const publicRows = rows.filter((r) => !r.is_private);
			const privateCount = rows.length - publicRows.length;
			const stats = buildStats(rows, 'committed_at');
			return toToon({
				totalCount: rows.length,
				publicCount: publicRows.length,
				privateCount,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: publicRows.map((r) => ({ repo: r.repo, title: r.title, date: r.committed_at }))
			});
		}
		case 'get_prs': {
			const rows = await query<{ repo: string; title: string; additions: number; deletions: number; committed_at: string; is_private: number }>(
				'SELECT repo, title, additions, deletions, committed_at, is_private FROM github_activity WHERE type = "pr" ORDER BY committed_at DESC'
			);
			const publicRows = rows.filter((r) => !r.is_private);
			const privateCount = rows.length - publicRows.length;
			const stats = buildStats(rows, 'committed_at');
			return toToon({
				totalCount: rows.length,
				publicCount: publicRows.length,
				privateCount,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: publicRows.map((r) => ({ repo: r.repo, title: r.title, additions: r.additions, deletions: r.deletions, mergedAt: r.committed_at }))
			});
		}
		case 'get_reviews': {
			const rows = await query<{ repo: string; title: string; committed_at: string; is_private: number }>(
				'SELECT repo, title, committed_at, is_private FROM github_activity WHERE type = "review" ORDER BY committed_at DESC'
			);
			const publicRows = rows.filter((r) => !r.is_private);
			const privateCount = rows.length - publicRows.length;
			const stats = buildStats(rows, 'committed_at');
			return toToon({
				totalCount: rows.length,
				publicCount: publicRows.length,
				privateCount,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: publicRows.map((r) => ({ repo: r.repo, title: r.title, date: r.committed_at }))
			});
		}
		case 'get_issues': {
			const rows = await query<{ repo: string; title: string; committed_at: string; is_private: number }>(
				'SELECT repo, title, committed_at, is_private FROM github_activity WHERE type = "issue" ORDER BY committed_at DESC'
			);
			const publicRows = rows.filter((r) => !r.is_private);
			const privateCount = rows.length - publicRows.length;
			const stats = buildStats(rows, 'committed_at');
			return toToon({
				totalCount: rows.length,
				publicCount: publicRows.length,
				privateCount,
				yearlyStats: stats.yearly,
				monthlyStats: stats.monthly,
				weeklyStats: stats.weekly,
				items: publicRows.map((r) => ({ repo: r.repo, title: r.title, date: r.committed_at }))
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

		const hasUrl = Boolean(openaiUrl && openaiUrl.trim() !== '');
		const hasKey = Boolean(openaiKey && openaiKey.trim() !== '');
		const hasModel = Boolean(openaiModel && openaiModel.trim() !== '');

		if (!hasUrl || !hasKey || !hasModel) {
			return json({
				response: 'Error: AI Terminal is not configured. Please set the OpenAI URL, Key, and Model in the admin settings.'
			});
		}

		let baseURL = openaiUrl.trim().replace(/\/+$/, '');

		if (baseURL.endsWith('/chat/completions')) {
			baseURL = baseURL.replace('/chat/completions', '');
		}

		const openai = new OpenAI({
			baseURL: baseURL,
			apiKey: openaiKey.trim()
		});

		const today = new Date().toISOString().slice(0, 10);
		const systemContent = terminalPrompt.replaceAll('{{today}}', today);
		const systemMessages = [{ role: 'system' as const, content: systemContent }];

		const enabledToolNames = await getEnabledToolNames();

		const toolResults = await Promise.all(
			enabledToolNames.map(async (name: string) => {
				const result = await executeTool(name);
				return `[${name}]\n${result}`;
			})
		);

		const contextMessage = {
			role: 'system' as const,
			content: `Here is all available data. Use ONLY this data to answer.\n\n${toolResults.join('\n\n')}`
		};

		const allMessages = [...systemMessages, contextMessage, ...messages] as OpenAI.Chat.ChatCompletionMessageParam[];

		try {
			const completion = await openai.chat.completions.create({
				model: openaiModel.trim(),
				messages: allMessages,
				temperature: 0
			});

			const aiReply = completion.choices?.[0]?.message?.content || 'No response from AI.';

			if (loggerProvider) {
				const logger = loggerProvider.getLogger('terminal');
				const userMessage = messages[messages.length - 1]?.content ?? '';
				logger.emit({
					body: 'AI Terminal Interaction',
					attributes: {
						'terminal.user_input': userMessage,
						'terminal.system_prompt': systemContent,
						'terminal.context_data': contextMessage.content,
						'terminal.ai_response': aiReply
					}
				});
			}

			return json({ response: aiReply });
		} catch (error: any) {
			console.error('OpenAI API Error:', error);
			const errorReply = `Error: Failed to connect to AI service.\n${error.message}`;

			if (loggerProvider) {
				const logger = loggerProvider.getLogger('terminal');
				const userMessage = messages[messages.length - 1]?.content ?? '';
				logger.emit({
					body: 'AI Terminal Error',
					attributes: {
						'terminal.user_input': userMessage,
						'terminal.system_prompt': systemContent,
						'terminal.context_data': contextMessage.content,
						'terminal.ai_response': errorReply,
						'error.message': error.message
					}
				});
			}

			return json({
				response: errorReply
			});
		}
	} catch (error: any) {
		console.error('Terminal API Error:', error);
		return json({ response: `Error: ${error.message}` });
	}
};
