import { query } from '../db';

const MAX_QUERY_WORDS = 12;

export function fullTextQuery(text: string): string {
	const words = [
		...new Set(
			text
				.split(/[\s\-]+/)
				.map((w) => w.replace(/[+><~*"@()]/g, ''))
				.filter((w) => w.length > 0)
		)
	].slice(0, MAX_QUERY_WORDS);
	return words.map((w) => `${w}*`).join(' ');
}

export interface SearchOpts {
	table: string;
	fields: string;
	where: string;
	ftFields: string[];
	keyword: string;
	dateClause: string;
	dateParams: (string | number)[];
	extraClause?: string;
	extraParams?: (string | number)[];
	limit: number;
}

export async function search<T>(opts: SearchOpts): Promise<{ rows: T[]; total: number }> {
	const ft = fullTextQuery(opts.keyword);
	const matchExpr = `MATCH(${opts.ftFields.join(', ')}) AGAINST(? IN BOOLEAN MODE)`;
	const base = `FROM ${opts.table} WHERE ${opts.where}${opts.extraClause ?? ''}${opts.dateClause}${ft ? ` AND ${matchExpr}` : ''}`;
	const baseParams = [...(opts.extraParams ?? []), ...opts.dateParams, ...(ft ? [ft] : [])];

	const [counted, rows] = await Promise.all([
		query<{ cnt: number }>(`SELECT COUNT(*) as cnt ${base}`, baseParams),
		ft
			? query<T>(`SELECT ${opts.fields}, ${matchExpr} AS relevance ${base} ORDER BY relevance DESC, created_at DESC LIMIT ?`, [ft, ...baseParams, opts.limit])
			: query<T>(`SELECT ${opts.fields} ${base} ORDER BY created_at DESC LIMIT ?`, [...baseParams, opts.limit])
	]);

	return { rows, total: Number(counted[0]?.cnt ?? rows.length) };
}
