<script lang="ts">
	interface $$Props {
		siteName: string;
		favicon: string;
		isFullscreen: boolean;
		onMouseDown: (e: MouseEvent) => void;
		toggleFullscreen: () => void;
		onMinimize: () => void;
	}

	let { siteName, favicon, isFullscreen, onMouseDown, toggleFullscreen, onMinimize }: $$Props = $props();

	function handleHeaderKeyDown(event: KeyboardEvent) {
		if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			toggleFullscreen();
		}
	}
</script>

<header
	class={`relative flex cursor-default items-center justify-between overflow-hidden px-4 py-3 ${isFullscreen ? 'lg:cursor-pointer' : 'lg:cursor-grab lg:active:cursor-grabbing'}`}
	ondblclick={toggleFullscreen}
	onmousedown={onMouseDown}
	onkeydown={handleHeaderKeyDown}
>
	<p class="not-sr-only flex items-center gap-2 font-semibold select-none max-lg:mx-auto">
		{#if favicon}<img src={favicon} alt="" class="hidden h-4 w-4 lg:block" />{/if}{siteName}
	</p>
	<div class="hidden items-center lg:flex">
		<button class="group grid h-8 w-10 place-items-center hover:bg-white/10" onclick={onMinimize} aria-label="Minimize">
			<svg width="10" height="1" viewBox="0 0 10 1" fill="currentColor" class="text-[#898989] group-hover:text-white"><rect width="10" height="1" /></svg>
		</button>
		<button
			class="group grid h-8 w-10 place-items-center hover:bg-white/10"
			onclick={toggleFullscreen}
			aria-label={isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'}
		>
			<svg width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1" class="text-[#898989] group-hover:text-white"
				><rect x="0.5" y="0.5" width="9" height="9" /></svg
			>
		</button>
		<button class="group grid h-8 w-10 place-items-center hover:bg-[#e81123]" onclick={() => window.close()} aria-label="Close">
			<svg width="10" height="10" viewBox="0 0 10 10" stroke="currentColor" stroke-width="1.2" class="text-[#898989] group-hover:text-white"
				><line x1="0" y1="0" x2="10" y2="10" /><line x1="10" y1="0" x2="0" y2="10" /></svg
			>
		</button>
	</div>
</header>
