<script lang="ts">
	import { onMount } from 'svelte';
	import Metadata from '$lib/components/metadata.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const general = (data.general ?? {}) as Record<string, unknown>;
	const metaTitle = ((general.title as string) ?? data.siteName ?? '') + ' | Contribute';
	const metaDescription = 'GitHub contribution stats and activity.';

	interface ActivityItem {
		type: 'commit' | 'pr';
		repo: string;
		repoUrl: string;
		title: string;
		url: string;
		date: string;
		private: boolean;
	}

	interface GithubData {
		username: string;
		user: {
			name: string;
			avatarUrl: string;
			bio: string;
			totalRepos: number;
			organizations: { login: string; name: string; avatarUrl: string; url: string }[];
		};
		stats: {
			week: number;
			month: number;
			year: number;
			totalCommits: number;
			totalPRs: number;
			totalIssues: number;
		};
		calendar: { date: string; count: number }[];
		activity: ActivityItem[];
		languages: { name: string; count: number }[];
	}

	let githubData = $state<GithubData | null>(null);
	let loading = $state(true);
	let error = $state('');

	const LANG_COLORS: Record<string, string> = {
		TypeScript: '#3178c6',
		JavaScript: '#f1e05a',
		PHP: '#777bb4',
		Python: '#3572A5',
		Vue: '#41b883',
		Svelte: '#ff3e00',
		CSS: '#563d7c',
		HTML: '#e34c26',
		Go: '#00ADD8',
		Rust: '#dea584',
		Java: '#b07219',
		'C#': '#178600',
		'C++': '#f34b7d',
		Shell: '#89e051',
		Ruby: '#701516',
		Dart: '#00B4AB'
	};

	function langColor(lang: string): string {
		return LANG_COLORS[lang] ?? '#8b949e';
	}

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
		if (count === 0) return 'bg-[#161b22]';
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
			if (week.length === 7) { weeks.push(week); week = []; }
		}
		// pad end of last week to Saturday
		if (week.length > 0) {
			while (week.length < 7) week.push({ date: '', count: -1 });
			weeks.push(week);
		}
		return weeks;
	}

	const weeks = $derived(githubData ? buildWeeks(githubData.calendar) : []);
	const maxLangCount = $derived(
		githubData?.languages.length ? Math.max(...githubData.languages.map((l) => l.count)) : 1
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
		} catch (e: any) {
			error = e.message ?? 'Network error.';
		} finally {
			loading = false;
		}
	});
</script>

<Metadata title={metaTitle} description={metaDescription} />

