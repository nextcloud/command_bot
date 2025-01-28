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
				$object = $this->mapper->getCommandForConversation($chatMessage['target']['id'], $command);
			} catch (DoesNotExistException) {
				$object = new Command();
				$object->setToken($chatMessage['target']['id']);
				$object->setCommand($command);
			}
			$object->setMessage($message);
			$this->mapper->insertOrUpdate($object);
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
		if (str_contains($string, '{mention}')) {
			$mention = $this->getFirstMentionId($content['parameters']);
			if ($mention === null) {
				return;
			}

			$searches[] = '{mention}';
			$replacements[] = $mention;
		}

		if (str_contains($string, '{text}')) {
			$searches[] = '{text}';
			$replacements[] = $this->getText($message, $content['parameters']);
		}

		if (str_contains($string, '{sender}')) {
			$searches[] = '{sender}';
			$replacements[] = $this->getSender($chatMessage['actor']);
		}

		if (str_contains($string, '{count}')) {
			$this->mapper->increaseCount($object);
			$searches[] = '{count}';
			$replacements[] = (string)$object->getCount();
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

	protected function getFirstMentionId(array $parameters): ?string {
		foreach ($parameters as $parameter) {
			$replace = $this->getMentionReplacement($parameter);
			if ($replace !== null) {
				return $replace;
			}
		}

		return null;
	}

	protected function getText(string $message, array $parameters): ?string {
		$search = $replacements = [];
		foreach ($parameters as $key => $parameter) {
			$replace = $this->getMentionReplacement($parameter);
			$search[] = '{' . $key . '}';
			$replacements[] = $replace ?? $parameter['name'];
		}

		return str_replace($search, $replacements, $message);
	}

	protected function getMentionReplacement(array $parameter): ?string {
		return match($parameter['type']) {
			'call' => '@all',
			'user' => isset($parameter['server']) ? '@"federated_user/' . $parameter['id'] . '@' . $parameter['server'] . '"' : '@"' . $parameter['id'] . '"',
			'user-group' => '@"group/' . $parameter['id'] . '"',
			'guest' => '@"guest/' . $parameter['id'] . '"',
			default => null,
		};
	}
}
