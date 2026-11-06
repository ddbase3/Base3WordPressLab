<?php declare(strict_types=1);

namespace Base3WordPressLab\Integration;

use Base3\Api\IAssetResolver;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Permission;
use Base3WordPressBridge\AjaxBridge;
use Base3WordPressLab\Service\WordPressChatbotDisplayConfiguration;
use ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherBootstrap;
use ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherFeature;
use Throwable;

/** Connects canonical ClientStack launcher to WordPress hooks and authorization. */
final class GlobalChatbot {

	public function __construct(
		private readonly ILinkTargetService $linkTargetService,
		private readonly IAssetResolver $assetResolver,
		private readonly ITranslation $translation,
		private readonly IAuthorizationService $authorization,
		private readonly GlobalChatbotLauncherBootstrap $launcher
	) {}

	public function register(): void {
		add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
		add_action('wp_footer', [$this, 'render'], 10);
		add_action('admin_footer', [$this, 'render'], 10);
		add_action('wp_ajax_base3_dispatch', [$this, 'authorizeAjaxRender'], 0);
		add_action('wp_ajax_nopriv_base3_dispatch', [$this, 'authorizeAjaxRender'], 0);
	}

	public function enqueueAssets(): void {
		if (!$this->isAllowed()) return;
		wp_enqueue_script(
			'base3-clientstack-assetloader',
			$this->assetResolver->resolve('plugin/ClientStack/assets/assetloader/assetloader.min.js'),
			[], null, false
		);
	}

	public function render(): void {
		$identity = [
			'config_group' => WordPressChatbotDisplayConfiguration::GLOBAL_CHATBOT_GROUP,
			'config_name' => WordPressChatbotDisplayConfiguration::GLOBAL_CHATBOT_NAME
		];
		$data = rtrim(strtr(base64_encode(json_encode($identity, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
		$url = $this->linkTargetService->getLink(
			['name' => 'chatbotdisplay', 'out' => 'html'],
			['base3_display_data' => $data]
		);
		echo $this->launcher->renderAuthorized(
			$this->authorization,
			$url,
			$identity['config_group'],
			$identity['config_name'],
			[
				'dock_target' => 'body',
				'dock_header_target' => '#wpadminbar',
				'header_title' => $this->translation->translate('Administration', 'administration', 'global_chatbot_header_title')
			]
		);
	}

	/** Protects only the global launcher identity, not independently embedded chatbots. */
	public function authorizeAjaxRender(): void {
		$name = $_GET['name'] ?? null;
		if (!is_string($name) || strtolower($name) !== 'chatbotdisplay') return;
		$encoded = $_REQUEST['base3_display_data'] ?? null;
		if (!is_string($encoded)) return;
		$decoded = AjaxBridge::decodeDisplayData((string) wp_unslash($encoded));
		if (!$decoded['valid'] || !is_array($decoded['value'])) return;
		$data = $decoded['value'];
		if (($data['config_group'] ?? null) !== WordPressChatbotDisplayConfiguration::GLOBAL_CHATBOT_GROUP
			|| ($data['config_name'] ?? null) !== WordPressChatbotDisplayConfiguration::GLOBAL_CHATBOT_NAME) return;
		if (!$this->isAllowed()) {
			wp_die($this->translation->translate('Administration', 'administration', 'global_chatbot_access_denied'), '', ['response' => 403]);
		}
	}

	private function isAllowed(): bool {
		try {
			return $this->authorization->decide(new AuthorizationRequest(
				$this->authorization->getDefaultAuthority(),
				Permission::for('feature', GlobalChatbotLauncherFeature::getName())
			))->isAllowed();
		} catch (Throwable) {
			return false;
		}
	}
}
