const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '.env') });

(async () => {
	try {
		await import(path.join(__dirname, 'otel', 'console-instrumentation.js'));
	} catch (_) {}
	await import(path.join(__dirname, 'build', 'index.js'));
})().catch((err) => {
	console.error(err);
	process.exit(1);
});
