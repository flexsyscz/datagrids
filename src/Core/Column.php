<?php

declare(strict_types=1);

namespace Flexsyscz\Datagrids\Core;

use Nette\InvalidArgumentException;
use Nette\Utils\Callback;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


/**
 * @template E of IEntity
 */
class Column
{
	/** @var callable|null */
	private $renderer = null;

	/** @var callable|null */
	private $sorter = null;


	public function __construct(
		private readonly string $name,
		private readonly bool $virtual,
	)
	{}


	public function getName() : string
	{
		return $this->name;
	}


	public function isVirtual() : bool
	{
		return $this->virtual;
	}


	/**
	 * @param callable $renderer
	 * @return Column<E>
	 */
	public function setRenderer(callable $renderer): self
	{
		$this->renderer = $renderer;
		return $this;
	}


	public function render(IEntity $row, mixed $value = null): mixed
	{
		try {
			$callback = Callback::check($this->renderer);
			$args = [$value, $row];
			if ($this->virtual) {
				$args = [$row];
			}

			return call_user_func_array($callback, $args);
		} catch (InvalidArgumentException) {}

		return $this->virtual ? null : $value;
	}


	/**
	 * @param callable $sorter
	 * @return Column<E>
	 */
	public function setSorter(callable $sorter): self
	{
		$this->sorter = $sorter;
		return $this;
	}


	/**
	 * @param ICollection<E> $collection
	 * @param string $order
	 * @return ICollection<E>
	 */
	public function sort(ICollection $collection, string $order = ICollection::ASC): ICollection
	{
		try {
			$callback = Callback::check($this->sorter);
			$result = call_user_func_array($callback, [$collection->resetOrderBy(), $order]);
			if ($result instanceof ICollection) {
				return $result;
			}
		} catch(InvalidArgumentException) {}

		return $collection->resetOrderBy()->orderBy($this->getName(), $order);
	}
}
