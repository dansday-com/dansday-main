import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { fetchGeneral, fetchHome, fetchArticles, fetchProjects, fetchAbouts, fetchSection } from '$lib/server/data';
import { query } from '$lib/server/db';
import { encode as toToon } from '@toon-format/toon';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';

const toolSections: Record<string, string | undefined> = {
	get_home: undefined,
	get_about: 'about_enable',
	get_articles: 'articles_enable',
	get_projects: 'projects_enable',
	get_activity: 'contribute_enable',
	get_prs: 'contribute_enable'
};

async function getEnabledToolNames(): Promise<string[]> {
	const section = await fetchSection();
	return Object.entries(toolSections)
		.filter(([, s]) => !s || section[s])
		.map(([name]) => name);
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
				testimonials: about.testimonials.map((t) => ({ name: t.name, company: t.company, text: t.text }))
			});
		}
		case 'get_articles': {
			const articles = await fetchArticles();
			return toToon(articles.map((a) => ({ title: a.title, description: a.short_desc, slug: a.slug, created_at: a.created_at })));
		}
		case 'get_projects': {
			const { projects, projects_categories } = await fetchProjects();
			const catMap = new Map(projects_categories.map((c) => [c.id, c.name]));
			return toToon(projects.map((p) => ({ title: p.title, description: p.short_desc, category: catMap.get(p.category_id), created_at: p.created_at })));
		}
		case 'get_activity': {
			const rows = await query<{ repo: string; title: string; committed_at: string }>(
				'SELECT repo, title, committed_at FROM github_activity WHERE type = "commit" ORDER BY committed_at DESC'
			);
			return toToon({
				totalCount: rows.length,
				items: rows.map((r) => ({ repo: r.repo, title: r.title, date: r.committed_at }))
			});
		}
		case 'get_prs': {
			const rows = await query<{ repo: string; title: string; additions: number; deletions: number; committed_at: string }>(
				'SELECT repo, title, additions, deletions, committed_at FROM github_activity WHERE type = "pr" ORDER BY committed_at DESC'
			);
			return toToon({
				totalCount: rows.length,
				items: rows.map((r) => ({ repo: r.repo, title: r.title, additions: r.additions, deletions: r.deletions, mergedAt: r.committed_at }))
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
				messages: allMessages
			});

			const reply = completion.choices?.[0]?.message?.content || 'No response from AI.';
			return json({ response: reply });
		} catch (error: any) {
			console.error('OpenAI API Error:', error);
			return json({
				response: `Error: Failed to connect to AI service.\n${error.message}`
			});
		}
	} catch (error: any) {
		console.error('Terminal API Error:', error);
		return json({ response: `Error: ${error.message}` });
	}
};
