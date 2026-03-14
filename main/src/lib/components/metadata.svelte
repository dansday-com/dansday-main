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
	{#if image}
		<meta property="og:image" content={image} />
	{/if}
</svelte:head>
