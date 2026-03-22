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
			totalReviews: number;
			totalIssues: number;
			weekRange: string;
			monthRange: string;
			yearRange: string;
			allTimeRange: string;
		};
		calendar: { date: string; count: number }[];
		createdYear: number;
		currentYear: number;
		activity: { items: ActivityItem[]; hasMore: boolean };
		topRepos: { repo: string; commits: number }[];
		topPRs: { repo: string; prNumber: number; title: string; additions: number; deletions: number; mergedAt: string; private: boolean }[];
	}

	let githubData = $state<GithubData | null>(null);
	let loading = $state(true);
	let error = $state('');
	let visibleCount = $state(0);
	let currentPage = $state(1);
	let calendarDays = $state<{ date: string; count: number }[]>([]);
	let calendarTotal = $state(0);
	let selectedYear = $state(new Date().getFullYear());
	let calendarLoading = $state(false);
	let hoveredDay = $state<{ date: string; count: number } | null>(null);
	let mouseX = $state(0);
	let mouseY = $state(0);
	let mainEl = $state<HTMLElement | null>(null);
	let calendarEl = $state<HTMLElement | null>(null);
	let cellSize = $state(0);
	let cellTop = $state(0);

	$effect(() => {
		if (!calendarEl || !weeks.length) return;
		const first = calendarEl.querySelector('[data-cell]') as HTMLElement | null;
		if (!first) return;
		const rect = first.getBoundingClientRect();
		const parentRect = calendarEl.getBoundingClientRect();
		cellSize = rect.width;
		cellTop = rect.top - parentRect.top;
	});
	let tooltipEl = $state<HTMLElement | null>(null);
	const tooltipStyle = $derived.by(() => {
		if (!hoveredDay || !mainEl) return 'display:none';
		const rect = mainEl.getBoundingClientRect();
		const tw = tooltipEl?.offsetWidth ?? 220;
		const th = tooltipEl?.offsetHeight ?? 32;
		const gap = 10;
		const wouldClipRight = mouseX + gap + tw > rect.right;
		const wouldClipBottom = mouseY - gap - th < rect.top;
		const xStyle = wouldClipRight ? `left:${mouseX - rect.left - tw - gap}px` : `left:${mouseX - rect.left + gap}px`;
		const yStyle = wouldClipBottom ? `top:${mouseY - rect.top + gap}px` : `top:${mouseY - rect.top - th - gap}px`;
		return `${xStyle};${yStyle}`;
	});

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

	function mask(text: string): string {
		return text.replace(/\S/g, '*');
	}

	function cellColor(count: number): string {
		if (count === 0) return 'bg-white/15';
		if (count <= 2) return 'bg-[#0e4429]';
		if (count <= 5) return 'bg-[#006d32]';
		if (count <= 9) return 'bg-[#26a641]';
		return 'bg-[#39d353]';
	}

	function pad2(n: number) {
		return String(n).padStart(2, '0');
	}
	function localDateStr(d: Date) {
		return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
	}

	function buildWeeks(days: { date: string; count: number }[], year: number) {
		const dayMap = new Map(days.map((d) => [d.date, d.count]));
		const today = localDateStr(new Date());
		const isCurrentYear = year === new Date().getFullYear();
		const allDays: { date: string; count: number; future: boolean }[] = [];
		const cursor = new Date(year, 0, 1);
		const endDate = new Date(year, 11, 31);
		while (cursor <= endDate) {
			const ds = localDateStr(cursor);
			const isFuture = isCurrentYear && ds > today;
			allDays.push({ date: ds, count: dayMap.get(ds) ?? 0, future: isFuture });
			cursor.setDate(cursor.getDate() + 1);
		}
		const weeks: { date: string; count: number; future: boolean }[][] = [];
		let week: { date: string; count: number; future: boolean }[] = [];
		if (allDays.length > 0) {
			const firstDay = cursor.getDay !== undefined ? new Date(year, 0, 1).getDay() : 0;
			const mondayIdx = firstDay === 0 ? 6 : firstDay - 1;
			for (let i = 0; i < mondayIdx; i++) week.push({ date: '', count: -1, future: false });
		}
		for (const day of allDays) {
			week.push(day);
			if (week.length === 7) {
				weeks.push(week);
				week = [];
			}
		}
		if (week.length > 0) {
			weeks.push(week);
		}
		return weeks;
	}

	function getMonthLabels(weeks: { date: string; count: number }[][]) {
		const labels: { label: string; col: number }[] = [];
		const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		let lastMonth = -1;
		for (let i = 0; i < weeks.length; i++) {
			const firstReal = weeks[i].find((d) => d.date !== '');
			if (!firstReal) continue;
			const parts = firstReal.date.split('-');
			const m = parseInt(parts[1], 10) - 1;
			if (m !== lastMonth) {
				labels.push({ label: months[m], col: i });
				lastMonth = m;
			}
		}
		return labels;
	}

	function formatTooltipDate(dateStr: string) {
		const [y, m, d] = dateStr.split('-').map(Number);
		const date = new Date(y, m - 1, d);
		const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
		const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		return `${days[date.getDay()]}, ${months[m - 1]} ${d}, ${y}`;
	}

	async function selectYear(year: number) {
		if (year === selectedYear && calendarDays.length > 0) return;
		selectedYear = year;
		if (githubData && year === githubData.currentYear) {
			calendarDays = githubData.calendar;
			calendarTotal = githubData.stats.totalCommits + githubData.stats.totalPRs + githubData.stats.totalReviews + githubData.stats.totalIssues;
			return;
		}
		calendarLoading = true;
		try {
			const res = await fetch(`/api/github?calendarYear=${year}`);
			if (!res.ok) throw new Error('API error');
			const data = await res.json();
			calendarDays = data.calendar ?? [];
			calendarTotal = data.total ?? 0;
		} catch {
			calendarDays = [];
			calendarTotal = 0;
		}
		calendarLoading = false;
	}

	const weeks = $derived(calendarDays.length ? buildWeeks(calendarDays, selectedYear) : []);
	const monthLabels = $derived(getMonthLabels(weeks));
	const yearOptions = $derived(
		githubData ? Array.from({ length: githubData.currentYear - githubData.createdYear + 1 }, (_, i) => githubData!.currentYear - i) : []
	);

	onMount(async () => {
		try {
			const res = await fetch('/api/github');
			if (!res.ok) {
				const err = await res.json();
				error = err.error ?? 'Failed to load GitHub data.';
				return;
			}
			githubData = await res.json();
			calendarDays = githubData!.calendar;
			calendarTotal = githubData!.stats.totalCommits + githubData!.stats.totalPRs + githubData!.stats.totalReviews + githubData!.stats.totalIssues;
			selectedYear = githubData!.currentYear;

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

<main bind:this={mainEl} class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-[#080808]/80 font-mono text-sm backdrop-blur-sm md:text-base">
	<div class="text-ash-100 flex-1 p-4 pb-12 sm:p-6">
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
			<div class="mb-5 flex items-start gap-3">
				<img src={githubData.user.avatarUrl} alt={githubData.user.name} class="h-10 w-10 shrink-0 rounded-full border border-[#30363d]" />
				<div class="min-w-0 flex-1">
					<div class="text-sm leading-tight font-bold text-white">{githubData.user.name}</div>
					<div class="text-xs text-[#8b949e]">@{githubData.username}</div>
					{#if githubData.user.bio}
						<div class="mt-0.5 truncate text-xs text-[#8b949e]">{githubData.user.bio}</div>
					{/if}
					{#if githubData.user.organizations?.length > 0}
						<div class="mt-1.5 flex flex-wrap items-center gap-1.5">
							<i class="fas fa-building text-xs text-[#8b949e]"></i>
							{#each githubData.user.organizations as org}
								<a
									href={org.url}
									target="_blank"
									rel="noopener noreferrer"
									class="flex items-center gap-1 transition-opacity hover:opacity-80"
									title={org.name}
								>
									<img src={org.avatarUrl} alt={org.name} class="h-4 w-4 rounded-sm border border-[#30363d]" />
									<span class="text-xs text-[#8b949e] transition-colors hover:text-white">{org.login}</span>
								</a>
							{/each}
						</div>
					{/if}
				</div>
			</div>

			<div class="mb-5 flex gap-2 overflow-x-auto pb-1 lg:grid lg:grid-cols-7">
				{#each [{ label: 'this week', value: githubData.stats.week, color: 'text-[#39d353]', sub: githubData.stats.weekRange }, { label: 'this month', value: githubData.stats.month, color: 'text-[#26a641]', sub: githubData.stats.monthRange }, { label: 'all time', value: githubData.stats.allTime, color: 'text-[#3fb950]', sub: githubData.stats.allTimeRange }, { label: 'commits', value: githubData.stats.totalCommits, color: 'text-[#58a6ff]', sub: githubData.stats.yearRange }, { label: 'PRs', value: githubData.stats.totalPRs, color: 'text-[#bc8cff]', sub: githubData.stats.yearRange }, { label: 'reviews', value: githubData.stats.totalReviews, color: 'text-[#d2a8ff]', sub: githubData.stats.yearRange }, { label: 'issues', value: githubData.stats.totalIssues, color: 'text-[#f78166]', sub: githubData.stats.yearRange }] as card}
					<div class="w-32 shrink-0 rounded border border-[#30363d] bg-[#161b22]/60 p-2 text-center lg:w-auto lg:shrink">
						<div class="text-lg font-bold {card.color}">{(card.value ?? 0).toLocaleString()}</div>
						<div class="text-xs text-[#8b949e]">{card.label}</div>
						{#if card.sub}<div class="text-xs text-[#6e7681]">{card.sub}</div>{/if}
					</div>
				{/each}
			</div>

			<div class="mb-5 rounded border border-[#30363d] bg-[#161b22]/60 p-3">
				<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
					<div class="shrink-0 text-xs text-[#8b949e]">
						{#if calendarLoading}
							<span class="text-[#8b949e]">Loading...</span>
						{:else}
							<span class="font-semibold text-white">{calendarTotal.toLocaleString()}</span> contributions in {selectedYear}
						{/if}
					</div>
					<div class="flex items-center gap-1 overflow-x-auto lg:hidden">
						{#each yearOptions as year}
							<button
								class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium transition-colors {year === selectedYear
									? 'bg-[#238636] text-white'
									: 'text-[#8b949e] hover:bg-[#21262d] hover:text-white'}"
								onclick={() => selectYear(year)}>{year}</button
							>
						{/each}
					</div>
				</div>
				<div class="flex gap-4">
					<div class="min-w-0 flex-1 overflow-x-auto">
						<div class="relative min-w-[500px]" bind:this={calendarEl}>
							<div class="mb-0.5 grid gap-0.5" style="padding-left:calc(2rem + 2px); grid-template-columns: repeat({weeks.length}, 1fr)">
								{#each weeks as _, wi}
									<div class="text-center text-xs leading-none text-[#8b949e]">
										{#each monthLabels as ml}{#if ml.col === wi}{ml.label}{/if}{/each}
									</div>
								{/each}
							</div>
							{#if cellSize > 0}
								{#each ['Mon', '', 'Wed', '', 'Fri', '', 'Sun'] as label, i}
									{#if label}
										<span
											class="absolute flex items-center text-xs leading-none text-[#8b949e]"
											style="top:{cellTop + i * (cellSize + 2)}px; left:0; width:2rem; height:{cellSize}px">{label}</span
										>
									{/if}
								{/each}
							{/if}
							<div class="grid gap-0.5" style="padding-left:calc(2rem + 2px); grid-template-columns: repeat({weeks.length}, 1fr)">
								{#each weeks as week}
									<div class="grid grid-rows-7 gap-0.5">
										{#each week as day, di}
											{#if day.date === ''}
												<div data-cell class="aspect-square w-full"></div>
											{:else if day.future}
												<div
													data-cell
													role="gridcell"
													tabindex="0"
													aria-label="No contributions on {day.date}"
													class="aspect-square w-full cursor-pointer rounded-sm bg-white/15 opacity-40"
													onmouseenter={(e) => {
														hoveredDay = { date: day.date, count: 0 };
														mouseX = e.clientX;
														mouseY = e.clientY;
													}}
													onmousemove={(e) => {
														mouseX = e.clientX;
														mouseY = e.clientY;
													}}
													onmouseleave={() => {
														hoveredDay = null;
													}}
												></div>
											{:else}
												<div
													data-cell
													role="gridcell"
													tabindex="0"
													aria-label="{day.count} contribution{day.count !== 1 ? 's' : ''} on {day.date}"
													class="aspect-square w-full cursor-pointer rounded-sm {cellColor(day.count)} hover:brightness-125"
													onmouseenter={(e) => {
														hoveredDay = { date: day.date, count: day.count };
														mouseX = e.clientX;
														mouseY = e.clientY;
													}}
													onmousemove={(e) => {
														mouseX = e.clientX;
														mouseY = e.clientY;
													}}
													onmouseleave={() => {
														hoveredDay = null;
													}}
												></div>
											{/if}
										{/each}
									</div>
								{/each}
							</div>
						</div>
						<div class="mt-4 flex items-center justify-end">
							<div class="absolute flex items-center gap-1.5 text-xs text-[#8b949e] lg:relative">
								<span>Less</span>
								<div class="h-2.25 w-2.25 rounded-sm bg-white/15"></div>
								<div class="h-2.25 w-2.25 rounded-sm bg-[#0e4429]"></div>
								<div class="h-2.25 w-2.25 rounded-sm bg-[#006d32]"></div>
								<div class="h-2.25 w-2.25 rounded-sm bg-[#26a641]"></div>
								<div class="h-2.25 w-2.25 rounded-sm bg-[#39d353]"></div>
								<span>More</span>
							</div>
						</div>
					</div>
					<div class="hidden flex-col items-center gap-1 lg:flex">
						{#each yearOptions as year}
							<button
								class="rounded px-1.5 py-0.5 text-lg font-medium transition-colors {year === selectedYear
									? 'bg-[#238636] text-white'
									: 'text-[#8b949e] hover:bg-[#21262d] hover:text-white'}"
								onclick={() => selectYear(year)}>{year}</button
							>
						{/each}
					</div>
				</div>
			</div>

			<div class="mb-5">
				<div class="mb-2 flex items-center gap-2 text-xs tracking-wider text-[#8b949e] uppercase">
					Live activity
					<span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-[#39d353]"></span>
				</div>
				<div class="flex flex-col gap-1">
					{#each githubData.activity.items.slice(0, visibleCount) as item}
						<div class="activity-item flex items-start gap-2 rounded border border-[#30363d] bg-[#161b22]/60 px-3 py-2">
							<i
								class="fas {item.type === 'pr'
									? 'fa-code-pull-request text-[#bc8cff]'
									: item.type === 'issue'
										? 'fa-circle-dot text-[#f78166]'
										: item.type === 'review'
											? 'fa-eye text-[#d2a8ff]'
											: 'fa-code-commit text-[#8b949e]'} mt-0.5 shrink-0 text-xs"
							></i>
							<div class="min-w-0 flex-1">
								<div class="flex flex-wrap items-center gap-1.5">
									<span class="shrink-0 text-xs font-medium text-[#58a6ff]">{item.repo}</span>
									{#if item.private}
										<span class="rounded border border-[#30363d] px-1 text-xs text-[#8b949e]">private</span>
									{/if}
									{#if item.type === 'pr' && item.additions != null}
										<span class="text-xs text-[#3fb950]">+{item.additions}</span>
										<span class="text-xs text-[#f85149]">-{item.deletions}</span>
									{/if}
								</div>
								<span class="mt-0.5 line-clamp-1 block text-xs {item.private ? 'text-[#8b949e]' : 'text-[#c9d1d9]'}"
									>{item.private ? mask(item.title) : item.title}</span
								>
							</div>
							<div class="mt-0.5 shrink-0 text-xs text-[#8b949e]">{timeAgo(item.date)}</div>
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

			<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
				{#if githubData.topPRs?.length > 0}
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
												<span class="rounded border border-[#30363d] px-1 text-xs text-[#8b949e]">private</span>
											{/if}
										</div>
										<span class="mt-0.5 line-clamp-1 block text-xs {pr.private ? 'text-[#8b949e]' : 'text-[#c9d1d9]'}"
											>{pr.private ? mask(pr.title) : pr.title}</span
										>
									</div>
									<div class="flex shrink-0 items-center gap-2 text-xs">
										<span class="text-[#3fb950]">+{(pr.additions ?? 0).toLocaleString()}</span>
										<span class="text-[#f85149]">-{(pr.deletions ?? 0).toLocaleString()}</span>
									</div>
								</div>
							{/each}
						</div>
					</div>
				{/if}

				{#if githubData.topRepos?.length > 0}
					<div>
						<div class="mb-2 text-xs tracking-wider text-[#8b949e] uppercase">Top repositories</div>
						<div class="flex flex-col gap-1">
							{#each githubData.topRepos as repo}
								<div class="flex items-center gap-3 rounded border border-[#30363d] bg-[#161b22]/60 px-3 py-2">
									<i class="fas fa-code-branch shrink-0 text-xs text-[#8b949e]"></i>
									<span class="min-w-0 flex-1 truncate text-xs font-medium text-[#58a6ff]">{repo.repo}</span>
									<span class="shrink-0 text-xs text-[#8b949e]">{repo.commits} commits</span>
								</div>
							{/each}
						</div>
					</div>
				{/if}
			</div>
		{/if}
	</div>
	{#if hoveredDay}
		<div
			bind:this={tooltipEl}
			class="pointer-events-none absolute z-[9999] hidden rounded border border-[#30363d] bg-[#1b1f23] px-2 py-1.5 text-xs whitespace-nowrap text-white shadow-lg md:block"
			style={tooltipStyle}
		>
			<strong>{hoveredDay.count === 0 ? 'No' : hoveredDay.count} contribution{hoveredDay.count !== 1 ? 's' : ''}</strong> on {formatTooltipDate(
				hoveredDay.date
			)}
		</div>
	{/if}
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
