<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\CommandBot\Listener;

use OCA\CommandBot\AppInfo\Application;
use OCA\CommandBot\Model\Command;
use OCA\CommandBot\Model\CommandMapper;
use OCA\Talk\Events\BotInvokeEvent;
use OCP\AppFramework\Db\DoesNotExistException;
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
		protected CommandMapper $mapper,
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
		if ($content['message'][0] !== '!' && $content['message'][0] !== '?') {
			return;
		}

		[$command, $message] = explode(' ', $content['message'] . ' ', 2);
		if ($command === '!set') {
			if (!in_array((int)$chatMessage['actor']['talkParticipantType'], [
					self::PARTICIPANT_TYPE_OWNER,
					self::PARTICIPANT_TYPE_MODERATOR,
				], true)) {
				// IF NOT MODERATOR, DO NOT ALLOW "!set …"
				$this->logger->debug('Can not use !set unless being a moderator');
				return;
			}

			$message = trim($message);
			if (!str_contains($message, ' ')) {
				$event->addReaction('👎');
				return;
			}

			[$command, $message] = explode(' ', $message, 2);
			$command = strtolower($command);
			if ($message === '' || strlen($command) < 2 || ($command[0] !== '!' && $command[0] !== '?')) {
				$event->addReaction('👎');
				return;
			}

			try {
				$this->mapper->getCommandForConversation($chatMessage['target']['id'], $command);
				$event->addReaction('👎');
				return;
			} catch (DoesNotExistException) {
			}
			$object = new Command();
			$object->setToken($chatMessage['target']['id']);
			$object->setCommand($command);
			$object->setMessage($message);
			$this->mapper->insert($object);
			$event->addReaction('👍');
			return;
		}
		if ($command === '!unset') {
			if (!in_array((int)$chatMessage['actor']['talkParticipantType'], [
					self::PARTICIPANT_TYPE_OWNER,
					self::PARTICIPANT_TYPE_MODERATOR,
				], true)) {
				// IF NOT MODERATOR, DO NOT ALLOW "!set …"
				$this->logger->debug('Can not use !set unless being a moderator');
				return;
			}

			$command = trim($message);
			if ($command === '') {
				$event->addReaction('👎');
				return;
			}

			try {
				$object = $this->mapper->getCommandForConversation($chatMessage['target']['id'], $command);
			} catch (DoesNotExistException) {
				$event->addReaction('👎');
				return;
			}
			$this->mapper->delete($object);
			$event->addReaction('👍');
			return;
		}

		try {
			$object = $this->mapper->getCommandForConversation($chatMessage['target']['id'], $command);
		} catch (DoesNotExistException) {
			$event->addReaction('👎');
			return;
		}

		$string = $object->getMessage();

		$searches = $replacements = [];
		if (str_contains($string, '${mention}')) {
			$searches[] = '${mention}';
			$mention = $this->getFirstMention($content['parameters']);
			if ($mention === null) {
				return;
			}
			$replacements[] = $mention;
		}

		if (str_contains($string, '${sender}')) {
			$searches[] = '${sender}';
			$replacements[] = $this->getSender($chatMessage['actor']);
		}

		$answer = str_replace(
			$searches,
			$replacements,
			$string
		);

		if ($answer !== '') {
			$event->addAnswer($answer);
		}
	}

	protected function getSender(array $actor): string {
		[$type, $id] = explode('/', $actor['id'], 2);
		$type = rtrim($type, 's');
		// TODO check with federated users and guests
		if ($type === 'user') {
			return '@"' . $id . '"';
		}
		return '@"' . $type . '/' . $id . '"';
	}

	protected function getFirstMention(array $parameters): ?string {
		foreach ($parameters as $parameter) {
			if ($parameter['type'] === 'call') {
				return '@all';
			}
			if ($parameter['type'] === 'user') {
				if (isset($parameter['server'])) {
					return '@"federated_user/' . $parameter['id'] . '@' . $parameter['server'] . '"';
				} else {
					return '@"' . $parameter['id'] . '"';
				}
			}
			if ($parameter['type'] === 'user-group') {
				return '@"group/' . $parameter['id'] . '"';
			}
			if ($parameter['type'] === 'guest') {
				return '@"guest/' . $parameter['id'] . '"';
			}
		}

		return null;
	}
}
