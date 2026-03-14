import { LoggerProvider, BatchLogRecordProcessor } from '@opentelemetry/sdk-logs';
import { OTLPLogExporter } from '@opentelemetry/exporter-logs-otlp-http';
import { resourceFromAttributes } from '@opentelemetry/resources';
import { ATTR_SERVICE_NAME } from '@opentelemetry/semantic-conventions';
const endpoint = process.env.OTEL_EXPORTER_OTLP_ENDPOINT;
const serviceName = process.env.OTEL_SERVICE_NAME || 'dansday-main';
let loggerProvider = null;
if (endpoint?.trim()) {
	try {
		const resource = resourceFromAttributes({
			[ATTR_SERVICE_NAME]: serviceName
		});
		const logExporter = new OTLPLogExporter({});
		loggerProvider = new LoggerProvider({
			resource,
			processors: [new BatchLogRecordProcessor(logExporter)]
		});
		const flushInterval = setInterval(() => loggerProvider?.forceFlush().catch(() => {}), 5000);
		if (flushInterval.unref) flushInterval.unref();
	} catch (_) {
		loggerProvider = null;
	}
}
export default loggerProvider;
