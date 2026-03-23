import { redirect } from '@sveltejs/kit';
import { fetchProjects } from '$lib/server/data';
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
	if (section.projects_enable !== 1 && section.projects_enable !== true) {
		throw redirect(302, '/');
	}
	try {
		const { projects, projects_categories } = await fetchProjects();
		const categories = (projects_categories ?? []) as Array<{ id: number; name: string }>;
		const byId = Object.fromEntries(categories.map((c) => [c.id, c]));
		const items = (projects ?? []).map((row: Record<string, unknown>) => {
			const catId = row.category_id as number | undefined;
			const cat = catId ? byId[catId] : null;
			return {
				id: row.id as number,
				slug: slug(row.title as string),
				title: row.title as string,
				description: (row.short_desc as string) || '',
				poster: (row.image as string) || '',
				category_id: catId,
				category_name: cat?.name ?? null,
				category_slug: cat ? slug(cat.name) : null
			};
		});
		return { items };
	} catch {
		return { items: [] };
	}
};
