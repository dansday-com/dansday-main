<script lang="ts">
	import { onMount } from 'svelte';
	import Metadata from '$lib/components/metadata.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const general = (data.general ?? {}) as Record<string, unknown>;
	const metaTitle = (general.title as string) ?? data.siteName ?? '';
	const metaDescription = (general.description as string) ?? '';

	interface CommandHistory {
		command: string;
		output: string[];
		isError?: boolean;
	}

	let history: CommandHistory[] = $state([]);
	let currentInput = $state('');
	let inputElement: HTMLInputElement;
	let containerElement: HTMLDivElement;
	let isProcessing = $state(false);
	let cachedEntries: string[] = [];
	let tabIndex = $state(-1);
	let tabPrefix = '';
	let commandHistory: string[] = [];
	let historyNav = $state(-1);
	let savedInput = '';

	const username = (general.terminal_username as string) ?? '';
	const directory = '~';

	function handleSubmit(event: Event) {
		event.preventDefault();
		if (!isProcessing) {
			const command = currentInput.trim();
			if (command) {
				commandHistory.push(command);
				historyNav = -1;
				savedInput = '';
				executeCommand(command);
			} else {
				history = [...history, { command: '', output: [] }];
			}
			currentInput = '';
			setTimeout(() => {
				scrollToBottom();
			}, 10);
		}
	}

	async function executeCommand(command: string) {
		const args = command.split(' ').filter(Boolean);
		const cmd = args[0].toLowerCase();

		if (cmd === 'clear') {
			history = [];
			return;
		}

		const historyIndex = history.length;
		history = [...history, { command, output: [] }];
		isProcessing = true;

		setTimeout(() => scrollToBottom(), 10);

		try {
			const apiMessages = [];

			for (let i = 0; i < history.length - 1; i++) {
				const item = history[i];
				if (item.command) {
					apiMessages.push({ role: 'user', content: item.command });
					if (item.output.length > 0) {
						apiMessages.push({ role: 'assistant', content: item.output.join('\n') });
					}
				}
			}
			apiMessages.push({ role: 'user', content: command });

			const contextMessages = apiMessages.slice(-20);

			const response = await fetch('/api/terminal', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ messages: contextMessages })
			});

			if (!response.ok) {
				throw new Error('API request failed');
			}

			const data = await response.json();
			const fullText = data.response || '';

			const outputLines = fullText.split('\n');
			const updatedHistory = [...history];
			updatedHistory[historyIndex] = {
				...updatedHistory[historyIndex],
				output: outputLines
			};
			history = updatedHistory;

			if (cmd === 'ls' || cmd === 'dir') {
				cachedEntries = parseLsOutput(outputLines);
				tabIndex = -1;
				tabPrefix = '';
			}

			scrollToBottom();
		} catch (error) {
			const updatedHistory = [...history];
			updatedHistory[historyIndex] = {
				...updatedHistory[historyIndex],
				output: ['Error: Failed to communicate with AI server.'],
				isError: true
			};
			history = updatedHistory;
		} finally {
			isProcessing = false;
			setTimeout(() => {
				scrollToBottom();
				focusInput();
			}, 10);
		}
	}

	function parseLsOutput(output: string[]): string[] {
		return output
			.flatMap((line) => line.split(/\s{2,}/))
			.map((e) => e.trim())
			.filter((e) => e.length > 0 && !e.startsWith('total '));
	}

	function handleKeydown(event: KeyboardEvent) {
		if (event.key === 'Tab') {
			event.preventDefault();
			if (cachedEntries.length === 0) return;

			const parts = currentInput.split(' ');
			const lastPart = parts[parts.length - 1];

			if (tabIndex === -1) {
				tabPrefix = lastPart.toLowerCase();
			}

			const matches = cachedEntries.filter((e) => e.toLowerCase().startsWith(tabPrefix));
			if (matches.length === 0) return;

			tabIndex = (tabIndex + 1) % matches.length;
			parts[parts.length - 1] = matches[tabIndex];
			currentInput = parts.join(' ');
		} else if (event.key === 'ArrowUp') {
			event.preventDefault();
			if (commandHistory.length === 0) return;
			if (historyNav === -1) savedInput = currentInput;
			historyNav = Math.min(historyNav + 1, commandHistory.length - 1);
			currentInput = commandHistory[commandHistory.length - 1 - historyNav];
		} else if (event.key === 'ArrowDown') {
			event.preventDefault();
			if (historyNav <= 0) {
				historyNav = -1;
				currentInput = savedInput;
				return;
			}
			historyNav--;
			currentInput = commandHistory[commandHistory.length - 1 - historyNav];
		} else {
			tabIndex = -1;
			tabPrefix = '';
		}
	}

	function scrollToBottom() {
		if (containerElement) {
			containerElement.scrollTop = containerElement.scrollHeight;
		}
	}

	onMount(() => {
		if (inputElement) {
			inputElement.focus();
		}
	});

	function linkify(text: string): string {
		return text.replace(
			/https?:\/\/[^\s<>"')\]]+/g,
			(url) => `<a href="${url}" target="_blank" rel="noopener noreferrer" class="text-[#729fcf] underline hover:text-[#93b8e0]">${url}</a>`
		);
	}

	function escapeHtml(text: string): string {
		return text
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function formatLine(line: string): string {
		return linkify(escapeHtml(line));
	}

	function focusInput() {
		if (inputElement && !isProcessing) {
			inputElement.focus();
		}
	}
</script>

<Metadata title={metaTitle} description={metaDescription} />

<main
	class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-[#080808]/80 font-mono text-sm backdrop-blur-sm md:text-base"
	onclick={focusInput}
	role="presentation"
>
	<div bind:this={containerElement} class="text-ash-100 flex-1 p-4 pb-12 sm:p-6">
		<div class="mb-4">
			<div>Welcome to Ubuntu 24.04 LTS (GNU/Linux 6.6.87.2-microsoft-standard-WSL2 x86_64)</div>
			<br />
			<div>* Documentation: <a href="https://help.ubuntu.com" target="_blank" class="hover:underline">https://help.ubuntu.com</a></div>
			<div>* Management: <a href="https://landscape.canonical.com" target="_blank" class="hover:underline">https://landscape.canonical.com</a></div>
			<div>* Support: <a href="https://ubuntu.com/pro" target="_blank" class="hover:underline">https://ubuntu.com/pro</a></div>
			<br />
			<div>Type 'help' to see available commands.</div>
		</div>

		{#each history as item, index}
			<div class="mb-1">
				<div class="flex flex-wrap items-center">
					<span class="font-bold text-[#8ae234]">{username}</span>
					<span class="text-white">:</span>
					<span class="font-bold text-[#729fcf]">{directory}</span>
					<span class="mr-2 text-white">$</span>
					<span>{item.command}</span>
				</div>
				{#if item.output.length > 0}
					<div class="mt-1 whitespace-pre-wrap {item.isError ? 'text-red-400' : 'text-ash-100'}">
						{#each item.output as line}
							<div>{@html formatLine(line)}</div>
						{/each}
					</div>
				{:else if isProcessing && index === history.length - 1}
					<div class="text-ash-400 mt-1 flex items-center gap-1">
						<span class="animate-pulse">_</span>
					</div>
				{/if}
			</div>
		{/each}

		{#if !isProcessing}
			<form class="flex flex-wrap items-center" onsubmit={handleSubmit}>
				<span class="font-bold text-[#8ae234]">{username}</span>
				<span class="text-white">:</span>
				<span class="font-bold text-[#729fcf]">{directory}</span>
				<span class="mr-2 text-white">$</span>
				<input
					bind:this={inputElement}
					bind:value={currentInput}
					onkeydown={handleKeydown}
					class="flex-1 bg-transparent text-white outline-none"
					type="text"
					spellcheck="false"
					autocomplete="off"
					autofocus
				/>
			</form>
		{/if}
	</div>
</main>
