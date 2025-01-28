<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\CommandBot\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20250128141713 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('tcb_commands')) {
			$table = $schema->createTable('tcb_commands');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);

			$table->addColumn('token', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('command', Types::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('message', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('count', Types::BIGINT, [
				'notnull' => false,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['token', 'command'], 'tcb_commands_token');
			return $schema;
		}
		return null;
	}
}
