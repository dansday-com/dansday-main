<script lang="ts">
	import Metadata from '$lib/components/metadata.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
	const pageTitle = $derived(data.siteName ? `Articles | ${data.siteName}` : 'Articles');
	const description = $derived((data.articlesListMeta?.description as string) ?? '');
</script>

<Metadata title={pageTitle} {description} />

<h1 class="sr-only">{pageTitle}</h1>

<div class="grid gap-2 lg:grid-cols-2">
	{#each data.articles as article, i (i)}
		<div class="flex flex-col gap-y-2 border border-[#898989]/20 p-2 md:gap-y-2.5 lg:gap-y-5">
			<h2 class="text-lg font-semibold text-[#C6C6C6] md:text-xl lg:text-2xl">
				<a href={`/articles/${article.slug}`} aria-label={`View details for article: ${article.title}`}>
					{article.title}
				</a>
			</h2>

			<p class="line-clamp-4 flex-1 text-sm">{article.description}</p>
			<div class="flex flex-wrap items-center justify-between gap-2 text-sm">
				<p>Published on {article.publishedDate}</p>
				<a
					href={`/articles/${article.slug}`}
					class="flex items-center justify-center gap-2 gap-x-2 bg-[#898989] px-2.5 py-0.5 text-[#131313] select-none"
					aria-label={`Read more about ${article.title}`}
				>
					Read more &gt;&gt;
				</a>
			</div>
		</div>
	{/each}
</div>
