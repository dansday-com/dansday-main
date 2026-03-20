import mysql from 'mysql2/promise';
import { env } from '$env/dynamic/private';

let pool: mysql.Pool | null = null;

function getPool(): mysql.Pool {
	if (!pool) {
		const host = env.DB_HOST ?? '127.0.0.1';
		const port = parseInt(env.DB_PORT ?? '3306', 10);
		const user = env.DB_USERNAME ?? 'root';
		const password = env.DB_PASSWORD ?? '';
		const database = env.DB_DATABASE ?? '';
		if (!database) throw new Error('DB_DATABASE is not set');
		pool = mysql.createPool({
			host,
			port,
			user,
			password,
			database,
			waitForConnections: true,
			connectionLimit: 10,
			queueLimit: 0
		});
	}
	return pool;
}

export async function query<T = Record<string, unknown>>(sql: string, params: (string | number | null)[] = []): Promise<T[]> {
	const [rows] = await getPool().query(sql, params);
	return rows as T[];
}

export async function queryOne<T = Record<string, unknown>>(sql: string, params: (string | number | null)[] = []): Promise<T | null> {
	const rows = await query<T>(sql, params);
	return rows[0] ?? null;
}
