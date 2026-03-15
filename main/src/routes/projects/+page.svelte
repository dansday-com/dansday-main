<script lang="ts">
	import { page } from '$app/state';
	import { browser } from '$app/environment';
	import Metadata from '$lib/components/metadata.svelte';
	import { resolveImageUrl } from '$lib/image';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
	const baseUrl = data.adminBaseUrl ?? '';
	let activeCategory = $derived(browser ? (page.url.searchParams.get('category') ?? '') : '');
	let projects = $derived.by(() => {
		if (activeCategory === '') return data.items;
		return data.items.filter((item) => item.category_slug === activeCategory);
	});
	const pageTitle = (data.projectsListMeta?.title as string) ?? (data.siteName ? `Projects | ${data.siteName}` : 'Projects');
	const description = (data.projectsListMeta?.description as string) ?? '';
	let canonicalUrl = $derived(page.url.origin + page.url.pathname);
</script>

<Metadata title={pageTitle} {description} canonical={canonicalUrl} />

<h1 class="sr-only">{pageTitle}</h1>

<main class="grid flex-1 grow gap-2 overflow-y-auto md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
	{#each projects as project, i (project.id)}
		<a
			href={`/projects/${project.id}` + (activeCategory ? `?category=${encodeURIComponent(activeCategory)}` : '')}
			class="divide-ash-700 border-ash-700 divide-y overflow-hidden border select-none"
			aria-label={`View details for project: ${project.title}`}
			data-sveltekit-preload-code="eager"
		>
			<figure class="group relative aspect-video overflow-hidden">
				<img
					src={resolveImageUrl(project.poster, baseUrl)}
					alt={project.title}
					class="size-full object-cover object-center grayscale-50 transition-all duration-500 group-hover:grayscale-0"
					loading={i < 4 ? 'eager' : 'lazy'}
					fetchpriority={i < 4 ? 'high' : 'auto'}
				/>
				<div class="absolute top-0 left-0 grid h-full w-full place-items-center bg-[#080808]/90 transition-opacity duration-500 group-hover:opacity-0">
					<p class="px-4 text-center text-3xl font-semibold uppercase">{project.title}</p>
				</div>
				<div aria-hidden="true" class="absolute top-0 left-0 h-full w-full bg-repeat opacity-2 group-hover:opacity-0"></div>
			</figure>
			<div class="p-2">
				<p class="line-clamp-4 text-sm">{project.description}</p>
			</div>
		</a>
	{/each}
</main>
