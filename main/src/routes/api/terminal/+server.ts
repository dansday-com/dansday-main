import { json } from '@sveltejs/kit';
import { fetchGeneral, fetchSection } from '$lib/server/data';
import { buildDataNote, buildTerminalTools, runTerminalTool } from '$lib/server/terminal/tools';
import { buildWebTools, isWebTool, runWebTool, webConfigFrom } from '$lib/server/terminal/web';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';
import loggerProvider from '../../../../otel/logger.js';

const MAX_RECENT = 10;
const REQUEST_TIMEOUT_MS = 120_000;
const MAX_TOOL_ITERATIONS = 5;

function normalizeBaseUrl(rawUrl: string): string {
	const trimmed = rawUrl.trim().replace(/\/+$/, '');
	return trimmed.endsWith('/chat/completions') ? trimmed.slice(0, -'/chat/completions'.length) : trimmed;
}

function buildCompletionParams(model: string, reasoning: string) {
	const useThinking = reasoning !== 'none';
	const modelLower = model.toLowerCase();
	const isGemini = modelLower.includes('gemini');

	const thinkingKwargs = (() => {
		if (!useThinking) return {};
		if (modelLower.includes('glm')) return { chat_template_kwargs: { enable_thinking: true, clear_thinking: false } };
		if (modelLower.includes('nemotron')) return { chat_template_kwargs: { enable_thinking: true }, reasoning_budget: -1 };
		if (modelLower.includes('qwen')) return { chat_template_kwargs: { enable_thinking: true } };
		if (modelLower.includes('deepseek') || modelLower.includes('kimi')) return { chat_template_kwargs: { thinking: true } };
		return {};
	})();

	return {
		model,
		...(useThinking ? { reasoning_effort: (isGemini && reasoning === 'xhigh' ? 'high' : reasoning) as any } : {}),
		...(!isGemini ? { frequency_penalty: 0.3 } : {}),
		...thinkingKwargs
	};
}

async function callChatCompletions(
	client: OpenAI,
	params: ReturnType<typeof buildCompletionParams>,
	messages: OpenAI.Chat.ChatCompletionMessageParam[],
	tools: OpenAI.Chat.ChatCompletionTool[],
	onToolCall: (name: string, args: Record<string, any>) => Promise<string>
): Promise<string> {
	const loop = [...messages];
	const toolResultCache = new Map<string, string>();
	let lastContent = '';

	for (let i = 0; i < MAX_TOOL_ITERATIONS; i++) {
		const lastIteration = i === MAX_TOOL_ITERATIONS - 1;
		const offerTools = tools.length > 0 && !lastIteration;

		const completion = await client.chat.completions.create({
			...params,
			...(offerTools ? { tools, tool_choice: 'auto' as const } : {}),
			messages: loop
		} as any);

		const choice = completion.choices?.[0]?.message;
		if (!choice) return lastContent;

		if (typeof choice.content === 'string' && choice.content.trim()) lastContent = choice.content;

		if (!choice.tool_calls?.length) return choice.content ?? lastContent;

		loop.push(choice);

		const pending = new Map<string, Promise<string>>();

		for (const call of choice.tool_calls as any[]) {
			const cacheKey = `${call.function.name}:${call.function.arguments ?? ''}`;
			if (toolResultCache.has(cacheKey) || pending.has(cacheKey)) continue;
			let args: Record<string, any> = {};
			try {
				args = call.function.arguments ? JSON.parse(call.function.arguments) : {};
			} catch {
				args = {};
			}
			pending.set(cacheKey, onToolCall(call.function.name, args));
		}

		const keys = [...pending.keys()];
		const settled = await Promise.all(pending.values());
		keys.forEach((key, index) => toolResultCache.set(key, settled[index]));

		for (const call of choice.tool_calls as any[]) {
			const cacheKey = `${call.function.name}:${call.function.arguments ?? ''}`;
			loop.push({ role: 'tool', tool_call_id: call.id, content: toolResultCache.get(cacheKey) as string });
		}
	}

	return lastContent;
}

export const POST: RequestHandler = async ({ request }) => {
	try {
		const { messages } = await request.json();

		if (!Array.isArray(messages) || messages.length === 0) {
			return json({ error: 'Invalid messages array' }, { status: 400 });
		}

		const generalData = await fetchGeneral();
		const apiUrl = (generalData.ai_url as string | null)?.trim() ?? '';
		const apiKey = (generalData.ai_key as string | null)?.trim() ?? '';
		const model = (generalData.ai_model as string | null)?.trim() ?? '';
		const terminalPrompt = (generalData.ai_terminal_prompt as string | null)?.trim() ?? '';
		const terminalReasoning = (generalData.ai_terminal_reasoning as string | null) ?? 'none';

		if (!apiUrl || !apiKey || !model) {
			return json({
				response: 'Error: AI Terminal is not configured. Please set the AI URL, Key, and Model in the admin settings.'
			});
		}

		const client = new OpenAI({ baseURL: normalizeBaseUrl(apiUrl), apiKey, timeout: REQUEST_TIMEOUT_MS, maxRetries: 0 });

		const section = await fetchSection();
		const webConfig = webConfigFrom(generalData);
		const siteTools = buildTerminalTools(section);
		const tools = [...siteTools, ...buildWebTools(webConfig)];

		const today = new Date().toISOString().slice(0, 10);
		const systemContent = [terminalPrompt.replaceAll('{{today}}', today), buildDataNote(siteTools)].filter(Boolean).join('\n\n');

		const conversation = (messages as OpenAI.Chat.ChatCompletionMessageParam[]).filter((m) => m.role !== 'system').slice(-MAX_RECENT);

		const fullResponse = await callChatCompletions(
			client,
			buildCompletionParams(model, terminalReasoning),
			[...(systemContent ? [{ role: 'system' as const, content: systemContent }] : []), ...conversation],
			tools,
			(name, args) => (isWebTool(name) ? runWebTool(name, args, webConfig) : runTerminalTool(name, args, section))
		);

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

		return json({ response: fullResponse });
	} catch (error: any) {
		const status = error instanceof OpenAI.APIError ? error.status : null;
		console.error(`Terminal API Error${status ? ` (${status})` : ''}:`, error);
		const notice =
			status === 401 || status === 403
				? 'The AI API key was rejected. Please check the admin settings.'
				: status === 429
					? 'The AI service is rate limited right now. Please try again in a moment.'
					: 'Something went wrong while contacting the AI service.';
		return json({ response: `Error: ${notice}` });
	}
};
