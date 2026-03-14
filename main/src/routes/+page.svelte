<script lang="ts">
	import Metadata from '$lib/components/metadata.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
	const general = (data.general ?? {}) as Record<string, unknown>;
	const home = (data.home ?? {}) as Record<string, unknown>;
	const metaTitle = (general.title as string) ?? data.siteName ?? '';
	const metaDescription = (general.description as string) ?? '';
</script>

<Metadata title={metaTitle} description={metaDescription} image={data.defaultOgImage} />

<section class="flex grow flex-col items-center justify-center space-y-2.5 md:space-y-5">
	<div class="flex flex-col items-center justify-center text-center">
		{#if data.homeTitleAscii}
			<pre
				class="max-w-full overflow-x-auto text-left font-mono text-[0.4rem] leading-tight sm:text-[0.5rem] md:text-[0.6rem] lg:text-[0.65rem]"
				aria-label={home.title as string}>{data.homeTitleAscii}</pre>
		{:else if home.title}
			<h1 class="text-lg font-medium md:text-xl">{home.title}</h1>
		{/if}
		{#if home.description}
			<div class="text-sm whitespace-pre-wrap md:text-base">{home.description}</div>
		{/if}
	</div>
</section>
