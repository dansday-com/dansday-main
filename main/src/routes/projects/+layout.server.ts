import { fetchProjects } from '$lib/server/data';
import type { LayoutServerLoad } from './$types';

function slug(name: string) {
	return name
		.toLowerCase()
		.replace(/\s+/g, '-')
		.replace(/^-+|-+$/g, '');
}

export const load: LayoutServerLoad = async () => {
	try {
		const { projects, projects_categories } = await fetchProjects();
		const categories = (projects_categories ?? []) as Array<{ id: number; name: string }>;
		const projectList = (projects ?? []) as Array<{ category_id?: number }>;
		const categoryFilterList: Array<{ id: number; name: string; slug: string }> = [];
		for (const cat of categories) {
			const hasProject = projectList.some((p) => p.category_id === cat.id);
			if (hasProject) categoryFilterList.push({id: cat.id, name: cat.name, slug: slug(cat.name) });
		}
		return { categoryFilterList };
	} catch {
		return { categoryFilterList: [] };
	}
};
