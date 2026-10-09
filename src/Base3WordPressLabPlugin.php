<?php declare(strict_types=1);

namespace Base3WordPressLab;

use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IBase;
use Base3\Database\Api\IDatabase;
use Base3\Migration\Api\IMigrationRunner;
use Base3\Migration\Database\DatabaseMigrationRunner;
use Base3\Settings\Api\ISettingsStore;
use Base3\Settings\Database\DatabaseSettingsStore;
use Base3\State\Api\IStateStore;
use Base3\State\Database\DatabaseStateStore;
use Base3\Translation\Api\ITranslation;
use Base3WordPress\Api\IBase3WordPressSettings;
use Base3WordPressLab\Service\Base3WordPressLabSettings;

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
			->set(IBase3WordPressSettings::class, fn($c) => new Base3WordPressLabSettings($c->get(ITranslation::class)), IContainer::SHARED);

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
