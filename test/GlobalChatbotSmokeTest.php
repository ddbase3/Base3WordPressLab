<?php declare(strict_types=1);

namespace Base3\Api {
	interface IAssetResolver { public function resolve(string $path): string; }
}

namespace Base3\LinkTarget\Api {
	interface ILinkTargetService { public function getLink(array $target, array $params = []): string; }
}

namespace Base3\Settings\Api {
	interface ISettingsStore {
		public function get(string $group, string $name, array $default = []): array;
		public function set(string $group, string $name, array $settings): void;
		public function has(string $group, string $name): bool;
		public function remove(string $group, string $name): void;
		public function getGroup(string $group): array;
		public function save(): void;
		public function reload(): void;
	}
}

namespace Base3\Translation\Api {
	interface ITranslation {
		public function translate(string $set, string $section, string $key, string $fallback = '', array $replacements = []): string;
	}
}

namespace Base3WordPress\Api {
	interface IBase3WordPressSettings { public function getAdministrationConfig(): array; }
}

namespace {
	use Base3\Api\IAssetResolver;
	use Base3\LinkTarget\Api\ILinkTargetService;
	use Base3\Settings\Api\ISettingsStore;
	use Base3\Translation\Api\ITranslation;
	use Base3WordPressLab\Integration\GlobalChatbot;
	use Base3WordPressLab\Service\Base3WordPressLabSettings;
	use ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherSettings;

	$hooks = [];
	$styles = [];
	$scripts = [];

	function add_action(string $hook, callable $callback, int $priority = 10): void {
		global $hooks;
		$hooks[$hook][] = [$callback, $priority];
	}

	function wp_enqueue_style(string $handle, string $src, array $dependencies = [], ?string $version = null): void {
		global $styles;
		$styles[$handle] = $src;
	}

	function wp_enqueue_script(string $handle, string $src, array $dependencies = [], ?string $version = null, bool $footer = false): void {
		global $scripts;
		$scripts[$handle] = [$src, $footer];
	}

