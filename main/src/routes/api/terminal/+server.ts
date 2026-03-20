import { json } from '@sveltejs/kit';
import { fetchGeneral, fetchHome, fetchArticles, fetchProjects, fetchAbouts, fetchSection } from '$lib/server/data';
import { query } from '$lib/server/db';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';

const allTools: Record<string, { tool: OpenAI.Chat.ChatCompletionTool; section?: string }> = {
	get_home: {
		tool: {
			type: 'function',
			function: {
				name: 'get_home',
				description: 'Get the homepage title, description, and social/contact links',
				parameters: { type: 'object', properties: {}, required: [] }
			}
		}
	},
	get_about: {
		section: 'about_enable',
		tool: {
			type: 'function',
			function: {
				name: 'get_about',
				description: 'Get about info: skills, experience, education, services, testimonials',
				parameters: { type: 'object', properties: {}, required: [] }
			}
		}
	},
	get_articles: {
		section: 'articles_enable',
		tool: {
			type: 'function',
			function: {
				name: 'get_articles',
				description: 'Get published articles/blog posts',
				parameters: { type: 'object', properties: {}, required: [] }
			}
		}
	},
	get_projects: {
		section: 'projects_enable',
		tool: {
			type: 'function',
			function: {
				name: 'get_projects',
				description: 'Get portfolio projects',
				parameters: { type: 'object', properties: {}, required: [] }
			}
		}
	},
	get_activity: {
		section: 'contribute_enable',
		tool: {
			type: 'function',
			function: {
				name: 'get_activity',
				description: 'Get recent GitHub commit activity',
				parameters: { type: 'object', properties: {}, required: [] }
			}
		}
	}
};

async function getEnabledTools(): Promise<OpenAI.Chat.ChatCompletionTool[]> {
	const section = await fetchSection();
	return Object.values(allTools)
		.filter((t) => !t.section || section[t.section])
		.map((t) => t.tool);
}

async function executeTool(name: string): Promise<string> {
	switch (name) {
		case 'get_home': {
			const [home, general] = await Promise.all([fetchHome(), fetchGeneral()]);
			return JSON.stringify({ title: home.title, description: home.description, social_links: general.social_links });
		}
		case 'get_about': {
			const about = await fetchAbouts();
			return JSON.stringify({
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
			return JSON.stringify(articles.map((a) => ({ title: a.title, description: a.short_desc, slug: a.slug, created_at: a.created_at })));
		}
		case 'get_projects': {
			const { projects, projects_categories } = await fetchProjects();
			const catMap = new Map(projects_categories.map((c) => [c.id, c.name]));
			return JSON.stringify(projects.map((p) => ({ title: p.title, description: p.short_desc, category: catMap.get(p.category_id), created_at: p.created_at })));
		}
		case 'get_activity': {
			const rows = await query<{ repo: string; title: string; committed_at: string; is_private: number }>(
				'SELECT repo, title, committed_at, is_private FROM github_activity ORDER BY committed_at DESC LIMIT 20'
			);
			return JSON.stringify(rows.map((r) => ({
				repo: r.repo,
				title: r.is_private ? '*'.repeat(r.title.length) : r.title,
				date: r.committed_at
			})));
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

		const systemMessages = terminalPrompt ? [{ role: 'system' as const, content: terminalPrompt }] : [];

		const allMessages = [...systemMessages, ...messages] as OpenAI.Chat.ChatCompletionMessageParam[];
		const enabledTools = await getEnabledTools();

		try {
			let completion = await openai.chat.completions.create({
				model: openaiModel.trim(),
				messages: allMessages,
				tools: enabledTools,
				tool_choice: 'auto'
			});

			let message = completion.choices?.[0]?.message;

			while (message?.tool_calls && message.tool_calls.length > 0) {
				allMessages.push(message);

				for (const call of message.tool_calls) {
					const result = await executeTool(call.function.name);
					allMessages.push({
						role: 'tool',
						tool_call_id: call.id,
						content: result
					});
				}

				completion = await openai.chat.completions.create({
					model: openaiModel.trim(),
					messages: allMessages,
					tools: enabledTools,
					tool_choice: 'auto'
				});

				message = completion.choices?.[0]?.message;
			}

			const reply = message?.content || 'No response from AI.';
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
