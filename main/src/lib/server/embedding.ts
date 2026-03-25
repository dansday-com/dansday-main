import { query, queryOne } from './db';
import { fetchGeneral } from './data';
import crypto from 'crypto';

interface EmbeddingConfig {
	url: string;
	key: string;
	model: string;
}

interface EmbeddingRow {
	id: number;
	table_name: string;
	row_id: number;
	content_hash: string;
	vector: string;
}

/** Returns embedding config if all 3 fields are set, otherwise null */
export async function getEmbeddingConfig(): Promise<EmbeddingConfig | null> {
	const general = await fetchGeneral();
	const url = (general.embedding_url as string | null)?.trim();
	const key = (general.embedding_key as string | null)?.trim();
	const model = (general.embedding_model as string | null)?.trim();
	if (!url || !key || !model) return null;
	return { url, key, model };
}

/** Call the embedding API to get a vector for the given text */
async function callEmbeddingApi(config: EmbeddingConfig, text: string): Promise<number[]> {
	let baseUrl = config.url.replace(/\/+$/, '');
	if (!baseUrl.endsWith('/embeddings')) {
		baseUrl += '/embeddings';
	}

	const res = await fetch(baseUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Authorization: `Bearer ${config.key}`
		},
		body: JSON.stringify({
			model: config.model,
			input: text
		})
	});

	if (!res.ok) {
		const body = await res.text();
		throw new Error(`Embedding API error ${res.status}: ${body}`);
	}

	const data = await res.json();
	return data.data[0].embedding as number[];
}

function contentHash(text: string): string {
	return crypto.createHash('sha256').update(text).digest('hex');
}

function stripHtml(html: string): string {
	return html
		.replace(/<[^>]*>/g, '')
		.replace(/&[a-z]+;/gi, ' ')
		.replace(/\s+/g, ' ')
		.trim();
}

/** Content builders for each table */
const contentBuilders: Record<string, (row: Record<string, any>) => string> = {
	articles: (r) => `${r.title}\n${stripHtml(r.description)}`,
	projects: (r) => `${r.title}\n${stripHtml(r.description)}`,
	experience: (r) => `${r.title}\n${r.period}\n${stripHtml(r.description)}`,
	service: (r) => `${r.title}\n${stripHtml(r.description)}`,
	skill: (r) => `${r.title} ${r.type}`,
	testimonial: (r) => `${r.name} ${r.company}\n${stripHtml(r.description)}`
};

/** Tables to embed and their queries */
const tableQueries: Record<string, string> = {
	articles: 'SELECT id, title, description FROM articles WHERE enable = 1',
	projects: 'SELECT id, title, description FROM projects WHERE enable = 1',
	experience: 'SELECT id, title, period, description FROM experience',
	service: 'SELECT id, title, description FROM service',
	skill: 'SELECT id, title, type FROM skill',
	testimonial: 'SELECT id, name, company, description FROM testimonial'
};

/** Embed all content, skipping rows whose content hasn't changed */
export async function embedAllContent(): Promise<{ embedded: number; skipped: number; errors: string[] }> {
	const config = await getEmbeddingConfig();
	if (!config) return { embedded: 0, skipped: 0, errors: ['Embedding not configured'] };

	let embedded = 0;
	let skipped = 0;
	const errors: string[] = [];

	for (const [tableName, sql] of Object.entries(tableQueries)) {
		const rows = await query<Record<string, any>>(sql);
		const builder = contentBuilders[tableName];

		for (const row of rows) {
			try {
				const text = builder(row);
				const hash = contentHash(text);

				const existing = await queryOne<EmbeddingRow>('SELECT content_hash FROM embeddings WHERE table_name = ? AND row_id = ?', [tableName, row.id]);

				if (existing && existing.content_hash === hash) {
					skipped++;
					continue;
				}

				const vector = await callEmbeddingApi(config, text);

				if (existing) {
					await query('UPDATE embeddings SET vector = ?, content_hash = ?, created_at = NOW() WHERE table_name = ? AND row_id = ?', [
						JSON.stringify(vector),
						hash,
						tableName,
						row.id
					]);
				} else {
					await query('INSERT INTO embeddings (table_name, row_id, content_hash, vector, created_at) VALUES (?, ?, ?, ?, NOW())', [
						tableName,
						row.id,
						hash,
						JSON.stringify(vector)
					]);
				}
				embedded++;
			} catch (e: any) {
				errors.push(`${tableName}:${row.id}: ${e.message}`);
			}
		}

		const rowIds = rows.map((r: any) => r.id);
		if (rowIds.length > 0) {
			const placeholders = rowIds.map(() => '?').join(',');
			await query(`DELETE FROM embeddings WHERE table_name = ? AND row_id NOT IN (${placeholders})`, [tableName, ...rowIds]);
		} else {
			await query('DELETE FROM embeddings WHERE table_name = ?', [tableName]);
		}
	}

	return { embedded, skipped, errors };
}

/** Embed a single query and return the vector */
export async function embedQuery(text: string): Promise<number[] | null> {
	const config = await getEmbeddingConfig();
	if (!config) return null;
	return callEmbeddingApi(config, text);
}

/** Cosine similarity between two vectors */
function cosineSimilarity(a: number[], b: number[]): number {
	let dot = 0;
	let normA = 0;
	let normB = 0;
	for (let i = 0; i < a.length; i++) {
		dot += a[i] * b[i];
		normA += a[i] * a[i];
		normB += b[i] * b[i];
	}
	const denom = Math.sqrt(normA) * Math.sqrt(normB);
	return denom === 0 ? 0 : dot / denom;
}

export interface SemanticResult {
	table_name: string;
	row_id: number;
	similarity: number;
}

/** Search embeddings by cosine similarity, returns top N results above threshold */
export async function semanticSearch(queryVector: number[], topN: number = 20, threshold: number = 0.3): Promise<SemanticResult[]> {
	const allEmbeddings = await query<EmbeddingRow>('SELECT table_name, row_id, vector FROM embeddings');

	const scored: SemanticResult[] = [];
	for (const row of allEmbeddings) {
		const vector = JSON.parse(row.vector) as number[];
		const similarity = cosineSimilarity(queryVector, vector);
		if (similarity >= threshold) {
			scored.push({ table_name: row.table_name, row_id: row.row_id, similarity });
		}
	}

	scored.sort((a, b) => b.similarity - a.similarity);
	return scored.slice(0, topN);
}
