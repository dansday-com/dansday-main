import { redirect } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ parent }) => {
	const data = await parent();
	const section = (data.section ?? {}) as Record<string, unknown>;
	const contributeEnabled = section.contribute_enable !== 0 && section.contribute_enable !== false;
	if (!contributeEnabled) {
		throw redirect(302, '/');
	}
	return {};
};
