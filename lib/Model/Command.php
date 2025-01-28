<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\CommandBot\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setToken(string $token)
 * @method string getToken()
 * @method void setCommand(string $command)
 * @method string getCommand()
 * @method void setMessage(string $message)
 * @method string getMessage()
 * @method void setCount(int $count)
 * @method int getCount()
 */
class Command extends Entity {
	protected string $token = '';
	protected string $command = '';
	protected string $message = '';
	protected int $count = 0;

	public function __construct() {
		$this->addType('token', Types::STRING);
		$this->addType('command', Types::STRING);
		$this->addType('message', Types::TEXT);
		$this->addType('count', Types::BIGINT);
	}
}
