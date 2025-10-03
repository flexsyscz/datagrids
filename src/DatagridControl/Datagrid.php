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
use Nette\Utils\Html;
use Nette\Utils\Paginator;
use Nette\Utils\Random;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


/**
 * @template E of IEntity
 */
abstract class Datagrid extends Control implements Injectable
{
	use TranslatedComponent;
	use FlashMessages;
	use Handlers;

	/** @var Table<E> */
	private Table $table;
	private Paginator $paginator;
	private FilterFormFactory $filterFormFactory;
	private SessionSection $filterStorage;

	protected static string $namespace = 'datagrid';

	protected static string $filterStoragePrefix = 'filterStorage';
	protected static string $selectionStoragePrefix = 'selectionStorage';

	/** @var array<callable(ICollection<E>, FilterFormValues): ICollection<E>> */
	public array $onFilter = [];

	/** @var array<callable(array<E>): void> */
	public array $onSelect = [];

	private ?Html $customToolbar = null;
	private bool $customToolbarMarginEndAuto = false;
	private bool $showCounter = false;

	/** @var int[]|string[] */
	private array $defaultSelection = [];

	#[Persistent]
	public int $page = 1;

	#[Persistent]
	public ?int $itemsPerPage = null;

	#[Persistent]
	public ?string $sortBy = null;

	#[Persistent]
	public string $order = ICollection::ASC;


	protected function getSession(string $prefix): SessionSection
	{
		$presenter = $this->getPresenter();
		return $presenter->getSession(sprintf('%s-%s', $prefix, get_class($presenter)));
	}


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
		$this->filterStorage = $this->getSession(self::$filterStoragePrefix);
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
				} catch (InvalidNamespaceException $e) {
					$this->flashError($e->getMessage());
				}
			}

			$template->setTranslator($this->translatorNamespace);
			$template->getLatte()->addExtension(new ApplicationLatte\UIExtension($this))
				->addExtension(new TranslatorExtension(
					$this->translatorNamespace->translate(...)
				));
		}

		return $template;
	}


	public function flushSelection(): void
	{
		$this->getSession(self::$selectionStoragePrefix)->remove();
	}


	/**
	 * @param int[]|string[] $ids
	 * @return Datagrid<E>
	 */
	public function setDefaultSelection(array $ids): self
	{
		$this->defaultSelection = $ids;

		return $this;
	}


	/**
	 * @param Html|null $customToolbar
	 * @param bool $marginEndAuto
	 * @return Datagrid<E>
	 */
	public function setCustomToolbar(?Html $customToolbar, bool $marginEndAuto = false): self
	{
		$this->customToolbar = $customToolbar;
		$this->customToolbarMarginEndAuto = $marginEndAuto;

		return $this;
	}


	public function hasCustomToolbar(): bool
	{
		return $this->customToolbar !== null;
	}


	/**
	 * @param bool $show
	 * @return Datagrid<E>
	 */
	public function showCounter(bool $show = true): self
	{
		$this->showCounter = $show;

		return $this;
	}


	public function render(): void
	{
		$template = $this->createTemplate(DatagridTemplate::class);

		if ($this->sortBy) {
			$this->table->sort($this->sortBy, $this->order);
		}

		$template->namespace = self::$namespace;
		$template->datagridId = Random::generate();
		$template->table = $this->table;
		$template->paginator = $this->paginator;
		$template->sortBy = $this->sortBy;
		$template->order = $this->order;
		$template->selectable = count($this->onSelect) > 0;
		$template->customToolbar = $this->customToolbar;
		$template->customToolbarMarginEndAuto = $this->customToolbarMarginEndAuto;
		$template->showCounter = $this->showCounter;

		$selectionStorage = $this->getSession(self::$selectionStoragePrefix);
		$selectedRows = $selectionStorage->get('ids');
		if (!$selectedRows) {
			$selectedRows = $this->defaultSelection;
			$selectionStorage->set('ids', $selectedRows);
		}
		$template->selectedRows = $selectedRows;

		$template->render();
	}
}
