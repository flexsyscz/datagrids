<?php

declare(strict_types=1);

namespace Flexsyscz\Datagrids\Core;

use Nette\Utils\Paginator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


/**
 * @template E of IEntity
 */
final class Table
{
	/** @var Column[] */
	private array $columns = [];

	/** @var ICollection<E> */
	private ICollection $collection;
	private Paginator $paginator;

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
	 * @return Table<E>
	 */
	public function addColumn(string $name, bool $virtual = false): self
	{
		$this->columns[] = new Column($name, $virtual);
		return $this;
	}


	/**
	 * @return Column[]
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
}
