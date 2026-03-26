<script lang="ts">
	import { MediaQuery } from 'svelte/reactivity';

	import '../app.css';
	import Header from '$lib/components/layout/header/header.svelte';
	import Navbar from '$lib/components/layout/navbar/navbar.svelte';
	import GoogleAnalytics from '$lib/components/GoogleAnalytics.svelte';

	import type { LayoutProps } from './$types';

	let { data, children }: LayoutProps = $props();

	const trackingId = $derived(typeof data.general?.analytics_code === 'string' && data.general.analytics_code.trim() ? data.general.analytics_code.trim() : '');

	let dragging = $state(false);
	let isFullscreen = $state(false);
	let isMinimized = $state(false);
	let offset = $state({ x: 0, y: 0 });
	let position = $state({ x: 0, y: 0 });
	let containerElement = $state<HTMLElement | null>(null);
	let isMobile = $derived(new MediaQuery('(max-width: 1024px)').current);
	let clock = $state({ time: '', date: '' });

	$effect(() => {
		if (isMobile) return;
		function tick() {
			const now = new Date();
			clock = {
				time: now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
				date: now.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' })
			};
		}
		tick();
		const interval = setInterval(tick, 1000);
		return () => clearInterval(interval);
	});

	function toggleFullscreen() {
		if (isMobile) return;
		if (!isFullscreen) containerElement?.requestFullscreen();
		else {
			document.exitFullscreen();
			position = { x: 0, y: 0 };
		}
		isFullscreen = !isFullscreen;
	}

	function onMinimize() {
		if (isMobile) return;
		isMinimized = true;
	}

	function toggleRestore() {
		isMinimized = !isMinimized;
	}

	function onMouseDown(e: MouseEvent) {
		if (isMobile) return;
		dragging = true;
		offset = { x: e.clientX - position.x, y: e.clientY - position.y };
	}

	$effect(() => {
		if (isMobile) return;
		const controller = new AbortController();

		const handleMouseUp = () => (dragging = false);

		const handleMouseMove = (e: MouseEvent) => {
			if (dragging) position = { x: e.clientX - offset.x, y: e.clientY - offset.y };
		};

		const handleFullscreenChange = () => {
			if (!document.fullscreenElement) isFullscreen = false;
		};

		window.addEventListener('mouseup', handleMouseUp, { signal: controller.signal });
		window.addEventListener('mousemove', handleMouseMove, { signal: controller.signal });
		document.addEventListener('fullscreenchange', handleFullscreenChange, { signal: controller.signal });

		return () => controller.abort();
	});

	$effect(() => {
		if (!isMobile) return;

		position = { x: 0, y: 0 };
	});
</script>

