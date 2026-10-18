<?php declare(strict_types=1);

namespace Base3WordPressLab\Integration;

use Base3\Api\IAssetResolver;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use Base3\Translation\Api\ITranslation;
use Base3WordPressLab\Service\Base3WordPressLabSettings;
use ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherSettings;

/** Host-specific composition of the shared ClientStack global chatbot launcher. */
final class GlobalChatbot {

	public function __construct(
		private readonly ILinkTargetService $linkTargetService,
		private readonly IAssetResolver $assetResolver,
		private readonly ITranslation $translation,
		private readonly ISettingsStore $settingsStore
	) {}

	public function register(): void {
		add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
		add_action('wp_footer', [$this, 'render'], 10);
		add_action('admin_footer', [$this, 'render'], 10);
	}

	public function enqueueAssets(): void {
		$root = 'plugin/ClientStack/assets/';
		wp_enqueue_script(
			'base3-clientstack-assetloader',
			$this->assetResolver->resolve($root . 'assetloader/assetloader.min.js'),
			[],
			null,
			false
		);
		wp_enqueue_style(
			'base3-global-chatbot',
			$this->assetResolver->resolve($root . 'globalchatbotlauncher/styles/globalchatbotlauncher.css'),
			[],
			null
		);
	}

	public function render(): void {
		$identity = [
			'config_group' => Base3WordPressLabSettings::GLOBAL_CHATBOT_GROUP,
			'config_name' => Base3WordPressLabSettings::GLOBAL_CHATBOT_NAME
		];
		$data = rtrim(strtr(base64_encode(json_encode($identity, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
		$url = $this->linkTargetService->getLink(
			['name' => 'chatbotdisplay', 'out' => 'html'],
			['base3_display_data' => $data]
		);
		$configuration = GlobalChatbotLauncherSettings::get($this->settingsStore);
		$label = $this->t('global_chatbot_label', 'Chat');
		$attributes = [
			'data-ajax-url' => $url,
			'data-dock-target' => 'body',
			'data-dock-header-target' => '#wpadminbar',
			'data-button-label' => $label,
			'data-header-title' => $this->t('global_chatbot_header_title', $label),
			'data-config-group' => $identity['config_group'],
			'data-config-name' => $identity['config_name'],
			'data-close-label' => $this->t('global_chatbot_close', 'Close chat'),
			'data-loading-label' => $this->t('global_chatbot_loading', 'Loading chat ...'),
			'data-load-error' => $this->t('global_chatbot_load_error', 'The chatbot could not be loaded.'),
			'data-resize-label' => $this->t('global_chatbot_resize', 'Resize chat window'),
			'data-mode-overlay-label' => $this->t('global_chatbot_mode_overlay', 'Floating window'),
			'data-mode-modal-label' => $this->t('global_chatbot_mode_modal', 'Dialog'),
			'data-mode-docked-label' => $this->t('global_chatbot_mode_docked', 'Sidebar'),
			'data-mode-cover-label' => $this->t('global_chatbot_mode_cover', 'Full window'),
			'data-mode-fullscreen-label' => $this->t('global_chatbot_mode_fullscreen', 'Fullscreen')
		];
		foreach ($configuration as $name => $enabled) {
			$attributes['data-' . str_replace('_', '-', $name)] = $enabled ? '1' : '0';
		}
		$markup = '';
		foreach ($attributes as $name => $value) {
			$markup .= "\n\t" . $name . '="' . esc_attr($value) . '"';
		}
		$moduleUrl = $this->assetResolver->resolve('plugin/ClientStack/assets/globalchatbotlauncher/index.js');
		echo '<div id="base3-chatbot-config" data-base3-global-chatbot-launcher hidden' . $markup . '></div>' . "\n";
		echo '<script type="module" src="' . esc_url($moduleUrl) . '"></script>' . "\n";
	}

	private function t(string $key, string $fallback): string {
		return $this->translation->translate('Administration', 'administration', $key, $fallback);
	}
}
