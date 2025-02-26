<?php

declare(strict_types = 1);

namespace Flexsyscz\Datagrids\DatagridControl;

use Nette\InvalidArgumentException;
use Nette\Utils\Callback;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


trait Handlers
{
	public function handleSort(string $by): void
	{
		$this->sortBy = $by;
		$this->order = $this->order === ICollection::ASC ? ICollection::DESC : ICollection::ASC;
	}


	public function handleToggleRow(int|string $id): void
	{
		$selected = false;
		$entity = $this->getTable()->getCollection()->getById($id);
		if ($entity instanceof IEntity) {
			$presenter = $this->getPresenter();
			$state = $presenter->getHttpRequest()->getPost('state') === 'true';

			$selectionStorage = $this->getSession(self::$selectionStoragePrefix);
			$ids = $selectionStorage->get('ids');
			if (!is_array($ids)) {
				$ids = [];
			}

			if ($state) {
				$ids[$id] = $id;
				$selected = true;
			} else {
				unset($ids[$id]);
			}

			$selectionStorage->set('ids', $ids);
		}

		$this->getPresenter()->sendJson([
			'selected' => $selected,
		]);
	}


	public function handleToggleAllRows(): void
	{
		$presenter = $this->getPresenter();
		$state = $presenter->getHttpRequest()->getPost('state') === 'true';

		$selectionStorage = $this->getSession(self::$selectionStoragePrefix);
		if ($state) {
			$ids = $this->getTable()->getCollection()->fetchPairs('id', 'id');
		} else {
			$ids = [];
		}

		$selectionStorage->set('ids', $ids);

		$this->getPresenter()->sendJson([
			'count' => count($ids),
		]);
	}


	public function handleProcessSelection(): void
	{
		$session = $this->getSession(self::$selectionStoragePrefix);
		$ids = $session->get('ids');
		$rows = $this->getTable()->getCollection()->findBy(['id' => $ids])->fetchAll();

		foreach ($this->onSelect as $callback) {
			try {
				$callback = Callback::check($callback);
				call_user_func($callback, $rows);
			} catch (InvalidArgumentException $e) {
				$this->flashError($e->getMessage());
			}
		}
	}
}
