<?php

declare(strict_types = 1);

namespace Flexsyscz\Datagrids\DatagridControl;

use Flexsyscz\Application\DI\Injectable;
use Flexsyscz\Datagrids\Core\Table;
use Flexsyscz\Datagrids\DatagridControl\Accessory\Forms\Filter\FilterFormFactory;
use Flexsyscz\Datagrids\DatagridControl\Accessory\Forms\Filter\FilterFormValues;
use Flexsyscz\FlashMessages\FlashMessages;
use Flexsyscz\Localization\Exceptions\InvalidNamespaceException;
use Flexsyscz\Localization\Translations\TranslatedComponent;
use Latte\Essential\TranslatorExtension;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\UI\Template;
use Nette\Bridges\ApplicationLatte;
use Nette\Http\SessionSection;
use Nette\Utils\Callback;
use Nette\Utils\Paginator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


/**
 * @template E of IEntity
 */
abstract class Datagrid extends Control implements Injectable
{
	use TranslatedComponent;
	use FlashMessages;

	/** @var Table<E> */
	private Table $table;
	private Paginator $paginator;
	private FilterFormFactory $filterFormFactory;
	private SessionSection $filterStorage;

	protected static string $namespace = 'datagrid';

	/** @var array<callable(ICollection<E>, FilterFormValues): ICollection<E>> */
	public array $onFilter = [];

	#[Persistent]
	public int $page = 1;

	#[Persistent]
	public ?int $itemsPerPage = null;


	/**
	 * @param ICollection<E> $collection
	 * @return Datagrid<E>
	 */
	public function setCollection(ICollection $collection, int $itemsPerPage = 100): self
	{
		$values = $this->filterStorage->get('values');
		if ($values instanceof FilterFormValues) {
			foreach ($this->onFilter as $callback) {
				/** @var ICollection<E> $collection */
				$collection = Callback::check($callback)($collection, $values);
			}
		}

		$this->paginator = (new Paginator())
			->setItemsPerPage($this->itemsPerPage ?? $itemsPerPage)
			->setItemCount($collection->countStored())
			->setPage($this->page);

		$this->table = new Table($collection, $this->paginator);
		return $this;
	}


	/**
	 * @param FilterFormFactory $filterFormFactory
	 * @return Datagrid<E>
	 */
	public function setFilterFormFactory(FilterFormFactory $filterFormFactory): self
	{
		$presenter = $this->getPresenter();
		$this->filterStorage = $presenter->getSession(get_class($presenter));

		$this->filterFormFactory = $filterFormFactory;
		return $this;
	}


	/**
	 * @return Table<E>
	 */
	public function getTable(): Table
	{
		return $this->table;
	}


	protected function createComponentFilterForm(): Form
	{
		return $this->filterFormFactory->create($this->filterStorage, function() {
			$this->redirect('this');
		});
	}


	protected function createTemplate(?string $class = null): Template
	{
		$template = parent::createTemplate($class);
		$template->setFile(__DIR__ . '/templates/datagrid.latte');
		if ($template instanceof ApplicationLatte\Template) {
			if (!$this->translatorNamespace->repository->has(self::$namespace)) {
				try {
					$this->translatorNamespace->repository->add(__DIR__ . '/translations', self::$namespace);
					$template->setTranslator($this->translatorNamespace);
					$template->getLatte()->addExtension(new ApplicationLatte\UIExtension($this))
						->addExtension(new TranslatorExtension(
							$this->translatorNamespace->translate(...)
						));
				} catch (InvalidNamespaceException $e) {
					$this->flashError($e->getMessage());
				}
			}
		}

		return $template;
	}


	public function render(): void
	{
		$template = $this->createTemplate(DatagridTemplate::class);

		$template->namespace = self::$namespace;
		$template->table = $this->table;
		$template->paginator = $this->paginator;

		$template->render();
	}
}
