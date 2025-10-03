<?php

declare(strict_types=1);

namespace Flexsyscz\Datagrids\Core;

use Flexsyscz\Database\Exceptions\ColumnAlreadyRegistered;
use Nette\Utils\Paginator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


/**
 * @template E of IEntity
 */
final class Table
{
	/** @var Column<E>[] */
	private array $columns = [];

	/** @var ICollection<E> */
	private ICollection $collection;
	private Paginator $paginator;

	/** @var array<callable> */
	private array $rowFormatters = [];

	/** @var array<callable> */
	private array $cellFormatters = [];

	/** @var int[] */
	private array $itemsPerPagePresets;


	/**
	 * @param ICollection<E> $collection
	 * @param Paginator $paginator
	 */
	public function __construct(ICollection $collection, Paginator $paginator)
	{
		$this->collection = $collection;
		$this->paginator = $paginator;

		$this->itemsPerPagePresets = [25, 50, 100, 200, 500];
		if (!in_array($paginator->getItemsPerPage(), $this->itemsPerPagePresets, true)) {
			$this->itemsPerPagePresets[] = $paginator->getItemsPerPage();
			sort($this->itemsPerPagePresets);
		}
	}


	/**
	 * @return ICollection<E>
	 */
	public function getCollection(): ICollection
	{
		return $this->collection;
	}


	/**
	 * @return string[]
	 */
	public function getLayout(): array
	{
		return [
			'table' => 'table table-bordered table-hover',
			'thead' => 'table-secondary',
			'filter' => 'table-light',
			'tfoot' => 'table-warning',
		];
	}


	/**
	 * @param string $by
	 * @param string $order
	 * @return Table<E>
	 */
	public function sort(string $by, string $order = ICollection::ASC): self
	{
		$column = $this->getColumns()[$by] ?? null;
		if ($column) {
			$this->collection = $column->sort($this->collection, $order);
		}

		return $this;
	}


	/**
	 * @return IEntity[]
	 */
	public function getRows(): array
	{
		return $this->collection->limitBy($this->paginator->getItemsPerPage(), $this->paginator->getOffset())
			->fetchAll();
	}


	/**
	 * @param string $name
	 * @param bool $virtual
	 * @param callable|null $renderer
	 * @return Table<E>
	 */
	public function addColumn(string $name, bool $virtual = false, ?callable $renderer = null): self
	{
		if (isset($this->columns[$name])) {
			throw new ColumnAlreadyRegistered();
		}

		/** @var Column<E> $column */
		$column = new Column($name, $virtual);
		if ($renderer !== null) {
			$column->setRenderer($renderer);
		}

		$this->columns[$name] = $column;
		return $this;
	}


	/**
	 * @return Column<E>[]
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}


	/**
	 * @return int[]
	 */
	public function getItemsPerPagePresets(): array
	{
		return $this->itemsPerPagePresets;
	}


	/**
	 * @param callable $callback
	 * @return Table<E>
	 */
	public function addRowFormatter(callable $callback): self
	{
		$this->rowFormatters[] = $callback;

		return $this;
	}


	/**
	 * @param callable $callback
	 * @return Table<E>
	 */
	public function addCellFormatter(callable $callback): self
	{
		$this->cellFormatters[] = $callback;

		return $this;
	}


	public function formatRow(IEntity $row): string
	{
		$stack = [];
		foreach ($this->rowFormatters as $formatter) {
			$stack[] = call_user_func($formatter, $row);
		}

		return implode(' ', $stack);
	}


	public function formatCell(string $name, IEntity $row): string
	{
		$stack = [];
		foreach ($this->cellFormatters as $formatter) {
			$stack[] = call_user_func($formatter, $name, $row);
		}

		return implode(' ', $stack);
	}
}