<main class="relative flex min-h-0 flex-1 flex-col font-mono text-sm md:text-base">
	<div class="absolute inset-0 -z-10 bg-[#080808]/80 backdrop-blur-sm"></div>
	<div class="text-ash-100 z-10 flex-1 overflow-y-auto p-4 pb-12 sm:p-6">

		<!-- Header -->
		<div class="mb-6">
			<div class="text-white font-bold text-base">~/contribute</div>
			<div class="text-[#8b949e] text-xs mt-0.5">Live GitHub contribution stats</div>
		</div>

		{#if loading}
			<div class="flex items-center gap-2 text-[#8b949e] py-10 justify-center">
				<span class="inline-block w-3 h-3 border-2 border-[#238636] border-t-transparent rounded-full animate-spin"></span>
				<span class="text-xs">Fetching GitHub data...</span>
			</div>

		{:else if error}
			<div class="text-red-400 text-xs">{error}</div>

		{:else if githubData}
			<!-- Profile row -->
			<div class="flex items-start gap-3 mb-5">
				<img src={githubData.user.avatarUrl} alt={githubData.user.name} class="w-10 h-10 rounded-full border border-[#30363d] shrink-0" />
				<div class="min-w-0 flex-1">
					<div class="text-white font-bold text-sm leading-tight">{githubData.user.name}</div>
					<div class="text-[#8b949e] text-xs">@{githubData.username}</div>
					{#if githubData.user.bio}
						<div class="text-[#8b949e] text-xs mt-0.5 truncate">{githubData.user.bio}</div>
					{/if}
					{#if githubData.user.organizations.length > 0}
						<div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
							<i class="fas fa-building text-[#8b949e] text-[10px]"></i>
							{#each githubData.user.organizations as org}
								<a href={org.url} target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 hover:opacity-80 transition-opacity" title={org.name}>
									<img src={org.avatarUrl} alt={org.name} class="w-4 h-4 rounded-sm border border-[#30363d]" />
									<span class="text-[#8b949e] text-[10px] hover:text-white transition-colors">{org.login}</span>
								</a>
							{/each}
						</div>
					{/if}
				</div>
				<div class="text-center shrink-0">
					<div class="text-white font-bold text-sm">{githubData.user.totalRepos}</div>
					<div class="text-[#8b949e] text-xs">repos</div>
				</div>
			</div>

			<!-- Stat cards -->
			<div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-5">
				{#each [
					{ label: 'week', value: githubData.stats.week, color: 'text-[#39d353]' },
					{ label: 'month', value: githubData.stats.month, color: 'text-[#26a641]' },
					{ label: 'year', value: githubData.stats.year, color: 'text-[#238636]' },
					{ label: 'commits', value: githubData.stats.totalCommits, color: 'text-[#58a6ff]' },
					{ label: 'PRs', value: githubData.stats.totalPRs, color: 'text-[#bc8cff]' },
					{ label: 'issues', value: githubData.stats.totalIssues, color: 'text-[#f78166]' }
				] as card}
					<div class="border border-[#30363d] bg-[#161b22]/60 rounded p-2 text-center">
						<div class="text-lg font-bold {card.color}">{card.value.toLocaleString()}</div>
						<div class="text-[#8b949e] text-xs">{card.label}</div>
					</div>
				{/each}
			</div>

			<!-- Contribution calendar -->
			<div class="border border-[#30363d] bg-[#161b22]/60 rounded p-3 mb-5 overflow-x-auto">
				<div class="text-xs text-[#8b949e] mb-2">
					<span class="text-white font-semibold">{githubData.stats.year.toLocaleString()}</span> contributions this year
				</div>
				<div class="flex gap-[3px]">
					{#each weeks as week}
						<div class="flex flex-col gap-[3px]">
							{#each week as day}
								{#if day.count === -1}
									<div class="w-[9px] h-[9px]"></div>
								{:else}
									<div class="w-[9px] h-[9px] rounded-sm {cellColor(day.count)}" title="{day.count} on {day.date}"></div>
								{/if}
							{/each}
						</div>
					{/each}
				</div>
				<div class="flex items-center gap-1.5 mt-2 text-xs text-[#8b949e]">
					<span>Less</span>
					<div class="w-[9px] h-[9px] rounded-sm bg-[#161b22] border border-[#30363d]"></div>
					<div class="w-[9px] h-[9px] rounded-sm bg-[#0e4429]"></div>
					<div class="w-[9px] h-[9px] rounded-sm bg-[#006d32]"></div>
					<div class="w-[9px] h-[9px] rounded-sm bg-[#26a641]"></div>
					<div class="w-[9px] h-[9px] rounded-sm bg-[#39d353]"></div>
					<span>More</span>
				</div>
			</div>

			<!-- Bottom: activity feed + languages -->
			<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

				<!-- Live activity feed -->
				<div class="lg:col-span-2">
					<div class="text-xs text-[#8b949e] uppercase tracking-wider mb-2">Live activity</div>
					<div class="flex flex-col gap-1">
						{#each githubData.activity as item}
							<div class="flex items-start gap-2 border border-[#30363d] bg-[#161b22]/60 rounded px-3 py-2">
								{#if item.type === 'commit'}
									<i class="fas fa-code-commit mt-0.5 shrink-0 text-[#8b949e] text-xs"></i>
								{:else}
									<i class="fas fa-code-pull-request mt-0.5 shrink-0 text-[#bc8cff] text-xs"></i>
								{/if}
								<div class="min-w-0 flex-1">
									<div class="flex items-center gap-1.5 flex-wrap">
										<span class="text-[#58a6ff] text-xs font-medium shrink-0">{item.repo}</span>
										{#if item.private}
											<span class="text-[#8b949e] text-[10px] border border-[#30363d] rounded px-1">private</span>
										{/if}
									</div>
									<a href={item.url} target="_blank" rel="noopener noreferrer" class="text-[#c9d1d9] text-xs hover:text-white hover:underline line-clamp-1 block mt-0.5">
										{item.title}
									</a>
								</div>
								<div class="text-[#8b949e] text-[10px] shrink-0 mt-0.5">{timeAgo(item.date)}</div>
							</div>
						{/each}
					</div>
				</div>

				<!-- Top languages -->
				<div>
					<div class="text-xs text-[#8b949e] uppercase tracking-wider mb-2">Top languages</div>
					<div class="border border-[#30363d] bg-[#161b22]/60 rounded p-3 flex flex-col gap-3">
						{#each githubData.languages as lang}
							<div>
								<div class="flex justify-between text-xs mb-1">
									<div class="flex items-center gap-1.5">
										<span class="inline-block w-2 h-2 rounded-full" style="background:{langColor(lang.name)}"></span>
										<span class="text-[#c9d1d9]">{lang.name}</span>
									</div>
									<span class="text-[#8b949e]">{lang.count}</span>
								</div>
								<div class="h-1 bg-[#21262d] rounded-full overflow-hidden">
									<div class="h-full rounded-full" style="width:{(lang.count / maxLangCount) * 100}%; background:{langColor(lang.name)}"></div>
								</div>
							</div>
						{/each}
					</div>
				</div>
			</div>
		{/if}
	</div>
</main>
