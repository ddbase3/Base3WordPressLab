<?php declare(strict_types=1);

namespace Base3WordPressLab\Group;

use Base3\Usermanager\Api\IGroup;
use Base3\Usermanager\Group;
use InvalidArgumentException;

/** Project-owned, code-backed initial developer membership; settings may only add members. */
final class Base3WordPressDevelopmentGroup implements IGroup {

	public static function getName(): string { return 'base3wordpressdevelopmentgroup'; }

	public function getGroup(): Group {
		return Group::fromArray(['id'=>'development','name'=>'development','info'=>'WordPress project developers and configured BASE3 members','roles'=>[]]);
	}

	/**
	 * Reads exact deployment-owned identities, e.g. from wp-config.php; no default administrator bypass.
	 * @return array<string,mixed> Fixed identity criterion, empty lists match nobody
	 * @throws InvalidArgumentException On a malformed deployment allowlist
	 */
	public function getCriteria(): array {
		$usernames = defined('BASE3_WORDPRESS_DEVELOPER_USERNAMES') ? constant('BASE3_WORDPRESS_DEVELOPER_USERNAMES') : [];
		$emails = defined('BASE3_WORDPRESS_DEVELOPER_EMAILS') ? constant('BASE3_WORDPRESS_DEVELOPER_EMAILS') : [];
		foreach ([$usernames, $emails] as $values) {
			if (!is_array($values) || !array_is_list($values)) throw new InvalidArgumentException('Developer identities require a list.');
			foreach ($values as $value) {
				if (!is_string($value) || trim($value) !== $value || $value === '') throw new InvalidArgumentException('Invalid developer identity.');
			}
		}
		return ['criterion'=>'base3useridentitycriterion','config'=>['usernames'=>$usernames,'emails'=>$emails]];
	}

	public function isEditable(): bool { return true; }
}
