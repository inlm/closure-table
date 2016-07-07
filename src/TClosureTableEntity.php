<?php

	namespace Inlm\ClosureTable;

	use LeanMapper\Entity;
	use LeanMapper\Filtering;
	use LeanMapper\Fluent;


	trait TClosureTableEntity
	{
		public function getClosureTableMapping()
		{
			return array(
				'parent' => 'parent_id',
				'depth' => 'depth',
				'ancestor' => 'ancestor_id',
				'descendant' => 'descendant_id',
			);
		}


		public function getDepth()
		{
			$mapping = $this->getClosureTableMapping();
			$depthField = $mapping['depth'];

			if (isset($this->row->$depthField)) {
				return $this->row->$depthField;
			}
			return 0;
		}


		public function getDescendants()
		{
			$mapping = $this->getClosureTableMapping();
			return $this->getClosureNodesInTree($mapping['descendant']);
		}


		public function getAncestors()
		{
			$mapping = $this->getClosureTableMapping();
			return $this->getClosureNodesInTree($mapping['ancestor']);
		}


		public function getParents()
		{
			// return $this->getAncestors();
			$mapping = $this->getClosureTableMapping();
			$table = $this->mapper->getTable(get_class($this));
			$primaryKey = $this->mapper->getPrimaryKey($table);

			$filtering = new Filtering(function (Fluent $fluent, Entity $entity, $ancestorField, $primaryKey) {
				$fluent->where('%n != ?', $ancestorField, $entity->{$primaryKey})
					->orderBy('[depth] DESC');
			}, array($this, $mapping['ancestor'], $primaryKey));

			return $this->getClosureNodesInTree($mapping['ancestor'], $filtering);
		}


		public function getChildren()
		{
			return $this->getChildrenEntities();
		}


		protected function getChildrenEntities(array $orderBy = NULL)
		{
			$mapping = $this->getClosureTableMapping();
			$parentField = $mapping['parent'];
			$table = $this->mapper->getTable(get_class($this));
			$primaryKey = $this->mapper->getPrimaryKey($table);
			$entities = array();
			$filtering = NULL;

			if (!empty($orderBy)) {
				$filtering = new Filtering(function (Fluent $fluent, $table, array $orderBy) {
					foreach ($orderBy as $field => $ordering) {
						if (!is_string($field)) {
							$fluent->orderBy('%n.%n', $table, $ordering);
						} else {
							$fluent->orderBy('%n.%n', $table, $field);

							if (is_string($ordering)) {
								$ordering = strtolower($ordering);
								if ($ordering === 'asc') {
									$fluent->asc();

								} elseif ($ordering = 'desc') {
									$fluent->desc();
								}
							} else {
								throw new \RuntimeException("Unknow ordering '$ordering' for field '$field'"); // TODO
							}
						}
					}
				}, array($table, $orderBy));
			}

			$rows = $this->row->referencing($table, $parentField, $filtering);

			foreach ($rows as $row) {
				$child = new static($row);
				$child->makeAlive($this->entityFactory);
				$entities[$row->$primaryKey] = $child;
			}

			return $entities;
			// where parent_id = entity.primary, orderBy ??? [name], [desc]
		}


		protected function getClosureNodesInTree($direction, Filtering $filtering = NULL)
		{
			$entities = array();
			$mapping = $this->getClosureTableMapping();
			$closureDirection = $direction === $mapping['descendant'] ? $mapping['ancestor'] : $mapping['descendant'];
			$table = $this->mapper->getTable(get_class($this));
			$primaryKey = $this->mapper->getPrimaryKey($table);
			$rows = $this->row->referencing($table . '_closure', $closureDirection, $filtering);

			foreach ($rows as $row) {
				$entityRow = $row->referenced($table, $direction);
				$entityRow->depth = $row->depth;
				$entity = new static($entityRow);
				$entity->makeAlive($this->entityFactory);
				$entities[$entityRow->$primaryKey] = $entity;
			}
			return $entities;
		}
	}
