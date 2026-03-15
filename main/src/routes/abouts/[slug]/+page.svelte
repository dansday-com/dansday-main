<script lang="ts">
	import Metadata from '$lib/components/metadata.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
</script>

<Metadata title={data.meta.title + (data.siteName ? ` | ${data.siteName}` : '')} description={data.meta.description} />

<article class="flex h-full min-h-0 w-full flex-col overflow-hidden rounded-b-lg">
	<div
		class="h-full min-h-0 w-full min-w-0 overflow-auto text-sm [&_pre]:!m-0 [&_pre]:!h-full [&_pre]:!min-h-full [&_pre]:!w-full [&_pre]:![min-width:min-content] [&_pre]:!rounded-none [&_pre]:!p-4 [&_pre]:!text-left"
	>
		{@html data.content.html}
	</div>
</article>
