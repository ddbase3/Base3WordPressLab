<?php declare(strict_types=1);

namespace Base3WordPressLab\Feature;

use Base3\Authorization\Api\IFeature;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Permission;

/** Declares access only to the WordPress project's floating global chatbot launcher. */
final class GlobalChatbotLauncherFeature implements IFeature {

	public function __construct(private readonly ITranslation $translation) {}

	public static function getName(): string {
		return 'globalchatbotlauncherfeature';
	}

	public function getLabel(): string {
		return $this->translation->translate(
			'Administration',
			'feature',
			'label_base3wordpresslab_global_chatbot_launcher',
			'Global chatbot launcher'
		);
	}

	public function getDescription(): string {
		return $this->translation->translate(
			'Administration',
			'feature',
			'description_base3wordpresslab_global_chatbot_launcher',
			'Use the floating global chatbot button and load its chatbot display.'
		);
	}

	public function getPermissions(): array {
		return [Permission::for('feature', self::getName())];
	}

	public function getDefaultAudience(): array {
		return ['any' => [['group' => 'anonymous'], ['group' => 'authenticated']]];
	}
}
