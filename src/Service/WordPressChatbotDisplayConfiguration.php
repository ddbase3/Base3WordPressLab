<?php declare(strict_types=1);

namespace Base3WordPressLab\Service;

use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Translation\Api\ITranslation;
use ClientStack\FileManager\FileManagerHttpService;

/** Owns the single WordPress project's global chatbot identity and display data. */
final class WordPressChatbotDisplayConfiguration {

	public const GLOBAL_CHATBOT_GROUP = 'chatbot';
	public const GLOBAL_CHATBOT_NAME = 'default';

	/** @return array{group:string,name:string} Trusted file storage owner for the host output. */
	public static function storageOwner(): array {
		return ['group' => self::GLOBAL_CHATBOT_GROUP, 'name' => self::GLOBAL_CHATBOT_NAME];
	}

	/** @return array<string,mixed> Display data for the canonical ChatbotconfigSettingsTab. */
	public static function displayData(ILinkTargetService $links, ITranslation $translation): array {
		$baseUrl = $links->getLink(['name' => 'base3wordpressfilemanageroutput', 'out' => 'json']);
		return [
			'group' => self::GLOBAL_CHATBOT_GROUP,
			'name' => self::GLOBAL_CHATBOT_NAME,
			'title' => $translation->translate('Administration', 'administration', 'chatbot_config_title'),
			'description' => $translation->translate('Administration', 'administration', 'wordpress_chatbot_config_description'),
			'submit_label' => $translation->translate('Administration', 'administration', 'save'),
			'mode' => 'standalone',
			'save_mode' => 'ajax',
			'resources' => [
				'enabled' => true,
				'endpoints' => FileManagerHttpService::buildEndpoints($baseUrl),
				'max_file_size' => FileManagerHttpService::DEFAULT_MAX_FILE_SIZE,
				'accept' => 'application/pdf,.pdf'
			]
		];
	}
}
