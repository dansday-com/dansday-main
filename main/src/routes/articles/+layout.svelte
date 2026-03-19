<script lang="ts">
	import type { LayoutProps } from './$types';

	let { children }: LayoutProps = $props();
</script>

<div class="relative flex min-h-0 flex-1 flex-col overflow-y-auto">
	<div class="absolute inset-0 -z-10 bg-[#080808]/80 backdrop-blur-sm"></div>
	<div class="z-10 flex-1 px-3 pb-12 lg:px-4">
		{@render children()}
	</div>
</div>
