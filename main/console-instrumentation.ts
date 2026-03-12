import '@opentelemetry/api-logs';
import loggerProvider from './logger.js';

const SeverityNumber = {
	DEBUG: 5,
	INFO: 9,
	WARN: 13,
	ERROR: 17
};

function serializeMessage(...args: unknown[]): string {
	return args.map((arg) => (typeof arg === 'object' ? JSON.stringify(arg) : String(arg))).join(' ');
}

function isPlainRecord(v: unknown): v is Record<string, unknown> {
	return !!v && typeof v === 'object' && (v as { constructor?: unknown }).constructor === Object;
}

function toAttributeValue(v: unknown): string | number | boolean {
	if (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean') return v;
	if (v == null) return '';
	return JSON.stringify(v);
}

function attributesFromConsoleArgs(args: unknown[]): Record<string, string | number | boolean> {
	if (args.length >= 2 && typeof args[0] === 'string' && isPlainRecord(args[1])) {
		const attrs: Record<string, string | number | boolean> = { tag: args[0] };
		for (const [k, v] of Object.entries(args[1])) attrs[k] = toAttributeValue(v);
		return attrs;
	}
	if (args.length === 1 && isPlainRecord(args[0])) {
		const attrs: Record<string, string | number | boolean> = {};
		for (const [k, v] of Object.entries(args[0])) attrs[k] = toAttributeValue(v);
		return attrs;
	}
	if (args.length >= 1 && typeof args[0] === 'string' && /^\[.+\]$/.test(args[0])) {
		return { tag: args[0] };
	}
	return {};
}

if (loggerProvider) {
	const logger = loggerProvider.getLogger('default', '1.0.0');
	const originalConsole = {
		log: console.log,
		info: console.info,
		warn: console.warn,
		error: console.error,
		debug: console.debug
	};

	console.log = function (...args: unknown[]) {
		logger.emit({
			severityNumber: SeverityNumber.INFO,
			severityText: 'INFO',
			body: serializeMessage(...args),
			attributes: attributesFromConsoleArgs(args)
		});
		originalConsole.log.apply(console, args);
	};

	console.info = function (...args: unknown[]) {
		logger.emit({
			severityNumber: SeverityNumber.INFO,
			severityText: 'INFO',
			body: serializeMessage(...args),
			attributes: attributesFromConsoleArgs(args)
		});
		originalConsole.info.apply(console, args);
	};

	console.warn = function (...args: unknown[]) {
		logger.emit({
			severityNumber: SeverityNumber.WARN,
			severityText: 'WARN',
			body: serializeMessage(...args),
			attributes: attributesFromConsoleArgs(args)
		});
		originalConsole.warn.apply(console, args);
	};

	console.error = function (...args: unknown[]) {
		logger.emit({
			severityNumber: SeverityNumber.ERROR,
			severityText: 'ERROR',
			body: serializeMessage(...args),
			attributes: attributesFromConsoleArgs(args)
		});
		originalConsole.error.apply(console, args);
	};

	console.debug = function (...args: unknown[]) {
		logger.emit({
			severityNumber: SeverityNumber.DEBUG,
			severityText: 'DEBUG',
			body: serializeMessage(...args),
			attributes: attributesFromConsoleArgs(args)
		});
		originalConsole.debug.apply(console, args);
	};
}
