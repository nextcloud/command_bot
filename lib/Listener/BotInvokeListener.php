<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\CommandBot\Listener;

use OCA\CommandBot\AppInfo\Application;
use OCA\Talk\Events\BotInvokeEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class BotInvokeListener implements IEventListener {
	public const PARTICIPANT_TYPE_OWNER = 1;
	public const PARTICIPANT_TYPE_MODERATOR = 2;

	public function __construct(
		protected LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BotInvokeEvent) {
			return;
		}

		if ($event->getBotUrl() !== 'nextcloudapp://' . Application::APP_ID) {
			return;
		}

		$chatMessage = $event->getMessage();
		if ($chatMessage['type'] !== 'Create') {
			$this->logger->debug('Not an even from creating a chat message: ' . $chatMessage['type']);
			return;
		}

		if (!isset($chatMessage['actor']['talkParticipantType'])) {
			$this->logger->debug('Missing participant type in data');
			return;
		}

		$content = json_decode($chatMessage['object']['content'], true);
		if (str_starts_with($content['message'], '!set')
			 && !in_array((int)$chatMessage['actor']['talkParticipantType'], [
				self::PARTICIPANT_TYPE_OWNER,
				self::PARTICIPANT_TYPE_MODERATOR,
			], true)) {
			// IF NOT MODERATOR, DO NOT ALLOW "!set …"
			$this->logger->debug('Can not use !set unless being a moderator');
			return;
		}

		if (str_starts_with($content['message'], '!set')) {
			$event->addReaction('👍');
		}
	}
}
