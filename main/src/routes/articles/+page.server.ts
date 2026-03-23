import { redirect } from '@sveltejs/kit';
import { fetchArticles } from '$lib/server/data';
import type { PageServerLoad } from './$types';

function slug(name: string) {
	return name
		.toLowerCase()
		.replace(/\s+/g, '-')
		.replace(/^-+|-+$/g, '');
}

export const load: PageServerLoad = async ({ parent }) => {
	const data = await parent();
	const section = (data.section ?? {}) as Record<string, unknown>;
	if (section.articles_enable !== 1 && section.articles_enable !== true) {
		throw redirect(302, '/');
	}
	try {
		const articles = await fetchArticles();
		const list = articles.map((row: Record<string, unknown>) => ({
			slug: slug(row.title as string),
			title: row.title as string,
			description: (row.short_desc as string) || '',
			publishedDate:
				row.created_at != null
					? new Date(row.created_at as string).toLocaleDateString('en-US', {
							month: 'short',
							day: 'numeric',
							year: 'numeric'
						})
					: '',
			poster: (row.image as string) || ''
		}));
		return { articles: list };
	} catch {
		return { articles: [] };
	}
};
