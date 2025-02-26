<?php
declare(strict_types=1);

namespace Flexsyscz\Datagrids\DatagridControl;

use Flexsyscz\Datagrids\Core\Table;
use Flexsyscz\FlashMessages\FlashMessage;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Utils\Paginator;
use Nextras\Orm\Entity\IEntity;
use stdClass;


/**
 * @template E of IEntity
 */
final class DatagridTemplate extends Template
{
	/** @var FlashMessage[]|stdClass[] */
	public array $flashes = [];

	public string $namespace;

	/** @var Table<E> */
	public Table $table;
	public Paginator $paginator;
}
