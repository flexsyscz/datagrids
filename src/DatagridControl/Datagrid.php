<?php

declare(strict_types=1);

namespace Flexsyscz\Datagrids\DatagridControl;

use Flexsyscz\Datagrids\Column;
use Flexsyscz\FlashMessages\FlashMessages;
use Flexsyscz\Localization\TranslatedComponent;
use Latte\Attributes\TemplateFilter;
use Nette\Application\AbortException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\UI\Template;
use Nette\Bridges\ApplicationLatte;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\InvalidArgumentException;
use Nette\Utils\Callback;
use Nette\Utils\Html;
use Nette\Utils\Paginator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


abstract class Datagrid extends Control
{
	use TranslatedComponent;
	use FlashMessages;


	public const    OffsetColumns = 'columns',
					OffsetSelectedItems = 'selectedItems';

	#[Persistent]
	public int $page = 1;

	#[Persistent]
	public ?string $sortBy;

	#[Persistent]
	public string $order = ICollection::ASC;

	protected Session $session;
	protected bool $filtered = false;

	/** @var Column[] */
	private array $columns = [];

	/** @var bool[] */
	private array $adjustableColumns = [];

	/** @var callable|null */
	protected $onAdjustColumns = null;

	/** @var callable|null */
	private $rowFormatter = null;

	protected ICollection $collection;
	protected Paginator $paginator;

	protected int $itemsPerPage = 50;

	protected bool $selectable = false;

	/** @var callable|null */
	protected $selectableCallback = null;


	public function injectSession(Session $session): void
	{
		$this->session = $session;
	}


	abstract protected function getSession(): SessionSection;


	protected function createTemplate(?string $class = null): Template
	{
		$template = parent::createTemplate();
		$template->setFile(__DIR__ . '/templates/table.latte');
		if($template instanceof ApplicationLatte\Template) {
			$this->translatorNamespace->dictionariesRepository->add(__DIR__ . '/translations', 'datagrid');
			$template->setTranslator($this->translatorNamespace);

			$template->addFilter('formatRow', [$this, 'formatRow']);
			$template->addFilter('renderCell', [$this, 'renderCell']);
		}

		return $template;
	}


	#[TemplateFilter]
	public function renderCell(mixed $value, string $key, IEntity $row): string|Html
	{
		$result = $value;
		if (isset($this->columns[$key]) && is_callable($this->columns[$key]->getRenderer())) {
			$result = call_user_func_array($this->columns[$key]->getRenderer(), [$value, $row]);
		}

		return is_string($result) || $result instanceof Html
			? $result
			: (is_scalar($result)
				? strval($result)
				: (is_null($result) ? '' : sprintf('_%s_', $key)));
	}


	#[TemplateFilter]
	public function formatRow(IEntity $row): string
	{
		$format = null;
		if (is_callable($this->rowFormatter)) {
			$format = call_user_func_array($this->rowFormatter, [$row]);
		}

		return is_string($format) ? $format : '';
	}


	public function registerRowFormatter(callable $formatter): self
	{
		$this->rowFormatter = $formatter;

		return $this;
	}


	public function addColumn(string $name, ?callable $renderer = null, ?string $alias = null): Column
	{
		$this->columns[$name] = new Column($name, $renderer, $alias);
		return $this->columns[$name];
	}


	public function addAdjustableColumn(string $name, ?callable $renderer = null, ?string $alias = null): Column
	{
		$this->adjustableColumns[$name] = false;
		return $this->addColumn($name, $renderer, $alias);
	}


	/**
	 * @return Column[]
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}


	/**
	 * @return bool[]
	 */
	public function getAdjustableColumns(): array
	{
		$columns = $this->adjustableColumns;
		$session = $this->getSession();
		if($session->offsetExists(self::OffsetColumns)) {
			$_columns = $session->offsetGet(self::OffsetColumns);
			if (is_array($_columns)) {
				foreach ($_columns as $name => $column) {
					$columns[$name] = $column;
				}
			}
		}

		return $columns;
	}


	protected function getPaginator(): Paginator
	{
		$paginator = new Paginator();

		return $paginator->setItemCount($this->collection->countStored())
			->setItemsPerPage($this->itemsPerPage)
			->setPage($this->page);
	}


	public function getColumnSorter(string $name): ?callable
	{
		return isset($this->columns[$name]) ? $this->columns[$name]->getSorter() : null;
	}


	public function getCollectionAfterSorting(): ICollection
	{
		if ($this->sortBy) {
			$sorter = $this->getColumnSorter($this->sortBy);
			if (is_callable($sorter)) {
				$collection = call_user_func_array($sorter, [$this->collection, $this->order]);
				if ($collection instanceof ICollection) {
					$this->collection = $collection;
				}
			} else {
				$this->collection = $this->collection->orderBy($this->sortBy, $this->order);
			}
		}

		return $this->collection;
	}


