import { json } from '@sveltejs/kit';
import { fetchGeneral } from '$lib/server/data';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ request }) => {
	try {
		const { messages } = await request.json();

		if (!Array.isArray(messages) || messages.length === 0) {
			return json({ error: 'Invalid messages array' }, { status: 400 });
		}

		const generalData = await fetchGeneral();
		const openaiUrl = generalData.openai_url as string | null;
		const openaiKey = generalData.openai_key as string | null;
		const openaiModel = generalData.openai_model as string | null;

		if (!openaiUrl || !openaiKey || !openaiModel) {
			return json(
				{ 
					response: "Error: AI Terminal is not configured. Please set the OpenAI URL, Key, and Model in the admin settings." 
				}
			);
		}

		let apiUrl = openaiUrl.trim().replace(/\/+$/, '');
		if (!apiUrl.endsWith('/chat/completions')) {
			if (!apiUrl.endsWith('/v1')) {
				apiUrl += '/v1';
			}
			apiUrl += '/chat/completions';
		}

		const systemPrompt = {
			role: 'system',
			content: `You are an AI assistant integrated into a web-based Ubuntu terminal emulator. 
Your username is "dansday@ai" and you operate in a CLI environment. 
Respond to user input as if you are a terminal command output or a helpful CLI assistant.
Keep responses concise, formatted as plain text, and visually resembling terminal output where appropriate.
If the user types a standard Linux command, you can simulate its output or provide helpful information.
If they ask a general question, answer it concisely in a terminal-friendly format.`
		};

		const payload = {
			model: openaiModel,
			messages: [systemPrompt, ...messages],
			temperature: 0.7,
			max_tokens: 1000
		};

		const response = await fetch(apiUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': `Bearer ${openaiKey}`
			},
			body: JSON.stringify(payload)
		});

		if (!response.ok) {
			const errorData = await response.text();
			console.error('OpenAI API Error:', errorData);
			return json(
				{ 
					response: `Error: Failed to connect to AI service. HTTP ${response.status}\n${errorData}` 
				}
			);
		}

		const data = await response.json();
		const reply = data.choices?.[0]?.message?.content || 'No response from AI.';

		return json({ response: reply });
	} catch (error: any) {
		console.error('Terminal API Error:', error);
		return json({ response: `Error: ${error.message}` });
	}
};
