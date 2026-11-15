<?php declare(strict_types=1);

namespace Base3\Base3WordPressLab;

use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\AssistantFoundation\Api\IAgentRuntimeSelector;
use Base3\AssistantRuntime\Service\PreferredAgentRuntimeSelector;
use Base3\CoreFoundation\Accesscontrol\Api\IAccesscontrol;
use Base3\CoreFoundation\Api\IAssetResolver;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IModuleRegistry;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Api\IBase;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Migration\Api\IMigrationRunner;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\Core\Logger\ScopedDatabaseLogger\ScopedDatabaseLogger;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\Core\Migration\Database\DatabaseMigrationRunner;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\Core\Settings\Database\DatabaseSettingsStore;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\Core\State\Database\DatabaseStateStore;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\Base3WordPressLab\Integration\GlobalChatbot;
use Base3\Base3WordPressLab\Service\WordPressChatbotDisplayConfiguration;
use Base3\ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherBootstrap;
use Base3\MissionBay\Agent\GlobalAgentKnowledgeService;
use Base3\MissionBay\Api\IAgentKnowledgeService;

/** Example project composition, ported from the ILIAS lab responsibility. */
final class Base3WordPressLabPlugin implements IPlugin, IBase {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string { return 'base3wordpresslabplugin'; }

	public function init(): void {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED)
			->set(ISettingsStore::class, fn($c) => new DatabaseSettingsStore($c->get(IDatabase::class)), IContainer::SHARED)
			->set(IStateStore::class, fn($c) => new DatabaseStateStore($c->get(IDatabase::class)), IContainer::SHARED)
			->set(IMigrationRunner::class, fn($c) => new DatabaseMigrationRunner($c->get(IClassMap::class), $c->get(IDatabase::class)), IContainer::SHARED)
			->set(ILogger::class, fn($c) => new ScopedDatabaseLogger($c->get(IDatabase::class)), IContainer::SHARED)
			->set('logger', ILogger::class, IContainer::ALIAS)
			->set('wordpressFileManagerOwner', WordPressChatbotDisplayConfiguration::storageOwner(), IContainer::PARAMETER)
			->set('chatbotConfigDisplayData', fn($c) => WordPressChatbotDisplayConfiguration::displayData(
				$c->get(ILinkTargetService::class), $c->get(ITranslation::class)
			), IContainer::SHARED)
			->set(GlobalChatbot::class, fn($c) => new GlobalChatbot(
				$c->get(ILinkTargetService::class),
				$c->get(IAssetResolver::class),
				$c->get(ITranslation::class),
				$c->get(IAuthorizationService::class),
				$c->get(GlobalChatbotLauncherBootstrap::class)
			), IContainer::SHARED);

		if (!(defined('WP_CLI') && WP_CLI)) {
			// WordPress init(20) composes all plugins. Resolve runtime services only
			// after that phase, before page assets and AJAX handlers are executed.
			add_action('init', function(): void {
				if ($this->container->get(IAccesscontrol::class)->getUserId() > 0
					&& wp_get_session_token() !== '') {
					$this->container->get(ISession::class)->start();
				}
			}, 25);
			add_action('init', function(): void {
				$this->container->get(GlobalChatbot::class)->register();
			}, 30);
		}

		// MissionBay is an optional BASE3 module. Only compose its services when installed.
		if ($this->container->get(IModuleRegistry::class)->getModulePath('MissionBay') !== null) {
			$this->container
				->set(IAgentRuntimeSelector::class, fn($c) => new PreferredAgentRuntimeSelector(
					$c->get(IAgentRuntimeRegistry::class),
					'missionbay'
				), IContainer::SHARED)
				->set(IAgentKnowledgeService::class, fn($c) => new GlobalAgentKnowledgeService(
					$c->get(IDatabase::class),
					$c->get(IAccesscontrol::class),
					$c->get(ISession::class)
				), IContainer::SHARED);
		}

		// Keep the ILIAS lab behavior: user removal invalidates assistant conversation history.
		add_action('deleted_user', function(int $userId): void {
			if (!interface_exists(\Base3\AssistantFoundation\Api\IAgentConversationMemory::class)) return;
			$ownerKey = \Base3\AssistantFoundation\Dto\AgentConversationScope::ownerKeyForUser($userId);
			$map = $this->container->get(IClassMap::class);
			foreach ($map->getInstancesByInterface(\Base3\AssistantFoundation\Api\IAgentConversationMemory::class) as $memory) {
				$memory->deleteConversationsByOwnerKey($ownerKey);
			}
		});
	}
}
