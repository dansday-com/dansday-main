import { json } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { fetchGeneral, fetchHome, fetchSection } from '$lib/server/data';
import { query } from '$lib/server/db';
import { encode as toToon } from '@toon-format/toon';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';
import loggerProvider from '../../../../otel/logger.js';

interface SemanticResult {
	table_name: string;
	row_id: number;
	similarity: number;
}

interface CachedEmbedding {
	table_name: string;
	row_id: number;
	vector: number[];
	norm: number;
}

function vectorNorm(v: number[]): number {
	let sum = 0;
	for (let i = 0; i < v.length; i++) sum += v[i] * v[i];
	return Math.sqrt(sum);
}

async function getEmbeddings(): Promise<CachedEmbedding[]> {
	const rows = await query<{ table_name: string; row_id: number; vector: string }>('SELECT table_name, row_id, vector FROM embeddings');
	return rows.map((r) => {
		const vector = JSON.parse(r.vector) as number[];
		return { table_name: r.table_name, row_id: r.row_id, vector, norm: vectorNorm(vector) };
	});
}

async function embedQuery(openai: OpenAI, model: string, text: string): Promise<number[] | null> {
	const response = await openai.embeddings.create({ model, input: text });
	return response.data[0].embedding ?? null;
}

function cosineSimilarityWithNorm(a: number[], b: number[], normB: number): number {
	let dot = 0;
	let normA = 0;
	for (let i = 0; i < a.length; i++) {
		dot += a[i] * b[i];
		normA += a[i] * a[i];
	}
	const denom = Math.sqrt(normA) * normB;
	return denom === 0 ? 0 : dot / denom;
}

async function semanticSearch(queryVector: number[], topN: number = 20, threshold: number = 0.3): Promise<SemanticResult[]> {
	const allEmbeddings = await getEmbeddings();
	const scored: SemanticResult[] = [];
	for (const row of allEmbeddings) {
		const similarity = cosineSimilarityWithNorm(queryVector, row.vector, row.norm);
		if (similarity >= threshold) {
			scored.push({ table_name: row.table_name, row_id: row.row_id, similarity });
		}
	}
	scored.sort((a, b) => b.similarity - a.similarity);
	return scored.slice(0, topN);
}

function stripHtml(html: string): string {
	return html
		.replace(/<[^>]*>/g, '')
		.replace(/&[a-z]+;/gi, ' ')
		.replace(/\s+/g, ' ')
		.trim();
}

const toolSections: Record<string, string | undefined> = {
	search: undefined
};


