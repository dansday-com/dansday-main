import figlet from 'figlet';
import { fetchGeneral, fetchHome, fetchSection, fetchAbouts } from '$lib/server/data';
import { env } from '$env/dynamic/private';
import type { LayoutServerLoad } from './$types';

function toAsciiBanner(text: string | null | undefined, font = 'Standard'): string | null {
	if (!text || typeof text !== 'string' || !text.trim()) return null;
	try {
		return figlet.textSync(text.trim(), { font });
	} catch {
		return null;
	}
}

function resolveUrl(url: string | null | undefined, base: string): string {
	if (!url || typeof url !== 'string') return '';
	const b = base.replace(/\/$/, '');
	if (url.startsWith('http://') || url.startsWith('https://')) return url;
	const path = url.startsWith('/') ? url : `/${url}`;
	if (path.startsWith('/uploads/')) return `${b}${path}`;
	return `${b}${path}`;
}

export const load: LayoutServerLoad = async () => {
	try {
		const publicBase = env.ADMIN_PUBLIC_URL?.replace(/\/$/, '') ?? '';
		const [generalData, home, sectionData, aboutsData] = await Promise.all([fetchGeneral(), fetchHome(), fetchSection(), fetchAbouts()]);
		const general = (generalData as Record<string, unknown>) ?? {};

		const hasUrl = Boolean(general.ai_url && typeof general.ai_url === 'string' && general.ai_url.trim() !== '');
		const hasKey = Boolean(general.ai_key && typeof general.ai_key === 'string' && general.ai_key.trim() !== '');
		const hasModel = Boolean(general.ai_model && typeof general.ai_model === 'string' && general.ai_model.trim() !== '');
		const aiTerminalConfigured = hasUrl && hasKey && hasModel;

		delete general.ai_key;
		delete general.ai_url;
		delete general.ai_model;

		const homeRecord = (home as Record<string, unknown>) ?? {};
		const section = (sectionData as Record<string, unknown>) ?? {};
		const siteName = (general.title as string) ?? (homeRecord.title as string) ?? '';
		const design_skills = (aboutsData.design_skills as Array<Record<string, unknown>>) ?? [];
		const dev_skills = (aboutsData.dev_skills as Array<Record<string, unknown>>) ?? [];
		const edu_experiences = (aboutsData.edu_experiences as Array<Record<string, unknown>>) ?? [];
		const emp_experiences = (aboutsData.emp_experiences as Array<Record<string, unknown>>) ?? [];
		const testimonials = (aboutsData.testimonials as Array<Record<string, unknown>>) ?? [];
		const services = (aboutsData.services as Array<Record<string, unknown>>) ?? [];
		let socialLinks: Array<{ title: string; text: string }> = [];
		try {
			const raw = general.social_links;
			if (typeof raw === 'string') socialLinks = JSON.parse(raw) ?? [];
			else if (Array.isArray(raw)) socialLinks = raw;
		} catch {}
		const defaultOgImage = resolveUrl((general.image_favicon as string) ?? null, publicBase);
		const defaultFavicon = resolveUrl((general.image_favicon as string) ?? null, publicBase);
		const projectsListMeta = {
			title: siteName ? `Projects | ${siteName}` : 'Projects',
			description: (general.description as string) ?? ''
		};
		const homeTitleAscii = toAsciiBanner(homeRecord.title as string);
		return {
			siteName,
			adminBaseUrl: publicBase,
			defaultOgImage,
			defaultFavicon,
			socialLinks,
			home: homeRecord,
			homeTitleAscii,
			general,
			section,
			design_skills,
			dev_skills,
			edu_experiences,
			emp_experiences,
			testimonials,
			services,
			projectsListMeta,
			aiTerminalConfigured
		};
	} catch {
		const publicBase = env.ADMIN_PUBLIC_URL?.replace(/\/$/, '') ?? '';
		return {
			siteName: '',
			adminBaseUrl: publicBase,
			defaultOgImage: '',
			defaultFavicon: '',
			socialLinks: [],
			home: {},
			homeTitleAscii: null,
			general: {},
			section: {},
			design_skills: [],
			dev_skills: [],
			edu_experiences: [],
			emp_experiences: [],
			testimonials: [],
			services: [],
			projectsListMeta: { title: 'Projects', description: '' },
			aiTerminalConfigured: false
		};
	}
};
