<?php declare(strict_types=1);

namespace Base3\Api {
	interface IAssetResolver { public function resolve(string $path): string; }
}

namespace Base3\Authorization\Api {
	interface IFeature {
		public static function getName(): string;
		public function getLabel(): string;
		public function getDescription(): string;
		public function getPermissions(): array;
		public function getDefaultAudience(): array;
	}
	interface IAuthorizationService {
		public function getDefaultAuthority(): string;
		public function decide(\Base3\Authorization\Dto\AuthorizationRequest $request): \Base3\Authorization\Dto\AuthorizationDecision;
		public function decideMany(array $requests): array;
	}
}

namespace Base3\Usermanager {
	final class Permission {
		public function __construct(public string $scope, public string $permission) {}
		public static function for(string $scope, string $permission): self { return new self($scope, $permission); }
	}
}

namespace Base3\Authorization\Dto {
	final class AuthorizationRequest {
		public function __construct(public string $authority, public \Base3\Usermanager\Permission $permission) {}
	}
	final class AuthorizationDecision {
		public function __construct(private readonly bool $allowed) {}
		public function isAllowed(): bool { return $this->allowed; }
	}
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
	interface IBase3WordPressSettings {
		public function getAdministrationConfig(): array;
		public function getAdministrationAccessLevel(string $name): ?string;
	}
}
namespace Base3\Usermanager\Api {
	interface IUsermanager { public function getGroups(): array; }
}

namespace {
	use Base3\Api\IAssetResolver;
	use Base3\Authorization\Api\IAuthorizationService;
	use Base3\Authorization\Dto\AuthorizationRequest;
	use Base3\Authorization\Dto\AuthorizationDecision;
	use Base3WordPressLab\Feature\GlobalChatbotLauncherFeature;
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
	function wp_unslash(string $value): string { return stripslashes($value); }
	final class TestWpDie extends \RuntimeException {
		public function __construct(public int $status) { parent::__construct('wp_die'); }
	}
	function wp_die(string $message, string $title = '', array $args = []): never {
		throw new TestWpDie((int) ($args['response'] ?? 500));
	}

	$clientStackSettings = dirname(__DIR__, 2) . '/ClientStack/src/GlobalChatbotLauncher/GlobalChatbotLauncherSettings.php';
	if (!is_file($clientStackSettings)) {
		throw new \RuntimeException('Pull ClientStack before running the WordPress launcher test.');
	}
	require_once $clientStackSettings;
	require_once dirname(__DIR__) . '/src/Service/Base3WordPressLabSettings.php';
	require_once dirname(__DIR__) . '/src/Feature/GlobalChatbotLauncherFeature.php';
	require_once dirname(__DIR__, 3) . '/includes/AjaxBridge.php';
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

