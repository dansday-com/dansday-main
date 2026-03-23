import { query, queryOne } from './db';

type Row = Record<string, unknown>;

function formatDate(d: Date | string | null | undefined): string | null {
	if (d == null) return null;
	const date = typeof d === 'string' ? new Date(d) : d;
	if (Number.isNaN(date.getTime())) return null;
	return date.toLocaleDateString('en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric'
	});
}

export async function fetchGeneral(): Promise<Record<string, unknown>> {
	const row = await queryOne<Row>('SELECT * FROM page_setting WHERE id = 1 LIMIT 1');
	return row ?? {};
}

export async function fetchHome(): Promise<Record<string, unknown>> {
	const row = await queryOne<Row>('SELECT * FROM page_home WHERE id = 1 LIMIT 1');
	if (!row) return { title: null, description: null };
	return row;
}

export async function fetchSection(): Promise<Record<string, unknown>> {
	const row = await queryOne<Row>('SELECT * FROM page_section WHERE id = 1 LIMIT 1');
	return row ?? {};
}

export async function fetchAbouts(): Promise<{
	design_skills: Row[];
	dev_skills: Row[];
	edu_experiences: Row[];
	emp_experiences: Row[];
	testimonials: Row[];
	services: Row[];
}> {
	const [design_skills, dev_skills, edu_experiences, emp_experiences, testimonials, services] = await Promise.all([
		query<Row>("SELECT * FROM skill WHERE type = 'design' ORDER BY `order` ASC"),
		query<Row>("SELECT * FROM skill WHERE type = 'development' ORDER BY `order` ASC"),
		query<Row>("SELECT * FROM experience WHERE type = 'education' ORDER BY `order` ASC"),
		query<Row>("SELECT * FROM experience WHERE type = 'employment' ORDER BY `order` ASC"),
		query<Row>('SELECT * FROM testimonial ORDER BY `order` ASC'),
		query<Row>('SELECT * FROM service ORDER BY `order` ASC')
	]);
	return {
		design_skills,
		dev_skills,
		edu_experiences,
		emp_experiences,
		testimonials,
		services
	};
}

export async function fetchArticles(): Promise<Row[]> {
	return query<Row>("SELECT * FROM articles WHERE status = 'published' ORDER BY `created_at` DESC");
}

export async function fetchArticle(slug: string): Promise<Record<string, unknown>> {
	const post = await queryOne<Row>('SELECT * FROM articles WHERE slug = ? LIMIT 1', [slug]);
	if (!post) throw new Error('Not found');
	const category = await queryOne<Row>('SELECT name FROM article_category WHERE id = ? LIMIT 1', [post.category_id as number]);
	const data: Record<string, unknown> = { ...post };
	data.category_name = category?.name ?? null;
	data.date_formated = formatDate(post.created_at as string | Date) ?? null;
	return data;
}

export async function fetchProjects(): Promise<{
	projects: Row[];
	projects_categories: Row[];
}> {
	const [projects, projects_categories] = await Promise.all([
		query<Row>('SELECT * FROM project WHERE enable = 1 ORDER BY `created_at` DESC'),
		query<Row>('SELECT * FROM project_category ORDER BY id ASC')
	]);
	return { projects, projects_categories };
}

export async function fetchProjectBySlug(slug: string): Promise<Record<string, unknown>> {
	const projects = await query<Row>('SELECT * FROM project WHERE enable = 1', []);
	function makeSlug(name: string) {
		return name
			.toLowerCase()
			.replace(/\s+/g, '-')
			.replace(/^-+|-+$/g, '');
	}
	const project = projects.find((p) => makeSlug(p.title as string) === slug) ?? null;
	if (!project) throw new Error('Not found');
	const category = await queryOne<Row>('SELECT name FROM project_category WHERE id = ? LIMIT 1', [project.category_id as number]);
	const data: Record<string, unknown> = { ...project };
	data.category_name = category?.name ?? null;
	return data;
}
