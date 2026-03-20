<script lang="ts">
	import { onMount } from 'svelte';
	import Metadata from '$lib/components/metadata.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const general = (data.general ?? {}) as Record<string, unknown>;
	const metaTitle = ((general.title as string) ?? data.siteName ?? '') + ' | Contribute';
	const metaDescription = 'GitHub contribution stats and activity.';

	interface ActivityItem {
		repo: string;
		title: string;
		type: string;
		date: string;
		private: boolean;
		additions: number | null;
		deletions: number | null;
	}

	interface GithubData {
		username: string;
		user: {
			name: string;
			avatarUrl: string;
			bio: string;
			organizations: { login: string; name: string; avatarUrl: string; url: string }[];
		};
		stats: {
			week: number;
			month: number;
			year: number;
			allTime: number;
			totalCommits: number;
			totalPRs: number;
			totalIssues: number;
		};
		calendar: { date: string; count: number }[];
		activity: { items: ActivityItem[]; hasMore: boolean };
		topRepos: { repo: string; commits: number }[];
		topPRs: { repo: string; prNumber: number; title: string; additions: number; deletions: number; mergedAt: string; private: boolean }[];
	}

	let githubData = $state<GithubData | null>(null);
	let loading = $state(true);
	let error = $state('');
	let visibleCount = $state(0);
	let currentPage = $state(1);

	function timeAgo(iso: string): string {
		const diff = Date.now() - new Date(iso).getTime();
		const s = Math.floor(diff / 1000);
		if (s < 60) return `${s}s ago`;
		const m = Math.floor(s / 60);
		if (m < 60) return `${m}m ago`;
		const h = Math.floor(m / 60);
		if (h < 24) return `${h}h ago`;
		const d = Math.floor(h / 24);
		if (d < 30) return `${d}d ago`;
		const mo = Math.floor(d / 30);
		if (mo < 12) return `${mo}mo ago`;
		return `${Math.floor(mo / 12)}y ago`;
	}

	function cellColor(count: number): string {
		if (count === 0) return 'bg-white/15';
		if (count <= 2) return 'bg-[#0e4429]';
		if (count <= 5) return 'bg-[#006d32]';
		if (count <= 9) return 'bg-[#26a641]';
		return 'bg-[#39d353]';
	}

	function buildWeeks(days: { date: string; count: number }[]) {
		const weeks: { date: string; count: number }[][] = [];
		let week: { date: string; count: number }[] = [];
		if (days.length > 0) {
			const firstDay = new Date(days[0].date).getDay();
			for (let i = 0; i < firstDay; i++) week.push({ date: '', count: -1 });
		}
		for (const day of days) {
			week.push(day);
			if (week.length === 7) {
				weeks.push(week);
				week = [];
			}
		}

		if (week.length > 0) {
			while (week.length < 7) week.push({ date: '', count: -1 });
			weeks.push(week);
		}
		return weeks;
	}

	const weeks = $derived(githubData ? buildWeeks(githubData.calendar) : []);
	const maxRepoCount = $derived(githubData?.topRepos.length ? Math.max(...githubData.topRepos.map((r) => r.commits)) : 1);

	onMount(async () => {
		try {
			const res = await fetch('/api/github');
			if (!res.ok) {
				const err = await res.json();
				error = err.error ?? 'Failed to load GitHub data.';
				return;
			}
			githubData = await res.json();

			const total = githubData?.activity?.items?.length ?? 0;
			let i = 0;
			const tick = () => {
				if (i < total) {
					visibleCount = ++i;
					setTimeout(tick, 80);
				}
			};
			setTimeout(tick, 200);
		} catch (e: any) {
			error = e.message ?? 'Network error.';
		} finally {
			loading = false;
		}
	});

	async function loadMore() {
		if (!githubData || !githubData.activity.hasMore) return;
		currentPage++;
		const res = await fetch(`/api/github?page=${currentPage}`);
		if (!res.ok) return;
		const page: { items: ActivityItem[]; hasMore: boolean } = await res.json();

		const prevLen = githubData.activity.items.length;
		githubData.activity.items = [...githubData.activity.items, ...page.items];
		githubData.activity.hasMore = page.hasMore;

		let i = prevLen;
		const total = githubData.activity.items.length;
		const tick = () => {
			if (i < total) {
				visibleCount = ++i;
				setTimeout(tick, 80);
			}
		};
		tick();
	}
</script>

<Metadata title={metaTitle} description={metaDescription} />

