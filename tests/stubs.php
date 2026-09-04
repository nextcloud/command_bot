<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model {
	class Bot {
		public const FEATURE_NONE = 0;
		public const FEATURE_WEBHOOK = 1;
		public const FEATURE_RESPONSE = 2;
		public const FEATURE_EVENT = 4;
		public const FEATURE_REACTION = 8;
	}
}

namespace OCA\Talk\Events {
	class BotInstallEvent extends \OCP\EventDispatcher\Event {
		public function __construct(
			protected string $name,
			protected string $secret,
			protected string $url,
			protected string $description = '',
			protected ?int $features = null,
		) {
			parent::__construct();
		}

		public function getName(): string {
		}

		public function getSecret(): string {
		}

		public function getUrl(): string {
		}

		public function getDescription(): string {
		}
	}

	class BotUninstallEvent extends \OCP\EventDispatcher\Event {
		public function getSecret(): string {
		}

		public function getUrl(): string {
		}
	}

	/**
	 * @psalm-type ChatMessageParentData = array{
	 *      type: 'Note',
	 *      actor: array{
	 *          type: 'Person',
	 *          id: non-empty-string,
	 *          name: non-empty-string,
	 *      },
	 *      object: array{
	 *          type: 'Note',
	 *          id: numeric-string,
	 *          name: string,
	 *          content: non-empty-string,
	 *          mediaType: 'text/markdown'|'text/plain',
	 *      },
	 *  }
	 * @psalm-type ChatMessageData = array{
	 *      type: 'Activity'|'Create',
	 *      actor: array{
	 *          type: 'Person',
	 *          id: non-empty-string,
	 *          name: non-empty-string,
	 *          talkParticipantType: numeric-string,
	 *      },
	 *      object: array{
	 *          type: 'Note',
	 *          id: numeric-string,
	 *          name: string,
	 *          content: non-empty-string,
	 *          mediaType: 'text/markdown'|'text/plain',
	 *          inReplyTo?: ChatMessageParentData,
	 *          threadId?: int,
	 *      },
	 *      target: array{
	 *          type: 'Collection',
	 *          id: non-empty-string,
	 *          name: non-empty-string,
	 *      },
	 *  }
	 * @psalm-type BotManagementData = array{
	 *      type: 'Join'|'Leave',
	 *      actor: array{
	 *          type: 'Application',
	 *          id: non-empty-string,
	 *          name: non-empty-string,
	 *      },
	 *      object: array{
	 *          type: 'Collection',
	 *          id: non-empty-string,
	 *          name: non-empty-string,
	 *      },
	 *  }
	 * @psalm-type InvocationData = ChatMessageData|BotManagementData
	 */
	class BotInvokeEvent extends \OCP\EventDispatcher\Event {
		public function getBotUrl(): string {
		}

		/**
		 * @return InvocationData
		 */
		public function getMessage(): array {
		}

		public function addReaction(string $emoji): void {
		}

		/**
		 * @return list<string>
		 */
		public function getReactions(): array {
		}

		public function addAnswer(string $message, bool|int $reply = false, bool $silent = false, string $referenceId = ''): void {
		}

		/**
		 * @return list<array{message: string, referenceId: string, reply: bool|int, silent: bool}>
		 */
		public function getAnswers(): array {
		}
	}
}
