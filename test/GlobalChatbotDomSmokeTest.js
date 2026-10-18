'use strict';

// Checks the WordPress host boundary against the installed ClientStack module.
// Browser interaction tests are owned by ClientStack/dev/GlobalChatbotLauncher.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

async function main() {
	const root = path.resolve(__dirname, '../..');
	const src = path.join(root, 'ClientStack/assets/globalchatbotlauncher');
	const index = path.join(src, 'index.js');
	const moduleJs = path.join(src, 'GlobalChatbotLauncher.js');
	const css = path.join(src, 'styles/globalchatbotlauncher.css');
	for (const file of [index, moduleJs, css]) {
		assert.ok(fs.existsSync(file), `Missing deployed ClientStack asset: ${file}`);
	}
	const module = await import(pathToFileURL(index).href);
	for (const name of ['GlobalChatbotLauncher', 'mountGlobalChatbotLauncher', 'initializeGlobalChatbotLaunchers']) {
		assert.equal(typeof module[name], 'function', `Missing shared launcher export: ${name}`);
	}
	const host = fs.readFileSync(path.join(__dirname, '../src/Integration/GlobalChatbot.php'), 'utf8');
	assert.match(host, /data-base3-global-chatbot-launcher/);
	assert.match(host, /globalchatbotlauncher\/index\.js/);
	assert.match(host, /<script type=\\?"module/);
	assert.doesNotMatch(host, /global-chatbot\.js|global-chatbot\.css|<iframe/);
	console.log('GlobalChatbotDomSmokeTest OK: WordPress uses the deployed ClientStack ES module and stylesheet.');
}

main().catch((error) => { console.error(error); process.exitCode = 1; });
