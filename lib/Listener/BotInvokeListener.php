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

		$isModerator = in_array((int)$chatMessage['actor']['talkParticipantType'], [
			self::PARTICIPANT_TYPE_OWNER,
			self::PARTICIPANT_TYPE_MODERATOR,
		], true);

		$content = json_decode($chatMessage['object']['content'], true);
		if ($content['message'][0] !== '!' && $content['message'][0] !== '?') {
			return;
		}

		[$command, $message] = explode(' ', $content['message'] . ' ', 2);
		if ($command === '!set') {
			if (!$isModerator) {
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
			if ($object->getId() !== null) {
				$this->mapper->update($object);
			} else {
				$this->mapper->insert($object);
			}
			$event->addReaction('👍');
			return;
		}
		if ($command === '!unset') {
			if (!$isModerator) {
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

		if ($command === '!command' || $command === '!commands') {
			$commands = $this->mapper->getCommandsForConversation($chatMessage['target']['id']);
			if (!$isModerator && empty($commands)) {
				$event->addAnswer('*No commands configured*', true);
				return;
			}
			$response = '### 💬 Available commands' . "\n";
			$response .= '- **!command** - List all commands' . "\n";
			foreach ($commands as $command) {
				$response .= '- **' . $command->getCommand() . '** - ';
				$response .= $this->highlightParameters($command->getMessage());
				if ($command->getCount()) {
					$response .= ' - *Current count: ' . $command->getCount() . '*';
				}
				$response .= "\n";
			}
			if ($isModerator) {
				$response .= "\n";
				$response .= '---';
				$response .= "\n";
				$response .= '### ⭐ Moderators' . "\n";
				$response .= '- **!set** - Create or update a command' . "\n";
				$response .= '  ```' . "\n";
				$response .= '  !set !counter The counter was used {count} times' . "\n";
				$response .= '  ```' . "\n";
				$response .= '- **!unset** - Remove a command' . "\n";
				$response .= '  ```' . "\n";
				$response .= '  !unset !counter' . "\n";
				$response .= '  ```' . "\n";
				$response .= "\n";
				$response .= '---';
				$response .= "\n";
				$response .= '### 💱 Placeholders' . "\n";
				$response .= '- **{sender}** - Replaced with a mention of the sender' . "\n";
				$response .= '- **{mention}** - Replaced with the first mention in the command' . "\n";
				$response .= '- **{text}** - All text that was provided after the command' . "\n";
				$response .= '- **{count}** - A counter how often the command was triggered already' . "\n";
			}
			$event->addAnswer($response);
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
			if (isset($chatMessage['object']['inReplyTo']['actor']['id'])
				&& !str_starts_with($chatMessage['object']['inReplyTo']['actor']['id'], 'bot/')) {
				$answer = $this->getSender($chatMessage['object']['inReplyTo']['actor']) . ' ' . $answer;
				$event->addAnswer($answer, (int)$chatMessage['object']['inReplyTo']['object']['id']);
			} else {
				$event->addAnswer($answer);
			}
		}
	}

	protected function highlightParameters(string $message): string {
		return str_replace(
			['{count}', '{mention}', '{sender}', '{text}'],
			['*{count}*', '*{mention}*', '*{sender}*', '*{text}*'],
			$message
		);
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