	public function resetCollection(ICollection $collection): void
	{
		$this->collection = $collection;
		$this->paginator = $this->getPaginator();
	}


	protected function createComponentAdjustColumnsForm(): Form
	{
		$form = new Form();

		$items = [];
		foreach (array_keys($this->adjustableColumns) as $column) {
			$items[$column] = $this->translate($column);
		}

		$form->addCheckboxList(self::OffsetColumns, $this->translate('!datagrid.adjust.form.columns.label'))
			->setRequired(false)
			->setItems($items);

		$form->addSubmit('save', $this->translate('!datagrid.adjust.form.save.label'));

		try {
			$columns = [];
			foreach ($this->getAdjustableColumns() as $name => $state) {
				if ($state) {
					$columns[] = $name;
				}
			}
			$form->setDefaults([
				self::OffsetColumns => $columns,
			]);
		} catch (InvalidArgumentException $e) {
			$form->addError($e->getMessage());
		}

		$form->onSuccess[] = function(Form $form) {
			try {
				$columns = $this->adjustableColumns;
				$httpData = $form->getHttpData($form::DataText, 'columns[]');
				if (is_array($httpData)) {
					foreach ($httpData as $name) {
						$columns[$name] = true;
					}
				}

				if (is_callable($this->onAdjustColumns)) {
					call_user_func(Callback::check($this->onAdjustColumns), $columns);
				}

				$this->getSession()->offsetSet(self::OffsetColumns, $columns);
			} catch (InvalidArgumentException $e) {
				$form->addError($e->getMessage());
				return;
			}

			$this->getPresenter()->redirect('this');
		};

		return $form;
	}


	public function setItemsSelectionCallback(?callable $callback): void
	{
		$this->selectable = is_callable($callback);
		$this->selectableCallback = $callback;
	}


	/**
	 * @param int $itemId
	 * @return void
	 * @throws AbortException
	 */
	public function handleToggleItem(int $itemId): void
	{
		$presenter = $this->getPresenter();
		if (!$presenter->isAjax()) {
			$this->redirect('this');
		}

		$items = [];
		$session = $this->getSession();
		if ($session->offsetExists(self::OffsetSelectedItems)) {
			$items = $session->offsetGet(self::OffsetSelectedItems);
			if (!is_array($items)) {
				$items = [];
			}
		}

		$item = $this->collection->getById($itemId);
		if ($item instanceof IEntity) {
			$state = $presenter->getHttpRequest()->getPost('state') === 'true';
			if ($state) {
				$items[$itemId] = $itemId;
			} else {
				unset($items[$itemId]);
			}

			$session->offsetSet(self::OffsetSelectedItems, $items);
			$payload = ['selected' => $state];

		} else {
			$presenter->getHttpResponse()->setCode(404);
			$presenter->sendResponse(new TextResponse(sprintf('Item %s not found', $itemId)));
		}

		$presenter->sendJson($payload);
	}


	/**
	 * @return void
	 * @throws AbortException
	 */
	public function handleToggleAllItems(): void
	{
		$presenter = $this->getPresenter();
		if (!$presenter->isAjax()) {
			$this->redirect('this');
		}

		$session = $this->getSession();
		$state = $presenter->getHttpRequest()->getPost('state') === 'true';
		$items = $state ? $this->collection->fetchPairs('id', 'id') : [];
		$session->offsetSet(self::OffsetSelectedItems, $items);
		$payload = [self::OffsetSelectedItems => $items];

		$presenter->sendJson($payload);
	}


	/**
	 * @param int[]|string[] $itemIds
	 * @return void
	 */
	public function setSelectedItems(array $itemIds): void
	{
		$session = $this->getSession();

		$items = [];
		if ($session->offsetExists(self::OffsetSelectedItems)) {
			$items = $session->offsetGet(self::OffsetSelectedItems);

			if (!is_array($items)) {
				$items = [];
			}
		}

		foreach ($itemIds as $itemId) {
			$item = $this->collection->getById($itemId);
			if ($item instanceof IEntity) {
				$items[$itemId] = $itemId;
			}
		}

		$session->offsetSet(self::OffsetSelectedItems, $items);
	}


	public function flushSelectedItems(): void
	{
		$session = $this->getSession();
		if ($session->offsetExists(self::OffsetSelectedItems)) {
			$session->offsetUnset(self::OffsetSelectedItems);
		}
	}


	public function handleUpdateBody(): void
	{
		if ($this->getPresenter()->isAjax()) {
			$this->redrawControl('datagrid');
			$this->redrawControl('body');
		}
	}
}