<svelte:head>
	{#if data.favicons && data.favicons.length > 0}
		{#each data.favicons as favicon}
			<link
				rel={favicon.rel}
				type={favicon.type}
				href={favicon.href}
				sizes={favicon.sizes}
				crossorigin={favicon.rel === 'manifest' ? 'use-credentials' : undefined}
			/>
		{/each}
	{:else if data.defaultFavicon}
		<link rel="icon" href={data.defaultFavicon} />
	{/if}
	<link rel="preconnect" href={data.adminBaseUrl} crossorigin="anonymous" />
</svelte:head>

{#if trackingId}
	<GoogleAnalytics id={trackingId} />
{/if}

<main
	bind:this={containerElement}
	data-fullscreen={isFullscreen || isMobile}
	class="from-ash-800 to-ash-700 z-10 flex h-dvh w-dvw flex-col overflow-hidden rounded-none bg-linear-to-tr data-[fullscreen=true]:rounded-none lg:h-[75dvh] lg:w-[70dvw] lg:rounded-xl"
	class:container-shadow={!isFullscreen || !isMobile}
	class:minimized={isMinimized && !isMobile}
	style:transform="translate({position.x}px, {position.y}px)"
	style:transition={dragging ? 'none' : 'all 0.2s cubic-bezier(0.4, 0, 0, 1)'}
>
	<Header siteName={data.siteName} favicon={data.defaultFavicon} {isFullscreen} {onMouseDown} {toggleFullscreen} {onMinimize} />
	{@render children()}
	<Navbar siteName={data.siteName} socialLinks={data.socialLinks} section={data.section} aiTerminalConfigured={data.aiTerminalConfigured} adminBaseUrl={data.adminBaseUrl} />
</main>

<div class="desktop-bg absolute top-0 left-0 hidden h-full w-full lg:block" aria-hidden="true"></div>

<div class="fixed bottom-0 left-0 z-50 hidden h-11 w-full items-center border-t border-white/10 bg-[#2c2c2c]/80 backdrop-blur-xl lg:flex" aria-label="Taskbar">
	<div class="flex flex-1 items-center justify-center">
		<button class="group relative grid h-10 w-10 place-items-center rounded hover:bg-white/10" onclick={toggleRestore} aria-label="Terminal">
			{#if data.defaultFavicon}<img src={data.defaultFavicon} alt="" class="h-5 w-5" />{/if}
			<span class="absolute bottom-0.5 h-0.5 rounded-full transition-all duration-300 {isMinimized ? 'w-1.5 bg-[#898989]' : 'w-4 bg-[#4CC2FF]'}"></span>
		</button>
	</div>
	<div class="text-ash-300 flex flex-col items-end rounded px-3 py-1 text-xs leading-tight hover:bg-white/10">
		<span>{clock.time}</span>
		<span>{clock.date}</span>
	</div>
</div>

{#if !isMobile}
	{#await import('$lib/components/layout/particle.svelte') then { default: Particle }}
		<Particle />
	{/await}
{/if}

<style>
	.desktop-bg {
		background: linear-gradient(135deg, #1a2332 0%, #1e3a5f 30%, #2a4a6b 50%, #1a2332 100%);
	}

	.minimized {
		transform: translateY(calc(100vh - 2.75rem)) scale(0.4) !important;
		opacity: 0;
		pointer-events: none;
	}

	.container-shadow {
		animation: animate-wave-shadow 8s ease-in-out infinite;
	}

	@keyframes animate-wave-shadow {
		0%,
		100% {
			box-shadow:
				0px 0px 0px 1px rgba(165, 165, 165, 0.04),
				-9px 9px 9px -0.5px rgba(0, 0, 0, 0.04),
				-18px 18px 18px -1.5px rgba(0, 0, 0, 0.08),
				-37px 37px 37px -3px rgba(0, 0, 0, 0.16),
				-75px 75px 75px -6px rgba(0, 0, 0, 0.24),
				-150px 150px 150px -12px rgba(0, 0, 0, 0.48);
		}
		25% {
			box-shadow:
				0px 0px 0px 1px rgba(165, 165, 165, 0.04),
				-7px 11px 9px -0.5px rgba(0, 0, 0, 0.04),
				-14px 22px 18px -1.5px rgba(0, 0, 0, 0.08),
				-29px 45px 37px -3px rgba(0, 0, 0, 0.16),
				-59px 91px 75px -6px rgba(0, 0, 0, 0.24),
				-118px 182px 150px -12px rgba(0, 0, 0, 0.48);
		}
		50% {
			box-shadow:
				0px 0px 0px 1px rgba(165, 165, 165, 0.04),
				-9px 9px 9px -0.5px rgba(0, 0, 0, 0.04),
				-18px 18px 18px -1.5px rgba(0, 0, 0, 0.08),
				-37px 37px 37px -3px rgba(0, 0, 0, 0.16),
				-75px 75px 75px -6px rgba(0, 0, 0, 0.24),
				-150px 150px 150px -12px rgba(0, 0, 0, 0.48);
		}
		75% {
			box-shadow:
				0px 0px 0px 1px rgba(165, 165, 165, 0.04),
				-11px 7px 9px -0.5px rgba(0, 0, 0, 0.04),
				-22px 14px 18px -1.5px rgba(0, 0, 0, 0.08),
				-45px 29px 37px -3px rgba(0, 0, 0, 0.16),
				-91px 59px 75px -6px rgba(0, 0, 0, 0.24),
				-182px 118px 150px -12px rgba(0, 0, 0, 0.48);
		}
	}
</style>
