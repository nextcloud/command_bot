<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\CommandBot\Migration;

use OCA\CommandBot\AppInfo\Application;
use OCA\Talk\Events\BotInstallEvent;
use OCA\Talk\Model\Bot;
use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Security\ISecureRandom;

class InstallBot implements IRepairStep {
	public function __construct(
		protected IEventDispatcher $dispatcher,
		protected ISecureRandom $secureRandom,
		protected IAppConfig $appConfig,
	) {
	}

	public function getName(): string {
		return 'Install as Talk bot';
	}

	public function run(IOutput $output): void {
		if (!class_exists(BotInstallEvent::class)) {
			$output->warning('Talk not found, not installing bots');
			return;
		}

		$secret = $this->appConfig->getAppValueString('secret');
		if ($secret === '') {
			$secret = $this->secureRandom->generate(128);
			$this->appConfig->setAppValueString('secret', $secret, sensitive: true);
		}

		$event = new BotInstallEvent(
			'Command',
			$secret,
			'nextcloudapp://' . Application::APP_ID,
			'Send a chat message "!command" to learn which commands and placeholders are available',
			Bot::FEATURE_EVENT
		);
		$this->dispatcher->dispatchTyped($event);
	}
}
