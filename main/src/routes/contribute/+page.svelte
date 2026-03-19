<script lang="ts">
	import { onMount } from 'svelte';
	import Metadata from '$lib/components/metadata.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const general = (data.general ?? {}) as Record<string, unknown>;
	const metaTitle = ((general.title as string) ?? data.siteName ?? '') + ' | Contribute';
	const metaDescription = 'GitHub contribution stats and activity.';

	interface GithubData {
		username: string;
		user: {
			name: string;
			avatarUrl: string;
			bio: string;
			followers: number;
			following: number;
			publicRepos: number;
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
		repos: {
			name: string;
			description: string;
			url: string;
			stars: number;
			forks: number;
			language: string | null;
			updatedAt: string;
		}[];
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
		const d = Math.floor(diff / 86400000);
		if (d === 0) return 'today';
		if (d === 1) return 'yesterday';
		if (d < 30) return `${d}d ago`;
		const m = Math.floor(d / 30);
		if (m < 12) return `${m}mo ago`;
		return `${Math.floor(m / 12)}y ago`;
	}

	function cellColor(count: number): string {
		if (count === 0) return 'bg-[#161b22]';
		if (count <= 2) return 'bg-[#0e4429]';
		if (count <= 5) return 'bg-[#006d32]';
		if (count <= 9) return 'bg-[#26a641]';
		return 'bg-[#39d353]';
	}

	// group calendar days into weeks for display
	function buildWeeks(days: { date: string; count: number }[]) {
		const weeks: { date: string; count: number }[][] = [];
		let week: { date: string; count: number }[] = [];

		// pad start so first day aligns to correct weekday
		if (days.length > 0) {
			const firstDay = new Date(days[0].date).getDay(); // 0=Sun
			for (let i = 0; i < firstDay; i++) week.push({ date: '', count: -1 });
		}

		for (const day of days) {
			week.push(day);
			if (week.length === 7) {
				weeks.push(week);
				week = [];
			}
		}
		if (week.length > 0) weeks.push(week);
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

<main class="min-h-screen bg-[#0d1117] text-[#c9d1d9] font-mono">
	<div class="max-w-5xl mx-auto px-4 py-10 sm:px-6">

		<!-- Header -->
		<div class="mb-8">
			<h1 class="text-2xl font-bold text-white tracking-tight">~/contribute</h1>
			<p class="text-[#8b949e] text-sm mt-1">Live GitHub contribution stats</p>
		</div>

		{#if loading}
			<div class="flex items-center gap-3 text-[#8b949e] py-20 justify-center">
				<span class="inline-block w-4 h-4 border-2 border-[#238636] border-t-transparent rounded-full animate-spin"></span>
				<span>Fetching GitHub data...</span>
			</div>

		{:else if error}
			<div class="border border-[#f85149]/40 bg-[#161b22] rounded-lg p-6 text-[#f85149]">
				<span class="font-bold">Error:</span> {error}
			</div>

		{:else if githubData}
			<!-- Profile row -->
			<div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-8 border border-[#30363d] rounded-lg p-5 bg-[#161b22]">
				<img
					src={githubData.user.avatarUrl}
					alt={githubData.user.name}
					class="w-16 h-16 rounded-full border-2 border-[#30363d]"
				/>
				<div class="flex-1 min-w-0">
					<div class="text-white font-bold text-lg leading-tight">{githubData.user.name}</div>
					<div class="text-[#8b949e] text-sm">@{githubData.username}</div>
					{#if githubData.user.bio}
						<div class="text-[#c9d1d9] text-sm mt-1 truncate">{githubData.user.bio}</div>
					{/if}
				</div>
				<div class="flex gap-5 text-sm text-[#8b949e] shrink-0">
					<div class="text-center">
						<div class="text-white font-bold text-base">{githubData.user.publicRepos}</div>
						<div>repos</div>
					</div>
					<div class="text-center">
						<div class="text-white font-bold text-base">{githubData.user.followers}</div>
						<div>followers</div>
					</div>
					<div class="text-center">
						<div class="text-white font-bold text-base">{githubData.user.following}</div>
						<div>following</div>
					</div>
				</div>
			</div>

			<!-- Commit stats cards -->
			<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
				{#each [
					{ label: 'This week', value: githubData.stats.week, color: 'text-[#39d353]' },
					{ label: 'This month', value: githubData.stats.month, color: 'text-[#26a641]' },
					{ label: 'This year', value: githubData.stats.year, color: 'text-[#238636]' },
					{ label: 'Commits', value: githubData.stats.totalCommits, color: 'text-[#58a6ff]' },
					{ label: 'Pull Reqs', value: githubData.stats.totalPRs, color: 'text-[#bc8cff]' },
					{ label: 'Issues', value: githubData.stats.totalIssues, color: 'text-[#f78166]' }
				] as card}
					<div class="border border-[#30363d] bg-[#161b22] rounded-lg p-4 text-center">
						<div class="text-2xl font-bold {card.color}">{card.value.toLocaleString()}</div>
						<div class="text-[#8b949e] text-xs mt-1">{card.label}</div>
					</div>
				{/each}
			</div>

			<!-- Contribution calendar -->
			<div class="border border-[#30363d] bg-[#161b22] rounded-lg p-5 mb-8 overflow-x-auto">
				<div class="text-sm text-[#8b949e] mb-3">
					<span class="text-white font-semibold">{githubData.stats.year.toLocaleString()}</span> contributions this year
				</div>
				<div class="flex gap-[3px]">
					{#each weeks as week}
						<div class="flex flex-col gap-[3px]">
							{#each week as day}
								{#if day.count === -1}
									<div class="w-[10px] h-[10px]"></div>
								{:else}
									<div
										class="w-[10px] h-[10px] rounded-sm {cellColor(day.count)}"
										title="{day.count} contributions on {day.date}"
									></div>
								{/if}
							{/each}
						</div>
					{/each}
				</div>
				<div class="flex items-center gap-2 mt-3 text-xs text-[#8b949e]">
					<span>Less</span>
					<div class="w-[10px] h-[10px] rounded-sm bg-[#161b22] border border-[#30363d]"></div>
					<div class="w-[10px] h-[10px] rounded-sm bg-[#0e4429]"></div>
					<div class="w-[10px] h-[10px] rounded-sm bg-[#006d32]"></div>
					<div class="w-[10px] h-[10px] rounded-sm bg-[#26a641]"></div>
					<div class="w-[10px] h-[10px] rounded-sm bg-[#39d353]"></div>
					<span>More</span>
				</div>
			</div>

			<!-- Bottom two columns: repos + languages -->
			<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

				<!-- Recent repos -->
				<div class="lg:col-span-2">
					<h2 class="text-sm font-semibold text-[#8b949e] uppercase tracking-wider mb-3">Recent repos</h2>
					<div class="flex flex-col gap-3">
						{#each githubData.repos as repo}
							<a
								href={repo.url}
								target="_blank"
								rel="noopener noreferrer"
								class="border border-[#30363d] bg-[#161b22] rounded-lg p-4 hover:border-[#58a6ff]/50 transition-colors group block"
							>
								<div class="flex items-start justify-between gap-2">
									<div class="min-w-0">
										<div class="text-[#58a6ff] font-semibold text-sm group-hover:underline truncate">{repo.name}</div>
										{#if repo.description}
											<div class="text-[#8b949e] text-xs mt-1 line-clamp-2">{repo.description}</div>
										{/if}
									</div>
									<div class="text-[#8b949e] text-xs shrink-0">{timeAgo(repo.updatedAt)}</div>
								</div>
								<div class="flex items-center gap-4 mt-3 text-xs text-[#8b949e]">
									{#if repo.language}
										<div class="flex items-center gap-1">
											<span
												class="inline-block w-3 h-3 rounded-full"
												style="background:{langColor(repo.language)}"
											></span>
											{repo.language}
										</div>
									{/if}
									{#if repo.stars > 0}
										<div class="flex items-center gap-1">
											<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 16 16"><path d="M8 .25a.75.75 0 0 1 .673.418l1.882 3.815 4.21.612a.75.75 0 0 1 .416 1.279l-3.046 2.97.719 4.192a.751.751 0 0 1-1.088.791L8 12.347l-3.766 1.98a.75.75 0 0 1-1.088-.79l.72-4.194L.818 6.374a.75.75 0 0 1 .416-1.28l4.21-.611L7.327.668A.75.75 0 0 1 8 .25Z"/></svg>
											{repo.stars}
										</div>
									{/if}
									{#if repo.forks > 0}
										<div class="flex items-center gap-1">
											<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 16 16"><path d="M5 5.372v.878c0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75v-.878a2.25 2.25 0 1 1 1.5 0v.878a2.25 2.25 0 0 1-2.25 2.25h-1.5v2.128a2.251 2.251 0 1 1-1.5 0V8.5h-1.5A2.25 2.25 0 0 1 3.5 6.25v-.878a2.25 2.25 0 1 1 1.5 0ZM5 3.25a.75.75 0 1 0-1.5 0 .75.75 0 0 0 1.5 0Zm6.75.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm-3 8.75a.75.75 0 1 0-1.5 0 .75.75 0 0 0 1.5 0Z"/></svg>
											{repo.forks}
										</div>
									{/if}
								</div>
							</a>
						{/each}
					</div>
				</div>

				<!-- Top languages -->
				<div>
					<h2 class="text-sm font-semibold text-[#8b949e] uppercase tracking-wider mb-3">Top languages</h2>
					<div class="border border-[#30363d] bg-[#161b22] rounded-lg p-5 flex flex-col gap-4">
						{#each githubData.languages as lang}
							<div>
								<div class="flex justify-between text-xs mb-1">
									<div class="flex items-center gap-1.5">
										<span
											class="inline-block w-2.5 h-2.5 rounded-full"
											style="background:{langColor(lang.name)}"
										></span>
										<span class="text-[#c9d1d9]">{lang.name}</span>
									</div>
									<span class="text-[#8b949e]">{lang.count} repo{lang.count !== 1 ? 's' : ''}</span>
								</div>
								<div class="h-1.5 bg-[#21262d] rounded-full overflow-hidden">
									<div
										class="h-full rounded-full transition-all duration-500"
										style="width:{(lang.count / maxLangCount) * 100}%; background:{langColor(lang.name)}"
									></div>
								</div>
							</div>
						{/each}
					</div>
				</div>
			</div>
		{/if}
	</div>
</main>
