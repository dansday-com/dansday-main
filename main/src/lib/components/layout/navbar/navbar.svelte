<script lang="ts">
	import { page } from '$app/state';
	import { navbarMenu } from './navbar-menu';
	import NavbarListener from './navbar-listener.svelte';

	interface SocialLink {
		title?: string;
		text?: string;
	}

	interface SectionFlags {
		about_enable?: number | boolean;
		experience_enable?: number | boolean;
		services_enable?: number | boolean;
		skills_enable?: number | boolean;
		testimonial_enable?: number | boolean;
		projects_enable?: number | boolean;
		articles_enable?: number | boolean;
	}

	interface $$Props {
		siteName: string;
		socialLinks: SocialLink[];
		section?: SectionFlags | Record<string, unknown>;
		aiTerminalConfigured?: boolean;
	}

	let { siteName, socialLinks = [], section = {}, aiTerminalConfigured = false }: $$Props = $props();

	const notDisabled = (v: unknown) => v !== 0 && v !== false;

	const hasAboutsChildren = $derived(
		notDisabled((section as SectionFlags).experience_enable) ||
			notDisabled((section as SectionFlags).services_enable) ||
			notDisabled((section as SectionFlags).skills_enable) ||
			notDisabled((section as SectionFlags).testimonial_enable)
	);
	const visibleMenu = $derived(
		navbarMenu.filter((item) => {
			if (item.href === '/') return true;
			if (item.href === '/abouts') return notDisabled((section as SectionFlags).about_enable) && hasAboutsChildren;
			if (item.href === '/projects') return notDisabled((section as SectionFlags).projects_enable);
			if (item.href === '/articles') return notDisabled((section as SectionFlags).articles_enable);
			if (item.href === '/terminal') return aiTerminalConfigured;
			return true;
		})
	);

	let currentPath = $derived(page.url.pathname);

	const isActive = (href: string, currentPath: string) => {
		if (currentPath === href) return true;
		if (href !== '/' && currentPath.startsWith(href + '/')) return true;
		return false;
	};

	const hasSocialLinks = $derived(Array.isArray(socialLinks) && socialLinks.some((link) => link?.text?.trim()));

	const getHighlightedParts = (title: string, key: string) => {
		if (!title || !key || key.length !== 1) return { before: title, highlighted: null, after: null };

		const index = title.toLowerCase().indexOf(key.toLowerCase());

		if (index === -1) return { before: title, highlighted: null, after: null };

		return {
			before: title.substring(0, index),
			highlighted: title.substring(index, index + 1),
			after: title.substring(index + 1)
		};
	};
</script>

<NavbarListener />

<nav class="overflow-x-auto text-sm select-none md:text-base lg:px-4 lg:py-3">
	{#if hasSocialLinks}
		<div class="hidden items-center justify-end gap-2 px-2 lg:flex lg:px-0">
			<p>-- LINK --</p>
		</div>
	{/if}
	<div class="flex items-center justify-between gap-20 overflow-x-auto px-2 py-3 leading-none lg:px-0 lg:py-0">
		<ul class="flex items-center">
			{#each visibleMenu as { title, href, key }, i (i)}
				{@const parts = getHighlightedParts(title, key)}
				{@const isOnCurrentPath = isActive(href, currentPath)}

				<li class="shrink-0">
					<a
						{href}
						data-sveltekit-preload-code="eager"
						data-sveltekit-preload-data
						data-active={isOnCurrentPath}
						class={`text-ash-300 data-[active=true]:bg-ash-300 data-[active=true]:text-ash-800 flex items-center px-2 py-0.5 leading-none transition-all ${isOnCurrentPath ? '' : ''}`}
						aria-label={`${title} (Shortcut: ${key})`}
					>
						{parts.before}{#if parts.highlighted}<span class={`${isOnCurrentPath ? 'text-ash-800' : 'text-ash-100'} transition-all`}>{parts.highlighted}</span>
						{/if}{parts.after}
					</a>
				</li>
			{/each}
		</ul>
		<div class="not-sr-only hidden items-center gap-2 lg:flex">
			{#each socialLinks as link (link.title + (link.text ?? ''))}
				{#if link?.text}
					<a
						class="bg-ash-300 flex h-6 w-6 shrink-0 items-center justify-center text-black"
						href={link.text}
						target="_blank"
						rel="noopener noreferrer"
						aria-label={link.title?.replace(/^fab fa-/, '') ?? 'Social link'}
					>
						<i class={link.title ?? 'fas fa-link'} aria-hidden="true"></i>
					</a>
				{/if}
			{/each}
		</div>
	</div>
</nav>
