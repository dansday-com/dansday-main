import type OpenAI from 'openai';

const SEARCH_PATH = '/search';
const FETCH_PATH = '/web/fetch';

const DEFAULT_TOOL_TIMEOUT_MS = 20_000;
const MAX_RESULTS = 5;
const MAX_RESULTS_CAP = 10;
const MAX_SNIPPET_LENGTH = 400;
const MAX_FETCH_CHARACTERS = 6000;
const THIN_SNIPPET_LENGTH = 160;

const SEARCH_DESCRIPTION = `Search the live web. This is your lookup for anything outside this site — news, current events, prices, release dates, versions, who someone is, whether something is real, how something works, or any claim where your own memory could be out of date.

The site tools are the source for what is on this site: articles, projects, GitHub activity, the about page. Use those for questions about this site and its owner's work, and use this for everything else.

Keep going until you actually have the answer. If the results are thin, contradict each other, or only half answer the question, do not answer yet: open the most promising result with fetch_web_page, or search again with different keywords. Only say you could not find something after you have really tried.

Do not call this for chatting, greetings, jokes, opinions, or questions about you. Answer only with facts present in the results, never fill a gap from memory, and say plainly when nothing useful came back. Write the query in English even when the user wrote another language, then reply in their language.`;

const FETCH_DESCRIPTION = `Read the full text of one web page by its URL. Use this whenever a search snippet looks like the answer but is too short to answer from confidently, whenever you need an exact number, price, date or version, and whenever the user gives you a link and asks what it says. Reading the page beats answering from a snippet.

You must already have the exact URL — from the user's message or from a search_web result. Never invent or guess a URL. One page per call. Answer only from the text that comes back; if the page turns out not to have it, go back to search_web and try the next result rather than giving up.`;

const SEARCH_EMPTY_HINT =
	'Nothing came back for this query. Do not tell the user you could not find it yet — search again with different or broader English keywords.';

const SEARCH_THIN_HINT = 'These snippets are too short to answer from confidently. Open the most relevant result with fetch_web_page before you answer.';

const SEARCH_FAILED_HINT = 'This search did not go through. Try search_web once more before you tell the user anything.';

const FETCH_FAILED_HINT =
	'This page could not be read. Go back to your search_web results and open a different one, or search again — do not answer from memory.';

export interface WebEndpoint {
	api_url: string;
	api_key: string;
	model: string;
}

export interface WebConfig {
	search: WebEndpoint;
	fetch: WebEndpoint;
}

function trimmed(value: unknown): string {
	return typeof value === 'string' ? value.trim() : '';
}

export function webConfigFrom(general: Record<string, unknown>): WebConfig {
	return {
		search: { api_url: trimmed(general.search_api_url), api_key: trimmed(general.search_api_key), model: trimmed(general.search_model) },
		fetch: { api_url: trimmed(general.fetch_api_url), api_key: trimmed(general.fetch_api_key), model: trimmed(general.fetch_model) }
	};
}

function configured(endpoint: WebEndpoint): boolean {
	return Boolean(endpoint.api_url && endpoint.api_key && endpoint.model);
}

function resolveToolUrl(rawUrl: string, path: string): string {
	const base = rawUrl.trim().replace(/\/+$/, '');
	if (!base) return '';
	return base.endsWith(path) ? base : `${base}${path}`;
}

