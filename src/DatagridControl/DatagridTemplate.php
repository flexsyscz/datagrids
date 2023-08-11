<?php
declare(strict_types=1);

namespace Flexsyscz\Datagrids\DatagridControl;

use Flexsyscz\Datagrids\Column;
use Flexsyscz\FlashMessages\FlashMessage;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Utils\Paginator;
use Nextras\Orm\Entity\IEntity;


final class DatagridTemplate extends Template
{
	/** @var FlashMessage[] */
	public array $flashes = [];

	/** @var Column[] */
	public array $columns;

	/** @var string[] */
	public array $adjustableColumns;

	/** @var IEntity[] */
	public array $rows;

	public Paginator $paginator;
	public ?string $sortBy;
	public string $order;
	public ?string $toolbar;
	public bool $filtered;

	/** @var array<string[]|int[]|bool[]> */
	public array $metadata;
}