	$authorization = new class implements IAuthorizationService {
		public bool $allowed = true;
		public array $requests = [];
		public function getDefaultAuthority(): string { return 'wordpress'; }
		public function decide(AuthorizationRequest $request): AuthorizationDecision {
			$this->requests[] = $request;
			return new AuthorizationDecision($this->allowed);
		}
		public function decideMany(array $requests): array { return []; }
	};
	$feature = new GlobalChatbotLauncherFeature($translation);
	if ($feature::getName() !== 'globalchatbotlauncherfeature'
		|| count($feature->getPermissions()) !== 1
		|| $feature->getPermissions()[0]->permission !== $feature::getName()
		|| $feature->getPermissions()[0]->scope !== 'feature'
		|| $feature->getDefaultAudience() !== ['any' => [['group' => 'anonymous'], ['group' => 'authenticated']]]) {
		throw new \RuntimeException('Feature ownership and audience are incorrect.');
	}
	if ($feature->getLabel() === '' || $feature->getDescription() === '') {
		throw new \RuntimeException('Feature translations are missing.');
	}
	$launcher = new GlobalChatbot($link, $resolver, $translation, $store, $authorization);
	$launcher->register();
	foreach (['wp_enqueue_scripts', 'admin_enqueue_scripts', 'wp_footer', 'admin_footer',
		'wp_ajax_base3_dispatch', 'wp_ajax_nopriv_base3_dispatch'] as $hook) {
		if (count($hooks[$hook] ?? []) !== 1) throw new \RuntimeException('Missing WordPress hook: ' . $hook);
	}
	if ($hooks['wp_ajax_base3_dispatch'][0][1] !== 0 || $hooks['wp_ajax_nopriv_base3_dispatch'][0][1] !== 0) {
		throw new \RuntimeException('The AJAX access guard must run before base3_dispatch.');
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
	foreach ($authorization->requests as $request) {
		if ($request->authority !== 'wordpress'
			|| $request->permission->scope !== 'feature'
			|| $request->permission->permission !== GlobalChatbotLauncherFeature::getName()) {
			throw new \RuntimeException('Wrong authorization operation checked.');
		}
	}
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

	// The global chatbot AJAX load is gated independently of the shared chatbot outputs.
	$_GET = ['name' => 'chatbotdisplay'];
	$_REQUEST = ['base3_display_data' => $data];
	$authorization->allowed = false;
	try {
		$launcher->authorizeAjaxRender();
		throw new \RuntimeException('Denied global chatbot AJAX must terminate.');
	} catch (TestWpDie $e) {
		if ($e->status !== 403) throw new \RuntimeException('Denied global chatbot AJAX must return HTTP 403.');
	}
	$authorization->allowed = true;
	$launcher->authorizeAjaxRender();

	$authorization->allowed = false;
	ob_start();
	$launcher->render();
	$deniedMarkup = (string) ob_get_clean();
	if ($deniedMarkup !== '') throw new \RuntimeException('Denied global launcher still renders.');
	$scripts = [];
	$styles = [];
	$launcher->enqueueAssets();
	if ($scripts !== [] || $styles !== []) throw new \RuntimeException('Denied global launcher still loads assets.');

	$unrelated = rtrim(strtr(base64_encode(json_encode([
		'config_group' => 'content', 'config_name' => 'support'
	], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
	$_REQUEST['base3_display_data'] = $unrelated;
	$launcher->authorizeAjaxRender();
	// This target may be rendered through BlockBridge/shortcodes without the launcher permission.
	$_GET['name'] = 'chatbotconversationstate';
	$_REQUEST['base3_display_data'] = $data;
	$launcher->authorizeAjaxRender();
	$_GET['name'] = 'chatbotdisplay';
	$_REQUEST['base3_display_data'] = 'not-base64%';
	$launcher->authorizeAjaxRender(); // The canonical dispatcher rejects malformed payloads with 400.
	if (\Base3WordPressBridge\AjaxBridge::decodeDisplayData('not-base64%')['valid']) {
		throw new \RuntimeException('Invalid display data unexpectedly decoded.');
	}
	unset($_GET['name'], $_REQUEST['base3_display_data']);

	$administration = (new Base3WordPressLabSettings($translation, $link, new class implements \Base3\Usermanager\Api\IUsermanager {
		public function getGroups(): array { return []; }
	}))->getAdministrationConfig();
	$chatbotTabs = [];
	foreach ($administration as $tab) {
		if ($tab['name'] === 'chatbot') $chatbotTabs = $tab['displays'];
	}
	if (array_column($chatbotTabs, 'name') !== [
		'chatbotconfigdisplay', 'globalchatbotlauncherconfigdisplay'
	]) throw new \RuntimeException('Chatbot administration tab order changed.');
	$configData = $chatbotTabs[0]['data'];
	if (($configData['group'] ?? '') !== $identity['config_group']
		|| ($configData['name'] ?? '') !== $identity['config_name']
		|| count($configData['resources']['endpoints'] ?? []) !== 13) {
		throw new \RuntimeException('The chatbot administration or FileManager configuration was changed.');
	}

	echo "GlobalChatbotSmokeTest OK: ClientStack launcher, feature permission, HTTP 403 guard, unrelated displays, tab ordering, 13 file endpoints.\n";
}
