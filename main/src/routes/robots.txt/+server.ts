import { env } from '$env/dynamic/private';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async () => {
	const baseUrl = env.BASE_URL;
	if (!baseUrl) {
		return new Response('BASE_URL environment variable is not set', { status: 503 });
	}
	return new Response(
		`User-Agent: *
Allow: /

Host: ${baseUrl}
Sitemap: ${baseUrl}/sitemap.xml`,
		{
			headers: {
				'Content-Type': 'text/plain',
				'Cache-Control': 'max-age=3600'
			}
		}
	);
};
