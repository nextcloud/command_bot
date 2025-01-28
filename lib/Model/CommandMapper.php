<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\CommandBot\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Command mapRowToEntity(array $row)
 * @method Command findEntity(IQueryBuilder $query)
 * @method list<Command> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Command>
 */
class CommandMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tcb_commands', Command::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getCommandForConversation(string $token, string $command): Command {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('token', $query->createNamedParameter($token)))
			->andWhere($query->expr()->eq('command', $query->createNamedParameter($command)));
		return $this->findEntity($query);
	}

	/**
	 * @return list<Command>
	 */
	public function getCommandsForConversation(string $token): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));
		return $this->findEntities($query);
	}

	public function increaseCount(Command $command): Command {
		$query = $this->db->getQueryBuilder();

		$query->update($this->tableName)
			->set('count', $query->func()->add('count', $query->expr()->literal(1)))
			->where($query->expr()->eq('id', $query->createNamedParameter($command->getId())));
		$query->executeStatement();

		$command->setCount($command->getCount() + 1);

		return $command;
	}
}
