import { env } from '$env/dynamic/private';
import { fetchSection, fetchArticles, fetchProjects, fetchGeneral } from '$lib/server/data';
import type { RequestHandler } from './$types';

function escapeXml(unsafe: string): string {
	return unsafe.replace(
		/[&<"'>]/g,
		(match) =>
			({
				'&': '&amp;',
				'<': '&lt;',
				'"': '&quot;',
				"'": '&apos;',
				'>': '&gt;'
			})[match] || match
	);
}

const notDisabled = (v: unknown) => v !== 0 && v !== false;

export const GET: RequestHandler = async () => {
	const baseUrl = env.BASE_URL;
	if (!baseUrl) {
		return new Response('BASE_URL environment variable is not set', { status: 503 });
	}

	let section: Record<string, unknown> = {};
	let aiTerminalConfigured = false;
	let articleUrlData: Array<{ loc: string; lastmod?: string; changefreq: string; priority: number }> = [];
	let projectUrlData: Array<{ loc: string; lastmod?: string; changefreq: string; priority: number }> = [];

	try {
		section = (await fetchSection()) as Record<string, unknown>;
		const general = await fetchGeneral() as Record<string, unknown>;
		const hasUrl = Boolean(general.ai_url && typeof general.ai_url === 'string' && general.ai_url.trim() !== '');
		const hasKey = Boolean(general.ai_key && typeof general.ai_key === 'string' && general.ai_key.trim() !== '');
		const hasModel = Boolean(general.ai_model && typeof general.ai_model === 'string' && general.ai_model.trim() !== '');
		aiTerminalConfigured = hasUrl && hasKey && hasModel;
	} catch {}

	if (notDisabled(section.articles_enable)) {
		try {
			const articles = await fetchArticles();
			articleUrlData = articles.map((row) => ({
				loc: `${baseUrl}/articles/${row.slug as string}`,
				lastmod:
					row.updated_at != null
						? new Date(row.updated_at as string).toISOString()
						: row.created_at != null
							? new Date(row.created_at as string).toISOString()
							: undefined,
				changefreq: 'daily',
				priority: 0.8
			}));
		} catch {}
	}

	if (notDisabled(section.projects_enable)) {
		try {
			const { projects } = await fetchProjects();
			projectUrlData = (projects ?? []).map((row) => ({
				loc: `${baseUrl}/projects/${row.id as number}`,
				lastmod: row.updated_at != null ? new Date(row.updated_at as string).toISOString() : undefined,
				changefreq: 'daily',
				priority: 0.6
			}));
		} catch {}
	}

	const ABOUTS_CHILDREN = [
		{ slug: 'experience' as const, enableKey: 'experience_enable' },
		{ slug: 'services' as const, enableKey: 'services_enable' },
		{ slug: 'skills' as const, enableKey: 'skills_enable' },
		{ slug: 'testimonial' as const, enableKey: 'testimonial_enable' }
	] as const;
	const aboutsUrlData: Array<{ loc: string; changefreq: string; priority: number; lastmod: string }> = [];
	const aboutOn = notDisabled(section.about_enable);
	const enabledAboutsChildren = ABOUTS_CHILDREN.filter((c) => notDisabled(section[c.enableKey]));
	if (aboutOn && enabledAboutsChildren.length > 0) {
		for (const { slug } of enabledAboutsChildren) {
			aboutsUrlData.push({
				loc: `${baseUrl}/abouts/${slug}`,
				changefreq: 'daily',
				priority: 0.5,
				lastmod: new Date().toISOString()
			});
		}
	}

	const allUrlData = [
		{ loc: `${baseUrl}/`, changefreq: 'daily', priority: 1.0, lastmod: new Date().toISOString() },
		...(notDisabled(section.projects_enable)
			? [{ loc: `${baseUrl}/projects`, changefreq: 'daily' as const, priority: 1.0, lastmod: new Date().toISOString() }]
			: []),
		...(notDisabled(section.articles_enable)
			? [{ loc: `${baseUrl}/articles`, changefreq: 'daily' as const, priority: 1.0, lastmod: new Date().toISOString() }]
			: []),
		...(aiTerminalConfigured
			? [{ loc: `${baseUrl}/terminal`, changefreq: 'daily' as const, priority: 0.8, lastmod: new Date().toISOString() }]
			: []),
		...aboutsUrlData,
		...articleUrlData,
		...projectUrlData
	];

	const urlElements = allUrlData
		.map(({ loc, lastmod, changefreq, priority }) => {
			const lastmodElement = lastmod ? `<lastmod>${lastmod}</lastmod>` : '';
			return `
    <url>
      <loc>${escapeXml(loc)}</loc>${lastmodElement}
      <changefreq>${changefreq}</changefreq>
      <priority>${priority.toFixed(1)}</priority>
    </url>`;
		})
		.join('');

	return new Response(
		`<?xml version="1.0" encoding="UTF-8" ?>
		<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9">
			${urlElements}
		</urlset>`.trim(),
		{
			headers: {
				'Content-Type': 'application/xml',
				'Cache-Control': 'max-age=3600'
			}
		}
	);
};
