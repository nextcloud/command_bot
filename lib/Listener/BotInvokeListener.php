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

/**
 * @template-implements IEventListener<Event>
 */
class BotInvokeListener implements IEventListener {
	public function __construct(
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BotInvokeEvent) {
			return;
		}

		if ($event->getBotUrl() !== Application::APP_ID) {
			return;
		}

		$chatMessage = $event->getMessage();
		if (!isset($chatMessage['actor']['talkParticipantType'])) {
			// IF NOT MODERATOR, DO NOT ALLOW "!set …"
		}

		$event->addReaction('👋');
		$event->addAnswer('hello', true);
		$event->addAnswer('hello again');
	}
}