<main class="relative flex min-h-0 flex-1 flex-col font-mono text-sm md:text-base">
	<div class="absolute inset-0 -z-10 bg-[#080808]/80 backdrop-blur-sm"></div>
	<div class="text-ash-100 z-10 flex-1 overflow-y-auto p-4 pb-12 sm:p-6">
		<!-- Header -->
		<div class="mb-6">
			<div class="text-base font-bold text-white">~/contribute</div>
			<div class="mt-0.5 text-xs text-[#8b949e]">Live GitHub contribution stats</div>
		</div>

		{#if loading}
			<div class="flex items-center justify-center gap-2 py-10 text-[#8b949e]">
				<span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-[#238636] border-t-transparent"></span>
				<span class="text-xs">Fetching GitHub data...</span>
			</div>
		{:else if error}
			<div class="text-xs text-red-400">{error}</div>
		{:else if githubData}
			<!-- Profile row -->
			<div class="mb-5 flex items-start gap-3">
				<img src={githubData.user.avatarUrl} alt={githubData.user.name} class="h-10 w-10 shrink-0 rounded-full border border-[#30363d]" />
				<div class="min-w-0 flex-1">
					<div class="text-sm leading-tight font-bold text-white">{githubData.user.name}</div>
					<div class="text-xs text-[#8b949e]">@{githubData.username}</div>
					{#if githubData.user.bio}
						<div class="mt-0.5 truncate text-xs text-[#8b949e]">{githubData.user.bio}</div>
					{/if}
					{#if githubData.user.organizations.length > 0}
						<div class="mt-1.5 flex flex-wrap items-center gap-1.5">
							<i class="fas fa-building text-[10px] text-[#8b949e]"></i>
							{#each githubData.user.organizations as org}
								<a
									href={org.url}
									target="_blank"
									rel="noopener noreferrer"
									class="flex items-center gap-1 transition-opacity hover:opacity-80"
									title={org.name}
								>
									<img src={org.avatarUrl} alt={org.name} class="h-4 w-4 rounded-sm border border-[#30363d]" />
									<span class="text-[10px] text-[#8b949e] transition-colors hover:text-white">{org.login}</span>
								</a>
							{/each}
						</div>
					{/if}
				</div>
			</div>

			<!-- Stat cards -->
			<div class="mb-5 grid grid-cols-4 gap-2 sm:grid-cols-7">
				{#each [{ label: 'week', value: githubData.stats.week, color: 'text-[#39d353]' }, { label: 'month', value: githubData.stats.month, color: 'text-[#26a641]' }, { label: 'this year', value: githubData.stats.year, color: 'text-[#238636]' }, { label: 'all time', value: githubData.stats.allTime, color: 'text-[#3fb950]' }, { label: 'commits (yr)', value: githubData.stats.totalCommits, color: 'text-[#58a6ff]' }, { label: 'PRs (yr)', value: githubData.stats.totalPRs, color: 'text-[#bc8cff]' }, { label: 'issues (yr)', value: githubData.stats.totalIssues, color: 'text-[#f78166]' }] as card}
					<div class="rounded border border-[#30363d] bg-[#161b22]/60 p-2 text-center">
						<div class="text-lg font-bold {card.color}">{card.value.toLocaleString()}</div>
						<div class="text-xs text-[#8b949e]">{card.label}</div>
					</div>
				{/each}
			</div>

			<!-- Contribution calendar -->
			<div class="mb-5 overflow-x-auto rounded border border-[#30363d] bg-[#161b22]/60 p-3">
				<div class="mb-2 text-xs text-[#8b949e]">
					<span class="font-semibold text-white">{githubData.stats.year.toLocaleString()}</span> contributions this year
				</div>
				<div class="flex gap-[3px]">
					{#each weeks as week}
						<div class="flex flex-col gap-[3px]">
							{#each week as day}
								{#if day.count === -1}
									<div class="h-[9px] w-[9px]"></div>
								{:else}
									<div class="h-[9px] w-[9px] rounded-sm {cellColor(day.count)}" title="{day.count} on {day.date}"></div>
								{/if}
							{/each}
						</div>
					{/each}
				</div>
				<div class="mt-2 flex items-center gap-1.5 text-xs text-[#8b949e]">
					<span>Less</span>
					<div class="h-[9px] w-[9px] rounded-sm bg-white/15"></div>
					<div class="h-[9px] w-[9px] rounded-sm bg-[#0e4429]"></div>
					<div class="h-[9px] w-[9px] rounded-sm bg-[#006d32]"></div>
					<div class="h-[9px] w-[9px] rounded-sm bg-[#26a641]"></div>
					<div class="h-[9px] w-[9px] rounded-sm bg-[#39d353]"></div>
					<span>More</span>
				</div>
			</div>

			<!-- Live activity feed -->
			<div class="mb-5">
				<div class="mb-2 flex items-center gap-2 text-xs tracking-wider text-[#8b949e] uppercase">
					Live activity
					<span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-[#39d353]"></span>
				</div>
				<div class="flex flex-col gap-1">
					{#each githubData.activity.items.slice(0, visibleCount) as item}
						<div class="activity-item flex items-start gap-2 rounded border border-[#30363d] bg-[#161b22]/60 px-3 py-2">
							<i class="fas {item.type === 'pr' ? 'fa-code-pull-request text-[#bc8cff]' : 'fa-code-commit text-[#8b949e]'} mt-0.5 shrink-0 text-xs"></i>
							<div class="min-w-0 flex-1">
								<div class="flex flex-wrap items-center gap-1.5">
									<span class="shrink-0 text-xs font-medium text-[#58a6ff]">{item.repo}</span>
									{#if item.private}
										<span class="rounded border border-[#30363d] px-1 text-[10px] text-[#8b949e]">private</span>
									{/if}
									{#if item.type === 'pr' && item.additions != null}
										<span class="text-[10px] text-[#3fb950]">+{item.additions}</span>
										<span class="text-[10px] text-[#f85149]">-{item.deletions}</span>
									{/if}
								</div>
								<span class="mt-0.5 line-clamp-1 block text-xs text-[#c9d1d9]">{item.title}</span>
							</div>
							<div class="mt-0.5 shrink-0 text-[10px] text-[#8b949e]">{timeAgo(item.date)}</div>
						</div>
					{/each}
					{#if githubData.activity.hasMore}
						<button
							onclick={loadMore}
							class="mt-1 cursor-pointer rounded border border-[#30363d] bg-[#161b22]/60 px-3 py-2 text-xs text-[#58a6ff] transition-colors hover:text-white"
						>
							Load more
						</button>
					{/if}
				</div>
			</div>

			<!-- Top PRs + Top repos -->
			<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
				<!-- Top PRs -->
				{#if githubData.topPRs.length > 0}
					<div>
						<div class="mb-2 text-xs tracking-wider text-[#8b949e] uppercase">Top pull requests</div>
						<div class="flex flex-col gap-1">
							{#each githubData.topPRs as pr}
								<div class="flex items-center gap-3 rounded border border-[#30363d] bg-[#161b22]/60 px-3 py-2">
									<i class="fas fa-code-pull-request shrink-0 text-xs text-[#bc8cff]"></i>
									<div class="min-w-0 flex-1">
										<div class="flex flex-wrap items-center gap-1.5">
											<span class="shrink-0 text-xs font-medium text-[#58a6ff]">{pr.repo}</span>
											{#if pr.private}
												<span class="rounded border border-[#30363d] px-1 text-[10px] text-[#8b949e]">private</span>
											{/if}
										</div>
										<span class="mt-0.5 line-clamp-1 block text-xs text-[#c9d1d9]">{pr.title}</span>
									</div>
									<div class="flex shrink-0 items-center gap-2 text-xs">
										<span class="text-[#3fb950]">+{pr.additions.toLocaleString()}</span>
										<span class="text-[#f85149]">-{pr.deletions.toLocaleString()}</span>
									</div>
								</div>
							{/each}
						</div>
					</div>
				{/if}

				<!-- Top repos -->
				{#if githubData.topRepos.length > 0}
					<div>
						<div class="mb-2 text-xs tracking-wider text-[#8b949e] uppercase">Top repositories</div>
						<div class="flex flex-col gap-1">
							{#each githubData.topRepos as repo}
								<div class="flex items-center gap-3 rounded border border-[#30363d] bg-[#161b22]/60 px-3 py-2">
									<i class="fas fa-code-branch shrink-0 text-xs text-[#8b949e]"></i>
									<div class="min-w-0 flex-1">
										<span class="text-xs font-medium text-[#58a6ff]">{repo.repo}</span>
										<div class="mt-1 h-1 overflow-hidden rounded-full bg-[#21262d]">
											<div class="h-full rounded-full bg-[#238636]" style="width:{(repo.commits / maxRepoCount) * 100}%"></div>
										</div>
									</div>
									<span class="shrink-0 text-xs text-[#8b949e]">{repo.commits}</span>
								</div>
							{/each}
						</div>
					</div>
				{/if}
			</div>
		{/if}
	</div>
</main>

<style>
	.activity-item {
		animation: slide-in 0.2s ease-out both;
	}

	@keyframes slide-in {
		from {
			opacity: 0;
			transform: translateX(-8px);
		}
		to {
			opacity: 1;
			transform: translateX(0);
		}
	}
</style>
