import { json } from '@sveltejs/kit';
import { fetchGeneral } from '$lib/server/data';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ request }) => {
	try {
		const { messages } = await request.json();

		if (!Array.isArray(messages) || messages.length === 0) {
			return json({ error: 'Invalid messages array' }, { status: 400 });
		}

		const lastMessage = messages[messages.length - 1];
		if (lastMessage?.role === 'user') {
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

		const systemMessages = terminalPrompt
			? [{ role: 'system' as const, content: terminalPrompt }]
			: [];

		try {
			const completion = await openai.chat.completions.create({
				model: openaiModel.trim(),
				messages: [...systemMessages, ...messages] as OpenAI.Chat.ChatCompletionMessageParam[]
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