async function postJson(url: string, apiKey: string, body: Record<string, unknown>, timeoutMs = DEFAULT_TOOL_TIMEOUT_MS): Promise<any> {
	const controller = new AbortController();
	const timer = setTimeout(() => controller.abort(), timeoutMs);

	try {
		const res = await fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${apiKey}` },
			body: JSON.stringify(body),
			signal: controller.signal
		});

		if (!res.ok) {
			const detail = await res.text().catch(() => '');
			const error: any = new Error(`HTTP ${res.status}${detail ? ` ${detail.slice(0, 200)}` : ''}`);
			error.status = res.status;
			error.body = detail;
			throw error;
		}

		return await res.json();
	} finally {
		clearTimeout(timer);
	}
}

function clampResults(raw: unknown): number {
	const count = Number(raw);
	if (!Number.isFinite(count) || count <= 0) return MAX_RESULTS;
	return Math.min(Math.trunc(count), MAX_RESULTS_CAP);
}

export async function runSearchTool(config: WebConfig, args: Record<string, any>) {
	const endpoint = config.search;
	if (!configured(endpoint)) return { ok: false, reason: 'web_search_not_configured' };

	const query = String(args?.query ?? '').trim();
	if (!query) return { ok: false, reason: 'missing_query' };

	const searchType = args?.search_type === 'news' ? 'news' : 'web';

	try {
		const payload = await postJson(resolveToolUrl(endpoint.api_url, SEARCH_PATH), endpoint.api_key, {
			model: endpoint.model,
			query,
			search_type: searchType,
			max_results: clampResults(args?.max_results)
		});

		const results = Array.isArray(payload?.results) ? payload.results : [];
		if (!results.length) return { ok: false, reason: 'no_results', query, next_step: SEARCH_EMPTY_HINT };

		const mapped = results.slice(0, MAX_RESULTS_CAP).map((entry: any) => ({
			title: entry?.title ?? null,
			url: entry?.url ?? null,
			snippet: typeof entry?.snippet === 'string' ? entry.snippet.slice(0, MAX_SNIPPET_LENGTH) : null,
			published_at: entry?.published_at ?? null
		}));

		const answer = payload?.answer ?? null;
		const thin = !answer && mapped.every((entry: { snippet: string | null }) => (entry.snippet ?? '').trim().length < THIN_SNIPPET_LENGTH);

		return {
			ok: true,
			query,
			answer,
			results: mapped,
			...(thin ? { next_step: SEARCH_THIN_HINT } : {})
		};
	} catch (error: any) {
		console.error(`Web search failed: ${error.message}`);
		return { ok: false, reason: error.name === 'AbortError' ? 'timeout' : 'search_failed', next_step: SEARCH_FAILED_HINT };
	}
}

export async function runFetchTool(config: WebConfig, args: Record<string, any>) {
	const endpoint = config.fetch;
	if (!configured(endpoint)) return { ok: false, reason: 'web_fetch_not_configured' };

	const url = String(args?.url ?? '').trim();
	if (!/^https?:\/\//i.test(url)) return { ok: false, reason: 'invalid_url', next_step: FETCH_FAILED_HINT };

	try {
		const payload = await postJson(resolveToolUrl(endpoint.api_url, FETCH_PATH), endpoint.api_key, {
			model: endpoint.model,
			url,
			format: 'markdown',
			max_characters: MAX_FETCH_CHARACTERS
		});

		const text = payload?.content?.text ?? '';
		if (!text) return { ok: false, reason: 'empty_page', url, next_step: FETCH_FAILED_HINT };

		return {
			ok: true,
			url: payload?.url ?? url,
			title: payload?.title ?? null,
			content: String(text).slice(0, MAX_FETCH_CHARACTERS),
			truncated: String(text).length > MAX_FETCH_CHARACTERS
		};
	} catch (error: any) {
		console.error(`Web fetch failed: ${error.message}`);
		return { ok: false, reason: error.name === 'AbortError' ? 'timeout' : 'fetch_failed', next_step: FETCH_FAILED_HINT };
	}
}

export function buildWebTools(config: WebConfig): OpenAI.Chat.ChatCompletionTool[] {
	const tools: OpenAI.Chat.ChatCompletionTool[] = [];

	if (configured(config.search)) {
		tools.push({
			type: 'function',
			function: {
				name: 'search_web',
				description: SEARCH_DESCRIPTION,
				parameters: {
					type: 'object',
					properties: {
						query: {
							type: 'string',
							description: 'What to search for, in English, kept short — keywords rather than a full sentence or question.'
						},
						search_type: {
							type: 'string',
							enum: ['web', 'news'],
							description: 'Use "news" only when the user asks for recent news or current events. Otherwise leave this out.'
						},
						max_results: {
							type: 'integer',
							description: `How many results to read, 1 to ${MAX_RESULTS_CAP}. Leave out for ${MAX_RESULTS}.`
						}
					},
					required: ['query']
				}
			}
		});
	}

	if (configured(config.fetch)) {
		tools.push({
			type: 'function',
			function: {
				name: 'fetch_web_page',
				description: FETCH_DESCRIPTION,
				parameters: {
					type: 'object',
					properties: {
						url: {
							type: 'string',
							description: 'The full http:// or https:// URL to read. Must be a URL you were given or found in a search result, never one you made up.'
						}
					},
					required: ['url']
				}
			}
		});
	}

	return tools;
}

export function isWebTool(name: string): boolean {
	return name === 'search_web' || name === 'fetch_web_page';
}

export async function runWebTool(name: string, args: Record<string, any>, config: WebConfig): Promise<string> {
	const result = name === 'search_web' ? await runSearchTool(config, args) : await runFetchTool(config, args);
	return JSON.stringify(result);
}
