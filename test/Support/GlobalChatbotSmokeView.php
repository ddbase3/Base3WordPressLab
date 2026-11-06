<?php declare(strict_types=1);

namespace Base3WordPressLab\Test\Support;

use Base3\Api\IMvcView;

/** Loads the real ClientStack launcher language bricks in an isolated smoke test. */
final class GlobalChatbotSmokeView implements IMvcView {

	private string $path = '';
	private array $bricks = [];

	public function setPath(string $path = '.') { $this->path = $path; }
	public function assign(string $key, $value) {}
	public function setTemplate(string $template = 'default') {}
	public function loadTemplate(): string { return ''; }
	public function loadBricks(string $set, string $language = '') {
		$path = $this->path . '/lang/' . $set . '/en.ini';
		$this->bricks = parse_ini_file($path, true, INI_SCANNER_RAW) ?: [];
	}
	public function getBricks(string $set): ?array { return $this->bricks[$set] ?? null; }
}
