<script lang="ts">
	import { onMount } from 'svelte';

	interface Props {
		id: string;
	}

	let { id }: Props = $props();
	let loaded = false;

	function loadAnalytics() {
		if (loaded || !id || typeof id !== 'string' || !id.trim()) return;
		loaded = true;

		const script1 = document.createElement('script');
		script1.async = true;
		script1.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id.trim())}`;
		document.head.appendChild(script1);

		const script2 = document.createElement('script');
		script2.textContent = `
			window.dataLayer = window.dataLayer || [];
			function gtag(){dataLayer.push(arguments);}
			gtag('js', new Date());
			gtag('config', ${JSON.stringify(id.trim())});
		`;
		document.head.appendChild(script2);
	}

	onMount(() => {
		if (!id || typeof id !== 'string' || !id.trim()) return;

		const handleInteraction = () => {
			loadAnalytics();
			cleanup();
		};

		const events = ['scroll', 'mousemove', 'touchstart', 'keydown', 'click'];
		events.forEach((event) => window.addEventListener(event, handleInteraction, { once: true, passive: true }));

		// Load after a delay even if there's no interaction
		const timeoutId = setTimeout(handleInteraction, 3500);

		function cleanup() {
			events.forEach((event) => window.removeEventListener(event, handleInteraction));
			clearTimeout(timeoutId);
		}

		return cleanup;
	});
</script>
