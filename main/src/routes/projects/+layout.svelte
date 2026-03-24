<script lang="ts">
	import { page } from '$app/state';
	import type { LayoutProps } from './$types';

	let { children, data }: LayoutProps = $props();

	const categoryFilterList = data.categoryFilterList ?? [];
	let activeCategory = $derived(page.params.category ?? '');
</script>

<main class="flex-1 flex-grow overflow-y-auto px-3 lg:px-4">
	<nav class="bg-ash-700 sticky top-0 z-50 mb-2 flex items-center overflow-x-auto select-none" aria-label="Project categories">
		<a
			href="/projects"
			data-active={activeCategory === ''}
			class="text-ash-300 data-[active=true]:bg-ash-300 data-[active=true]:text-ash-800 flex shrink-0 items-center gap-1.5 px-3 py-0.5 leading-none"
			aria-label="All projects"
		>
			All
		</a>
		{#each categoryFilterList as { id, name, slug } (id)}
			<a
				href="/projects/category/{encodeURIComponent(slug)}"
				data-active={activeCategory === slug}
				class="text-ash-300 data-[active=true]:bg-ash-300 data-[active=true]:text-ash-800 flex shrink-0 items-center gap-1.5 px-3 py-0.5 leading-none"
				aria-label="Filter by {name}"
			>
				{name}
			</a>
		{/each}
	</nav>
	{@render children()}
</main>
