<?php declare(strict_types=1);

namespace Base3WordPressLab\Hook;

use Base3\Api\IContainer;
use Base3\Api\IModuleRegistry;
use Base3\Hook\Api\IHookListener;
use Base3\Settings\Api\ISettingsStore;

/** Seeds project messaging defaults after schema migrations, without replacing edited settings. */
final class MessagingDefaultsHookListener implements IHookListener {

	public function __construct(
		private readonly IContainer $container,
		private readonly IModuleRegistry $modules
	) {}

	public static function getSubscribedHooks(): array {
		return ['bootstrap.migrated' => 0];
	}

	public function isActive(): bool {
		return $this->modules->getModulePath('MessageHub') !== null;
	}

	public function handle(string $hookName, ...$args) {
		if ($hookName !== 'bootstrap.migrated') return null;

		$settings = $this->container->get(ISettingsStore::class);
		if (!$settings->has('messaging', 'default')) {
			$settings->set('messaging', 'default', [
				'enabled' => true,
				'default_transport' => 'log',
				'retention_days' => 365
			]);
		}
		if (!$settings->has('messaging_transports', 'log')) {
			$settings->set('messaging_transports', 'log', ['enabled' => true]);
		}
		$settings->save();
		return null;
	}
}
