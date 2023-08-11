<?php

declare(strict_types=1);

namespace Flexsyscz\Datagrids;


final class Column
{
	private string $name;

	/** @var callable|null */
	private $renderer;

	/** @var string|null */
	private ?string $alias;

	/** @var callable|null */
	private $sorter = null;


	public function __construct(string $name, ?callable $renderer, ?string $alias)
	{
		$this->name = $name;
		$this->renderer = $renderer;
		$this->alias = $alias;
	}


	public function setName(string $name): self
	{
		$this->name = $name;

		return $this;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function setRenderer(callable $renderer): self
	{
		$this->renderer = $renderer;

		return $this;
	}


	public function getRenderer(): ?callable
	{
		return $this->renderer;
	}


	public function setAlias(string $alias): self
	{
		$this->alias = $alias;

		return $this;
	}


	public function getAlias(): ?string
	{
		return $this->alias;
	}


	public function setSorter(callable $sorter): self
	{
		$this->sorter = $sorter;

		return $this;
	}


	public function getSorter(): ?callable
	{
		return $this->sorter;
	}
}
