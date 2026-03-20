import { createClient } from 'redis';
import { env } from '$env/dynamic/private';

let client: ReturnType<typeof createClient> | null = null;

async function getClient() {
	if (!client) {
		client = createClient({ url: env.REDIS_URL });
		client.on('error', (err) => console.error('[Redis]', err));
		await client.connect();
	}
	return client;
}

export async function redisGet(key: string): Promise<string | null> {
	const c = await getClient();
	return c.get(key);
}

export async function redisSet(key: string, value: string, ttlSeconds?: number): Promise<void> {
	const c = await getClient();
	if (ttlSeconds) {
		await c.set(key, value, { EX: ttlSeconds });
	} else {
		await c.set(key, value);
	}
}
