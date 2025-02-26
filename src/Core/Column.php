<?php

declare(strict_types=1);

namespace Flexsyscz\Datagrids\Core;


class Column
{
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
}
