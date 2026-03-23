import { fetchProjectBySlug } from '$lib/server/data';
import { error, redirect } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ params, parent }) => {
	const data = await parent();
	const section = (data.section ?? {}) as Record<string, unknown>;
	if (section.projects_enable !== 1 && section.projects_enable !== true) {
		throw redirect(302, '/');
	}
	try {
		const project = await fetchProjectBySlug(params.slug);
		return {
			meta: {
				title: project.title as string,
				description: (project.short_desc as string) || '',
				poster: (project.image as string) || ''
			},
			body: (project.description as string) || ''
		};
	} catch {
		error(404, 'Project not found');
	}
};
