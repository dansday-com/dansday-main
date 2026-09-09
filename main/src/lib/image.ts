import { env } from '$env/dynamic/public';

export function resolveImageUrl(url: string | null | undefined, baseUrl: string): string {
	if (!url || typeof url !== 'string') return '';
	if (url.startsWith('http://') || url.startsWith('https://')) return url;
	const base = baseUrl.replace(/\/$/, '');
	const uploads = env.PUBLIC_UPLOADS_URL?.replace(/\/$/, '') ?? '';
	if (url.startsWith('uploads/')) return uploads ? `${uploads}/${url.slice(8)}` : `${base}/${url}`;
	if (url.startsWith('/uploads/')) return uploads ? `${uploads}/${url.slice(9)}` : `${base}${url}`;
	return url.startsWith('/') ? `${base}${url}` : `${base}/${url}`;
}
