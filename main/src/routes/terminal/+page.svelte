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

	const username = 'dansday@ai';
	const directory = '~';

	function handleKeydown(event: KeyboardEvent) {
		if (event.key === 'Enter' && !isProcessing) {
			const command = currentInput.trim();
			if (command) {
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

			const updatedHistory = [...history];
			updatedHistory[historyIndex] = {
				...updatedHistory[historyIndex],
				output: (data.response || '').split('\n')
			};
			history = updatedHistory;
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

	function focusInput() {
		if (inputElement && !isProcessing) {
			inputElement.focus();
		}
	}
</script>

<Metadata title={metaTitle} description={metaDescription} image={data.defaultOgImage} />

<main class="flex min-h-0 flex-1 flex-col font-mono text-sm md:text-base relative" onclick={focusInput} role="presentation">
	<div class="absolute inset-0 bg-[#080808]/80 backdrop-blur-sm -z-10"></div>
	<div bind:this={containerElement} class="text-ash-100 flex-1 overflow-y-auto p-4 sm:p-6 pb-12 z-10">
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
							<div>{line}</div>
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
			<div class="flex flex-wrap items-center">
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
			</div>
		{/if}
	</div>
</main>
