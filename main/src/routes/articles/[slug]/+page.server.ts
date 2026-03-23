import { fetchArticleBySlug } from '$lib/server/data';
import { error, redirect } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ params, parent }) => {
	const data = await parent();
	const section = (data.section ?? {}) as Record<string, unknown>;
	if (section.articles_enable !== 1 && section.articles_enable !== true) {
		throw redirect(302, '/');
	}
	try {
		const post = await fetchArticleBySlug(params.slug);
		const publishedDate =
			post.date_formated != null
				? (post.date_formated as string)
				: post.created_at != null
					? new Date(post.created_at as string).toLocaleDateString('en-US', {
							month: 'short',
							day: 'numeric',
							year: 'numeric'
						})
					: '';
		return {
			meta: {
				title: post.title as string,
				description: (post.short_desc as string) || '',
				poster: (post.image as string) || '',
				publishedDate
			},
			body: (post.description as string) || ''
		};
	} catch (e) {
		error(404, 'Article not found');
	}
};
