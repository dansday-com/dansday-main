<script lang="ts">
	import Metadata from '$lib/components/metadata.svelte';
	import { resolveImageUrl } from '$lib/image';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
	const posterUrl = resolveImageUrl(data.meta.poster, data.adminBaseUrl ?? '');
</script>

<Metadata title={data.meta.title} description={data.meta.description} image={posterUrl} />

<header class="group border-ash-700 bg-ash-700 relative mb-5 aspect-3/1 overflow-hidden border">
	<img src={posterUrl} alt={data.meta.title} class="size-full object-cover object-center" fetchpriority="high" loading="eager" />
	<div class="absolute top-0 left-0 grid h-full w-full place-items-center bg-[#080808]/80 transition-opacity duration-500 group-hover:opacity-0">
		<h1 class="max-w-[80%] px-4 text-center text-2xl font-semibold uppercase lg:max-w-[60%] lg:text-4xl">
			{data.meta.title}
		</h1>
	</div>
	<div aria-hidden="true" class="absolute top-0 left-0 h-full w-full bg-repeat opacity-2 group-hover:opacity-0"></div>
</header>

<article
	class="prose prose-hr:border-ash-600 prose-pre:bg-ash-800/20 prose-code:before:opacity-0 prose-code:after:opacity-0 prose-code:text-ash-200 prose-code:font-mono prose-pre:border prose-headings:text-ash-200 prose-p:text-ash-400 prose-strong:text-ash-200 prose-li:text-ash-400 prose-li:marker:text-ash-300 prose-a:text-ash-200 prose-blockquote:border-ash-500 prose-p:indent-5 prose-td:text-ash-300 prose-thead:border-ash-300 prose-tr:border-ash-500 mx-auto max-w-3xl font-sans"
>
	{@html data.body}
</article>
