<script lang="ts">
	import type { LayoutProps } from './$types';

	let { children }: LayoutProps = $props();
</script>

<div class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-[#080808]/80 backdrop-blur-sm">
	<div class="flex-1 px-3 pb-12 lg:px-4">
		{@render children()}
	</div>
</div>
