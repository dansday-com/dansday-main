<script lang="ts">
	import { page } from '$app/state';
	import { MediaQuery } from 'svelte/reactivity';

	interface $$Props {
		title: string;
		description: string;
		image?: string;
		canonical?: string;
	}

	let { title, description, image = '', canonical = '' }: $$Props = $props();

	let isMobile = $derived(new MediaQuery('(max-width: 1024px)').current);
	let finalCanonical = $derived(canonical || page.url.href);
	let finalImage = $derived(image || `${page.url.origin}/og.png`);
	let siteName = $derived((page.data.siteName as string) ?? '');
</script>

<svelte:head>
	<title>{title}</title>
	<meta name="description" content={description} />
	<meta name="theme-color" content={isMobile ? '#262626' : '#454545'} />

	<link rel="canonical" href={finalCanonical} />

	<meta property="og:url" content={finalCanonical} />
	<meta property="og:type" content="website" />
	<meta property="og:title" content={title} />
	<meta property="og:description" content={description} />
	<meta property="og:image" content={finalImage} />
	{#if siteName}
		<meta property="og:site_name" content={siteName} />
	{/if}

	<meta name="twitter:card" content="summary_large_image" />
	<meta name="twitter:title" content={title} />
	<meta name="twitter:description" content={description} />
	<meta name="twitter:image" content={finalImage} />
</svelte:head>
