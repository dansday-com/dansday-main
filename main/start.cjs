// Wrapper for hosts that use require() to start the app (e.g. LiteSpeed lsnode).
// SvelteKit build is ESM with top-level await; require() cannot load it.
const path = require('path');
// Load .env so panel env vars that don't reach the process can be replaced by a file on the server
require('dotenv').config({ path: path.join(__dirname, '.env') });
import(path.join(__dirname, 'build', 'index.js')).catch((err) => {
	console.error(err);
	process.exit(1);
});
