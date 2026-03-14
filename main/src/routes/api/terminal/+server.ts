import { json } from '@sveltejs/kit';
import { fetchGeneral } from '$lib/server/data';
import OpenAI from 'openai';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ request, getClientAddress }) => {
	try {
		const { messages } = await request.json();

		if (!Array.isArray(messages) || messages.length === 0) {
			return json({ error: 'Invalid messages array' }, { status: 400 });
		}

		const lastMessage = messages[messages.length - 1];
		if (lastMessage?.role === 'user') {
			let clientIp = 'unknown';
			try {
				clientIp =
					request.headers.get('cf-connecting-ip') ||
					request.headers.get('x-real-ip') ||
					request.headers.get('x-forwarded-for')?.split(',')[0].trim() ||
					getClientAddress();
			} catch (e) {}
			console.info(`[Terminal Activity] Command executed by IP ${clientIp}: ${lastMessage.content}`);
		}

		const generalData = await fetchGeneral();
		const openaiUrl = generalData.ai_url as string | null;
		const openaiKey = generalData.ai_key as string | null;
		const openaiModel = generalData.ai_model as string | null;

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

		const systemPrompt = {
			role: 'system',
			content: `You are an AI assistant integrated into a web-based Ubuntu terminal emulator. 
Your username is "dansday@ai" and you operate in a CLI environment. 
Respond to user input as if you are a terminal command output or a helpful CLI assistant.
Keep responses concise, formatted as plain text, and visually resembling terminal output where appropriate.
If the user types a standard Linux command, you can simulate its output or provide helpful information.
If they ask a general question, answer it concisely in a terminal-friendly format.`
		};

		try {
			const completion = await openai.chat.completions.create({
				model: openaiModel.trim(),
				messages: [systemPrompt, ...messages] as OpenAI.Chat.ChatCompletionMessageParam[]
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
