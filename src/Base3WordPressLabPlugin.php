<?php declare(strict_types=1);

namespace Base3WordPressLab;

use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantRuntime\Service\PreferredAgentRuntimeSelector;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IModuleRegistry;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IBase;
use Base3\Database\Api\IDatabase;
use Base3\Migration\Api\IMigrationRunner;
use Base3\Logger\Api\ILogger;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\ScopedDatabaseLogger\ScopedDatabaseLogger;
use Base3\Session\Api\ISession;
use Base3\Migration\Database\DatabaseMigrationRunner;
use Base3\Settings\Api\ISettingsStore;
use Base3\Settings\Database\DatabaseSettingsStore;
use Base3\State\Api\IStateStore;
use Base3\State\Database\DatabaseStateStore;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Api\IUsermanager;
use Base3WordPress\Api\IBase3WordPressSettings;
use Base3WordPressLab\Integration\GlobalChatbot;
use Base3WordPressLab\Service\Base3WordPressLabSettings;
use MissionBay\Agent\GlobalAgentKnowledgeService;
use MissionBay\Api\IAgentKnowledgeService;

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
			->set(IBase3WordPressSettings::class, fn($c) => new Base3WordPressLabSettings($c->get(ITranslation::class), $c->get(ILinkTargetService::class), $c->get(IUsermanager::class)), IContainer::SHARED)
			->set(GlobalChatbot::class, fn($c) => new GlobalChatbot(
				$c->get(ILinkTargetService::class),
				$c->get(IAssetResolver::class),
				$c->get(ITranslation::class),
				$c->get(ISettingsStore::class)
			), IContainer::SHARED);

		if (!(defined('WP_CLI') && WP_CLI)) {
			$this->container->get(GlobalChatbot::class)->register();
		}

		// Keep native PHP sessions available before WordPress sends output. The
		// host adapter binds their BASE3 state to the validated WP login token.
		if (!(defined('WP_CLI') && WP_CLI)
			&& $this->container->get(IAccesscontrol::class)->getUserId() > 0
			&& wp_get_session_token() !== '') {
			$this->container->get(ISession::class)->start();
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
			if (!interface_exists(\AssistantFoundation\Api\IAgentConversationMemory::class)) return;
			$ownerKey = \AssistantFoundation\Dto\AgentConversationScope::ownerKeyForUser($userId);
			$map = $this->container->get(IClassMap::class);
			foreach ($map->getInstancesByInterface(\AssistantFoundation\Api\IAgentConversationMemory::class) as $memory) {
				$memory->deleteConversationsByOwnerKey($ownerKey);
			}
		});
	}
}
