<?php

	namespace Inlm\ClosureTable;

	use LeanMapper\Entity;


	trait TClosureTableRepository
	{
		/**
		 * @inheritdoc
		 */
		protected function insertIntoDatabase(Entity $entity)
		{
			try {
				$this->connection->begin();
				$id = parent::insertIntoDatabase($entity);
				$tableName = $this->getTable() . '_closure';
				$data = $entity->getRowData();
				$mapping = $entity->getClosureTableMapping();
				$parentField = $mapping['parent'];

				// vlozeni sebe sama
				$this->connection->query('INSERT INTO %n (ancestor_id, descendant_id, depth) VALUES (?, ?, 0)', $tableName, $id, $id);

				// zkopirovani struktury rodicu
				$this->connection->query(
					'INSERT INTO %n (ancestor_id, descendant_id, depth)'
					. ' SELECT ancestor_id, ?, depth+1 FROM %n'
					. ' WHERE descendant_id = ?',
					$tableName,
					$id,
					$tableName,
					isset($data[$parentField]) ? $data[$parentField] : NULL
				);
				$this->connection->commit();
				return $id;
			} catch (\Exception $e) {
				$this->connection->rollback();
				throw $e;
			}
		}


		protected function updateInDatabase(Entity $entity)
		{
			$mapping = $entity->getClosureTableMapping();
			$parentField = $mapping['parent'];

			// zmenilo se parent_id ??
			$values = $entity->getModifiedRowData();

			if (!array_key_exists($parentField, $values)) {
				return parent::updateInDatabase($entity);

			} else {
				try {
					$this->connection->begin();
					$res = parent::updateInDatabase($entity);
					$tableName = $this->getTable() . '_closure';
					$primaryKey = $this->mapper->getPrimaryKey($this->getTable());
					$idField = $this->mapper->getEntityField($this->getTable(), $primaryKey);

					// odstraneni stare struktury
					$this->connection->query(
						'DELETE a FROM %n AS a'
						. ' JOIN %n AS d ON a.descendant_id = d.descendant_id'
						. ' LEFT JOIN %n AS x'
						. ' ON x.ancestor_id = d.ancestor_id AND x.descendant_id = a.ancestor_id'
						. ' WHERE d.ancestor_id = ? AND x.ancestor_id IS NULL',
						$tableName,
						$tableName,
						$tableName,
						$entity->$idField
					);

					// vlozeni nove struktury
					if ($values[$parentField] !== NULL) {
						$this->connection->query(
							'INSERT INTO %n (ancestor_id, descendant_id, depth)'
							. ' SELECT supertree.ancestor_id, subtree.descendant_id,'
							. ' supertree.depth+subtree.depth+1'
							. ' FROM %n AS supertree JOIN %n AS subtree'
							. ' WHERE subtree.ancestor_id = ?'
							. ' AND supertree.descendant_id = ?',
							$tableName,
							$tableName,
							$tableName,
							$entity->$idField,
							$values[$parentField]
						);
					}

					$this->connection->commit();
					return $res;

				} catch (\Exception $e) {
					$this->connection->rollback();
					throw $e;
				}
			}
		}


		protected function deleteFromDatabase($arg)
		{
			try {
				$this->connection->begin();

				$primaryKey = $this->mapper->getPrimaryKey($this->getTable());
				$idField = $this->mapper->getEntityField($this->getTable(), $primaryKey);
				$id = ($arg instanceof Entity) ? $arg->$idField : $arg;

				// smazani struktury
				$tableName = $this->getTable() . '_closure';
				$this->connection->query('DELETE FROM %n WHERE descendant_id = ?', $tableName, $id);

				// odstraneni zaznamu
				$res = parent::deleteFromDatabase($arg);

				$this->connection->commit();
				return $res;

			} catch (\Exception $e) {
				$this->connection->rollback();
				throw $e;
			}
		}
	}