const searchParams = {
	type: 'object',
	properties: {
		keyword: {
			type: 'string',
			description: 'Search keyword to match against titles and descriptions. Omit to return all results.'
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
				'Search across all data: articles, projects, skills, experiences, services, testimonials, GitHub activity, and site info (social links, email, site URL). Supports keyword filtering and/or date filtering.',
			parameters: searchParams
		}
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

interface EmbeddingOpts {
	client: OpenAI | null;
	model: string;
}

async function executeTool(
	name: string,
	args: Record<string, any> = {},
	section: Record<string, any> = {},
	embedding: EmbeddingOpts = { client: null, model: '' }
): Promise<string> {
	switch (name) {
		case 'search': {
			const rawKeyword = (args.keyword ?? '').trim();
			const hasKeyword = rawKeyword.length > 0;
			const df = buildDateFilter(args);
			const dateClause = df.clause;
			const dp = df.params;
			const ftQuery = rawKeyword
				.split(/[\s\-]+/)
				.map((w: string) => w.replace(/[+><~*"@()]/g, ''))
				.filter((w: string) => w.length > 0)
				.map((w: string) => `${w}*`)
				.join(' ');
			const ftMatch = (fields: string[]) => {
				if (!hasKeyword) return { filter: '', scoreCol: '', params: [] as string[] };
				const matchExpr = `MATCH(${fields.join(', ')}) AGAINST(? IN BOOLEAN MODE)`;
				return { filter: ` AND ${matchExpr}`, scoreCol: `, ${matchExpr} AS relevance`, params: [ftQuery, ftQuery] };
			};
			const ftMatchFilter = (fields: string[]) => {
				if (!hasKeyword) return { filter: '', params: [] as string[] };
				const matchExpr = `MATCH(${fields.join(', ')}) AGAINST(? IN BOOLEAN MODE)`;
				return { filter: ` AND ${matchExpr}`, params: [ftQuery] };
			};

			const articlesFt = ftMatch(['title', 'description']);
			const projectsFt = ftMatch(['title', 'description']);
			const ghFt = ftMatchFilter(['repo', 'title']);
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
			const ghTypeFilter = !wantAll && wantGh ? ' AND type = ?' : '';
			const ghTypeParams = !wantAll && wantGh ? [t] : [];

			const queries = await Promise.all([
				articlesOn && (wantAll || t === 'article')
					? query<{ title: string; description: string; created_at: string; relevance?: number }>(
							'SELECT id, title, description, created_at' +
								articlesFt.scoreCol +
								' FROM articles WHERE enable = 1' +
								articlesFt.filter +
								dateClause +
								(hasKeyword ? ' ORDER BY relevance DESC' : ' ORDER BY created_at DESC'),
							[...articlesFt.params, ...dp]
						)
					: [],
				wantProjects && (wantAll || t === 'project')
					? query<{ title: string; description: string; category_id: number; created_at: string; relevance?: number }>(
							'SELECT id, title, description, category_id, created_at' +
								projectsFt.scoreCol +
								' FROM projects WHERE enable = 1' +
								projectsFt.filter +
								dateClause +
								(hasKeyword ? ' ORDER BY relevance DESC' : ' ORDER BY created_at DESC'),
							[...projectsFt.params, ...dp]
						)
					: [],
				wantGh
					? query<{ repo: string; title: string; type: string; created_at: string }>(
							'SELECT id, repo, title, type, created_at FROM github_activity WHERE 1=1' + ghTypeFilter + ghFt.filter + dateClause + ' ORDER BY created_at DESC',
							[...ghTypeParams, ...ghFt.params, ...dp]
						)
					: [],
				wantAbout && skillsOn
					? query<{ title: string; type: string }>('SELECT id, title, type FROM skill ORDER BY `order` ASC')
					: [],
				wantAbout && experienceOn
					? query<{ title: string; type: string; period: string; description: string }>(
							'SELECT id, title, type, period, description FROM experience ORDER BY `order` ASC'
						)
					: [],
				wantAbout && servicesOn
					? query<{ title: string; description: string }>(
							'SELECT id, title, description FROM service ORDER BY `order` ASC'
						)
					: [],
				wantAbout && testimonialOn
					? query<{ name: string; company: string; description: string }>(
							'SELECT id, name, company, description FROM testimonial ORDER BY `order` ASC'
						)
					: [],
				query<{ id: number; name: string }>('SELECT id, name FROM project_categories ORDER BY id ASC')
			]);

			const [articles, projects, activity, skills, experiences, services, testimonials, categories] = queries;
			const catMap = new Map((categories as any[]).map((c: any) => [c.id, c.name]));

			let semanticHits: SemanticResult[] = [];
			if (hasKeyword && embedding.client) {
				try {
					const queryVector = await embedQuery(embedding.client, embedding.model, rawKeyword);
					if (queryVector) {
						semanticHits = await semanticSearch(queryVector, 20, 0.3);
					}
				} catch {}
			}

			const K = 60;
			const semanticScoreMap = new Map<string, number>();
			semanticHits.forEach((h, i) => {
				semanticScoreMap.set(`${h.table_name}:${h.row_id}`, 1 / (K + i + 1));
			});

			const rrfSort = (tableName: string, rows: any[], bm25Key?: string) => {
				if (!hasKeyword || rows.length === 0) return rows;
				return [...rows]
					.map((r: any, i: number) => {
						const bm25Score = bm25Key && r[bm25Key] ? 1 / (K + i + 1) : 0;
						const semScore = semanticScoreMap.get(`${tableName}:${r.id}`) ?? 0;
						return { ...r, _rrfScore: bm25Score + 1.5 * semScore };
					})
					.sort((a: any, b: any) => b._rrfScore - a._rrfScore);
			};

			const semanticIds = (table: string) => semanticHits.filter((h) => h.table_name === table).map((h) => h.row_id);

			const mergeSemanticRows = async (tableName: string, existing: any[], selectSql: string): Promise<any[]> => {
				const sIds = semanticIds(tableName);
				if (sIds.length === 0) return existing;
				const existingIds = new Set(existing.map((r: any) => r.id));
				const missingIds = sIds.filter((id) => !existingIds.has(id));
				if (missingIds.length === 0) return existing;
				const placeholders = missingIds.map(() => '?').join(',');
				const extra = await query(`${selectSql} WHERE id IN (${placeholders})`, missingIds);
				return [...existing, ...(extra as any[])];
			};

			const mergedArticles = hasKeyword
				? rrfSort('articles', await mergeSemanticRows('articles', articles as any[], 'SELECT id, title, description, created_at FROM articles'), 'relevance')
				: articles;
			const mergedProjects = hasKeyword
				? rrfSort(
						'projects',
						await mergeSemanticRows('projects', projects as any[], 'SELECT id, title, description, category_id, created_at FROM projects'),
						'relevance'
					)
				: projects;
			const mergedSkills = hasKeyword ? rrfSort('skill', await mergeSemanticRows('skill', skills as any[], 'SELECT id, title, type FROM skill')) : skills;
			const mergedExperiences = hasKeyword
				? rrfSort('experience', await mergeSemanticRows('experience', experiences as any[], 'SELECT id, title, type, period, description FROM experience'))
				: experiences;
			const mergedServices = hasKeyword
				? rrfSort('service', await mergeSemanticRows('service', services as any[], 'SELECT id, title, description FROM service'))
				: services;
			const mergedTestimonials = hasKeyword
				? rrfSort('testimonial', await mergeSemanticRows('testimonial', testimonials as any[], 'SELECT id, name, company, description FROM testimonial'))
				: testimonials;

			let mergedActivity = activity as any[];
			if (hasKeyword) {
				const bm25Ids = new Set((activity as any[]).map((r: any) => r.id));
				const bm25RankMap = new Map<number, number>();
				(activity as any[]).forEach((r: any, i: number) => {
					bm25RankMap.set(r.id, i);
				});

				const sIds = semanticIds('github_activity');
				if (sIds.length > 0) {
					const missingIds = sIds.filter((id) => !bm25Ids.has(id));
					if (missingIds.length > 0) {
						const placeholders = missingIds.map(() => '?').join(',');
						const extraActivity = await query<{ id: number; repo: string; title: string; type: string; created_at: string }>(
							`SELECT id, repo, title, type, created_at FROM github_activity WHERE id IN (${placeholders})`,
							missingIds
						);
						mergedActivity = [...(activity as any[]), ...(extraActivity as any[])];
					}
				}

				mergedActivity = mergedActivity
					.map((r: any) => {
						const bm25Rank = bm25RankMap.get(r.id);
						const bm25Score = bm25Rank !== undefined ? 1 / (K + bm25Rank + 1) : 0;
						const semScore = semanticScoreMap.get(`github_activity:${r.id}`) ?? 0;
						return { ...r, _rrfScore: bm25Score + 1.5 * semScore };
					})
					.sort((a: any, b: any) => b._rrfScore - a._rrfScore);
			}

			const result: Record<string, any> = {};
			if (mergedSkills.length > 0) result.skills = (mergedSkills as any[]).map((s: any) => ({ title: s.title, type: s.type }));
			if (mergedExperiences.length > 0)
				result.experiences = (mergedExperiences as any[]).map((e: any) => ({
					title: e.title,
					type: e.type,
					period: e.period,
					description: stripHtml(e.description)
				}));
			if (mergedServices.length > 0) result.services = (mergedServices as any[]).map((s: any) => ({ title: s.title, description: stripHtml(s.description) }));
			if (mergedTestimonials.length > 0)
				result.testimonials = (mergedTestimonials as any[]).map((t: any) => ({ name: t.name, company: t.company, description: stripHtml(t.description) }));
			if (mergedArticles.length > 0)
				result.articles = (mergedArticles as any[]).map((a: any) => ({ title: a.title, description: stripHtml(a.description), created_at: a.created_at }));
			if (mergedProjects.length > 0)
				result.projects = (mergedProjects as any[]).map((p: any) => ({
					title: p.title,
					description: stripHtml(p.description),
					category: catMap.get(p.category_id)
				}));
			const activityLimit = Math.min(Math.max(args.count ?? 50, 1), 500);
			if (mergedActivity.length > 0)
				result.activity = {
					totalCount: mergedActivity.length,
					items: mergedActivity.slice(0, activityLimit).map((r: any) => ({ repo: r.repo, title: r.title, type: r.type, date: r.created_at }))
				};

			const [home, generalInfo] = await Promise.all([fetchHome(), fetchGeneral()]);
			const siteUrl = (env.BASE_URL ?? '').replace(/\/+$/, '');
			result.site = { title: home.title, description: home.description, site_url: siteUrl, social_links: generalInfo.social_links };

			return toToon(result);
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

		const embeddingUrl = (generalData.embedding_url as string | null)?.trim() ?? '';
		const embeddingKey = (generalData.embedding_key as string | null)?.trim() ?? '';
		const embeddingModel = (generalData.embedding_model as string | null)?.trim() ?? '';
		let embeddingClient: OpenAI | null = null;
		if (embeddingUrl && embeddingKey && embeddingModel) {
			let embBaseUrl = embeddingUrl.replace(/\/+$/, '');
			if (embBaseUrl.endsWith('/embeddings')) {
				embBaseUrl = embBaseUrl.replace(/\/embeddings$/, '');
			}
			embeddingClient = new OpenAI({ baseURL: embBaseUrl, apiKey: embeddingKey });
		}
		const embeddingOpts: EmbeddingOpts = { client: embeddingClient, model: embeddingModel };

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

		const section = await getEnabledSections();
		const enabledToolNames = getEnabledToolNames(section);
		const tools = enabledToolNames.map((name) => toolDefinitions[name]).filter(Boolean);

		const maxRecent = 10;
		let conversationMessages: OpenAI.Chat.ChatCompletionMessageParam[];
		if (messages.length > maxRecent) {
			const olderMessages = messages.slice(0, -maxRecent);
			const recentMessages = messages.slice(-maxRecent);

			const conversationText = olderMessages
				.filter((m: any) => (m.role === 'user' || m.role === 'assistant') && m.content)
				.map((m: any) => `${m.role}: ${typeof m.content === 'string' ? m.content : JSON.stringify(m.content)}`)
				.join('\n');

			if (conversationText.trim()) {
				const summaryCompletion = await openai.chat.completions.create({
					model: openaiModel!.trim(),
					messages: [
						{
							role: 'system',
							content:
								'Summarize this conversation history in 2-4 concise sentences. Focus on what was discussed, what questions were asked, and what answers were given. Keep it factual and brief.'
						},
						{ role: 'user', content: conversationText }
					]
				});
				const summary = summaryCompletion.choices?.[0]?.message?.content?.trim() ?? '';
				conversationMessages = [...(summary ? [{ role: 'user' as const, content: `[Previous conversation summary: ${summary}]` }, { role: 'assistant' as const, content: 'Understood.' }] : []), ...recentMessages];
			} else {
				conversationMessages = recentMessages;
			}
		} else {
			conversationMessages = messages;
		}

		conversationMessages = conversationMessages.filter((m: any) => m.role !== 'system');
		const loop: OpenAI.Chat.ChatCompletionMessageParam[] = [{ role: 'system', content: systemContent }, ...conversationMessages];

		const useThinking = terminalReasoning !== 'none';
		const modelLower = (openaiModel ?? '').toLowerCase();
		const thinkingKwargs = (() => {
			if (!useThinking) return {};
			if (modelLower.includes('glm')) return { chat_template_kwargs: { enable_thinking: true, clear_thinking: false } };
			if (modelLower.includes('nemotron')) return { chat_template_kwargs: { enable_thinking: true }, reasoning_budget: -1 };
			if (modelLower.includes('qwen')) return { chat_template_kwargs: { enable_thinking: true } };
			if (modelLower.includes('deepseek') || modelLower.includes('kimi')) return { chat_template_kwargs: { thinking: true } };
			return {};
		})();

		const isGemini = modelLower.includes('gemini');
		const completionParams = {
			model: openaiModel!.trim(),
			tools: tools.length > 0 ? tools : undefined,
			tool_choice: tools.length > 0 ? ('auto' as const) : undefined,
			...(useThinking ? { reasoning_effort: (isGemini && terminalReasoning === 'xhigh' ? 'high' : terminalReasoning) as any } : {}),
			...(!isGemini ? { frequency_penalty: 1.2 } : {}),
			...thinkingKwargs as any
		};

		for (let i = 0; i < 10; i++) {
			const completion = await openai.chat.completions.create({
				...completionParams,
				messages: loop
			});

			const message = completion.choices?.[0]?.message;
			if (!message) break;

			if (!message.tool_calls || message.tool_calls.length === 0) {
				break;
			}

			loop.push(message);

			const toolResults = await Promise.all(
				message.tool_calls.map(async (tc: any) => {
					const toolArgs = tc.function.arguments ? JSON.parse(tc.function.arguments) : {};
					const result = await executeTool(tc.function.name, toolArgs, section, embeddingOpts);
					return {
						role: 'tool' as const,
						tool_call_id: tc.id,
						content: result
					};
				})
			);

			loop.push(...toolResults);
		}

		const stream = await openai.chat.completions.create({
			...completionParams,
			messages: loop,
			stream: true
		} as any);

		const encoder = new TextEncoder();
		let fullResponse = '';
		const readable = new ReadableStream({
			async start(controller) {
				try {
					for await (const chunk of stream as any) {
						const content = (chunk as any).choices?.[0]?.delta?.content ?? '';
						if (content) {
							fullResponse += content;
							controller.enqueue(encoder.encode(`data: ${JSON.stringify({ content })}\n\n`));
						}
					}
					controller.enqueue(encoder.encode('data: [DONE]\n\n'));
					controller.close();

					if (loggerProvider) {
						const logger = loggerProvider.getLogger('terminal');
						const userMessage = messages[messages.length - 1]?.content ?? '';
						const cleaned = fullResponse
							.replace(/<reasoning>[\s\S]*?<\/reasoning>/gi, '')
							.replace(/<think>[\s\S]*?<\/think>/gi, '')
							.replace(/\(no output\)\s*/g, '')
							.trim();
						logger.emit({
							body: 'AI Terminal Interaction',
							attributes: {
								'terminal.user_input': userMessage,
								'terminal.system_prompt': systemContent,
								'terminal.ai_response': cleaned
							}
						});
					}
				} catch (err) {
					controller.enqueue(encoder.encode(`data: ${JSON.stringify({ error: 'Stream error' })}\n\n`));
					controller.close();
				}
			}
		});

		return new Response(readable, {
			headers: {
				'Content-Type': 'text/event-stream',
				'Cache-Control': 'no-cache',
				'Connection': 'keep-alive'
			}
		});
	} catch (error: any) {
		console.error('Terminal API Error:', error);
		return json({ response: `Error: ${error.message}` });
	}
};
