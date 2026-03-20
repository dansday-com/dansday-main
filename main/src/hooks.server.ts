import type { Handle } from '@sveltejs/kit';

function detectDevice(ua: string): 'mobile' | 'tablet' | 'desktop' | 'bot' | 'unknown' {
	const v = ua.toLowerCase();
	if (!v) return 'unknown';
	if (/(bot|crawler|spider|slurp|facebookexternalhit|twitterbot)/i.test(v)) return 'bot';
	if (/(ipad|tablet)/i.test(v)) return 'tablet';
	if (/(mobi|iphone|android)/i.test(v)) return 'mobile';
	return 'desktop';
}

function detectBrowser(ua: string): string {
	if (!ua) return 'unknown';
	if (/Edg\//.test(ua)) return 'edge';
	if (/OPR\//.test(ua) || /Opera/.test(ua)) return 'opera';
	if (/Chrome\//.test(ua) && !/Edg\//.test(ua) && !/OPR\//.test(ua)) return 'chrome';
	if (/Safari\//.test(ua) && !/Chrome\//.test(ua)) return 'safari';
	if (/Firefox\//.test(ua)) return 'firefox';
	return 'unknown';
}

function detectOs(ua: string): string {
	if (!ua) return 'unknown';
	if (/Windows NT/.test(ua)) return 'windows';
	if (/Android/.test(ua)) return 'android';
	if (/(iPhone|iPad|iPod)/.test(ua)) return 'ios';
	if (/Mac OS X/.test(ua)) return 'macos';
	if (/Linux/.test(ua)) return 'linux';
	return 'unknown';
}

const preloadHandle: Handle = async ({ event, resolve }) => {
	const start = Date.now();
	const fonts = ['commit-mono-latin-400-normal'];
	const response = await resolve(event, {
		preload: ({ type, path }) => {
			if (type === 'font') {
				if (!path.endsWith('.woff2')) return false;
				return fonts.some((font) => path.includes(font));
			}
			return type === 'js' || type === 'css';
		}
	});

	const headers = event.request.headers;
	const ua = headers.get('user-agent') ?? '';
	const cfIp = headers.get('cf-connecting-ip');
	const cfPseudoIpv4 = headers.get('cf-pseudo-ipv4');
	const realIp = headers.get('x-real-ip');
	const xff = headers.get('x-forwarded-for');

	const ipFromHeader = cfIp || realIp || (xff ? xff.split(',')[0]?.trim() : '');
	const ip = ipFromHeader || event.getClientAddress();

	console.info('[request]', {
		ip,
		ip_ipv4: cfPseudoIpv4 || undefined,
		ip_source: cfIp ? 'cf-connecting-ip' : realIp ? 'x-real-ip' : xff ? 'x-forwarded-for' : 'getClientAddress',
		device: detectDevice(ua),
		browser: detectBrowser(ua),
		os: detectOs(ua),
		user_agent: ua,
		method: event.request.method,
		path: event.url.pathname,
		query: event.url.search,
		referrer: headers.get('referer') ?? headers.get('referrer') ?? '',
		accept_language: headers.get('accept-language') ?? '',
		status: response.status,
		duration_ms: Date.now() - start
	});

	const contentType = response.headers.get('content-type') ?? '';
	if (contentType.includes('text/html') || contentType.includes('application/json')) {
		response.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate');
		response.headers.set('CDN-Cache-Control', 'no-store');
	}

	return response;
};

export const handle = preloadHandle;