	function esc_url(string $url): string { return htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); }
	function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

	$clientStackSettings = dirname(__DIR__, 2) . '/ClientStack/src/GlobalChatbotLauncher/GlobalChatbotLauncherSettings.php';
	if (!is_file($clientStackSettings)) {
		throw new \RuntimeException('Pull ClientStack before running the WordPress launcher test.');
	}
	require_once $clientStackSettings;
	require_once dirname(__DIR__) . '/src/Service/Base3WordPressLabSettings.php';
	require_once dirname(__DIR__) . '/src/Integration/GlobalChatbot.php';

	$link = new class implements ILinkTargetService {
		public function getLink(array $target, array $params = []): string {
			return 'https://example.org/wp-admin/admin-ajax.php?' . http_build_query(array_merge([
				'action' => 'base3_dispatch',
				'name' => (string) ($target['name'] ?? ''),
				'out' => (string) ($target['out'] ?? 'php')
			], $params));
		}
	};
	$resolver = new class implements IAssetResolver {
		public function resolve(string $path): string { return 'https://example.org/' . $path; }
	};
	$translation = new class implements ITranslation {
		public function translate(string $set, string $section, string $key, string $fallback = '', array $replacements = []): string {
			return $key === 'global_chatbot_header_title' ? 'Ask <strong>KIM</strong>' : $fallback;
		}
	};
	$store = new class implements ISettingsStore {
		private array $groups = [];
		public function get(string $group, string $name, array $default = []): array { return $this->groups[$group][$name] ?? $default; }
		public function set(string $group, string $name, array $settings): void { $this->groups[$group][$name] = $settings; }
		public function has(string $group, string $name): bool { return isset($this->groups[$group][$name]); }
		public function remove(string $group, string $name): void { unset($this->groups[$group][$name]); }
		public function getGroup(string $group): array { return $this->groups[$group] ?? []; }
		public function save(): void {}
		public function reload(): void {}
	};

	$configuration = GlobalChatbotLauncherSettings::DEFAULTS;
	$configuration['mode_docked_enabled'] = true;
	$configuration['mode_cover_enabled'] = true;
	$configuration['mode_fullscreen_enabled'] = true;
	$configuration['resizable_overlay'] = true;
	$configuration['reopen_overlay'] = true;
	GlobalChatbotLauncherSettings::save($store, $configuration);

	$launcher = new GlobalChatbot($link, $resolver, $translation, $store);
	$launcher->register();
	foreach (['wp_enqueue_scripts', 'admin_enqueue_scripts', 'wp_footer', 'admin_footer'] as $hook) {
		if (count($hooks[$hook] ?? []) !== 1) throw new \RuntimeException('Missing WordPress hook: ' . $hook);
	}
	$launcher->enqueueAssets();
	if (($scripts['base3-clientstack-assetloader'][1] ?? null) !== false
		|| !str_ends_with($scripts['base3-clientstack-assetloader'][0] ?? '', 'ClientStack/assets/assetloader/assetloader.min.js')
		|| !str_ends_with($styles['base3-global-chatbot'] ?? '', 'ClientStack/assets/globalchatbotlauncher/styles/globalchatbotlauncher.css')) {
		throw new \RuntimeException('Shared ClientStack assets were not enqueued.');
	}
	if (isset($scripts['base3-global-chatbot'])) throw new \RuntimeException('Legacy WordPress launcher is still enqueued.');

	ob_start();
	$launcher->render();
	$html = (string) ob_get_clean();
	if (!str_contains($html, 'data-base3-global-chatbot-launcher')
		|| !str_contains($html, '<script type="module" src="https://example.org/plugin/ClientStack/assets/globalchatbotlauncher/index.js"></script>')
		|| str_contains($html, '<iframe')
		|| str_contains($html, 'global-chatbot.js')) {
		throw new \RuntimeException('The WordPress launcher does not use the shared ES module.');
	}
	if (!preg_match('/data-ajax-url="([^"]+)"/', $html, $match)) throw new \RuntimeException('Render URL is missing.');
	parse_str((string) parse_url(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), PHP_URL_QUERY), $query);
	if (($query['action'] ?? '') !== 'base3_dispatch' || ($query['name'] ?? '') !== 'chatbotdisplay'
		|| ($query['out'] ?? '') !== 'html') throw new \RuntimeException('The generic BASE3 display route changed.');
	$data = (string) ($query['base3_display_data'] ?? '');
	$identity = json_decode((string) base64_decode(strtr($data, '-_', '+/'), true), true, 512, JSON_THROW_ON_ERROR);
	if ($identity !== ['config_group' => 'chatbot', 'config_name' => 'default']) throw new \RuntimeException('Global chatbot identity changed.');
	foreach ($configuration as $key => $enabled) {
		$name = 'data-' . str_replace('_', '-', $key);
		$expected = $enabled ? '1' : '0';
		if (!str_contains($html, $name . '="' . $expected . '"')) throw new \RuntimeException('Missing window option: ' . $name);
	}
	if (!str_contains($html, 'data-header-title="Ask &lt;strong&gt;KIM&lt;/strong&gt;"')
		|| !str_contains($html, 'data-dock-target="body"')
		|| !str_contains($html, 'data-dock-header-target="#wpadminbar"')) {
		throw new \RuntimeException('The shared launcher branding or sidebar target is incorrect.');
	}

	$administration = (new Base3WordPressLabSettings($translation, $link))->getAdministrationConfig();
	$chatbotTabs = [];
	foreach ($administration as $tab) {
		if ($tab['name'] === 'chatbot') $chatbotTabs = $tab['displays'];
	}
	if (array_column($chatbotTabs, 'name') !== [
		'chatbotconfigdisplay', 'globalchatbotlauncherconfigdisplay', 'chatbotextensionsconfigdisplay'
	]) throw new \RuntimeException('Chatbot administration tab order changed.');
	$configData = $chatbotTabs[0]['data'];
	if (($configData['group'] ?? '') !== $identity['config_group']
		|| ($configData['name'] ?? '') !== $identity['config_name']
		|| count($configData['resources']['endpoints'] ?? []) !== 13) {
		throw new \RuntimeException('The chatbot administration or FileManager configuration was changed.');
	}

	echo "GlobalChatbotSmokeTest OK: ClientStack ES module, all launcher flags, generic dispatch, tab ordering, 13 file endpoints.\n";
}
