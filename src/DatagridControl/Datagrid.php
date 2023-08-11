<?php

declare(strict_types=1);

namespace Flexsyscz\Datagrids\DatagridControl;

use Flexsyscz\Datagrids\Column;
use Flexsyscz\FlashMessages\FlashMessages;
use Flexsyscz\Localization\TranslatedComponent;
use Latte\Attributes\TemplateFilter;
use Nette\Application\Attributes\Persistent;
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


	#[Persistent]
	public int $page = 1;

	#[Persistent]
	public string $sortBy;

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


	public function injectSession(Session $session): void
	{
		$this->session = $session;
	}


	abstract protected function getSession(): SessionSection;


	protected function createTemplate(): Template
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


	public function registerRowFormatter(callable $formatter): self
	{
		$this->rowFormatter = $formatter;

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
	 * @return bool[]
	 */
	public function getAdjustableColumns(): array
	{
		$columns = $this->adjustableColumns;
		$session = $this->getSession();
		if($session->offsetExists('columns')) {
			foreach ($session->offsetGet('columns') as $name => $column) {
				$columns[$name] = $column;
			}
		}

		return $columns;
	}


	protected function getPaginator(): Paginator
	{
		$paginator = new Paginator();

		$this->onAnchor[] = function() use ($paginator) {
			$paginator->setPage($this->page);
		};

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
		$sorter = $this->getColumnSorter($this->sortBy);
		if(is_callable($sorter)) {
			$collection = call_user_func_array($sorter, [$this->collection, $this->order]);
			if($collection instanceof ICollection) {
				$this->collection = $collection;
			}
		} else {
			$this->collection = $this->collection->orderBy($this->sortBy, $this->order);
		}

		return $this->collection;
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


	protected function createComponentAdjustColumnsForm(): Form
	{
		$form = new Form();

		$items = [];
		foreach (array_keys($this->adjustableColumns) as $column) {
			$items[$column] = $this->translate($column);
		}

		$form->addCheckboxList('columns', $this->translate('!datagrid.adjust.form.columns.label'))
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
				'columns' => $columns,
			]);
		} catch (InvalidArgumentException $e) {
			$form->addError($e->getMessage());
		}

		$form->onSuccess[] = function(Form $form) {
			try {
				$columns = $this->adjustableColumns;
				$httpData = $form->getHttpData(Form::DataText, 'columns[]');
				foreach ($httpData as $name) {
					$columns[$name] = true;
				}

				if (is_callable($this->onAdjustColumns)) {
					call_user_func(Callback::check($this->onAdjustColumns), $columns);
				}

				$this->getSession()->offsetSet('columns', $columns);
			} catch (InvalidArgumentException $e) {
				$form->addError($e->getMessage());
				return;
			}

			$this->getPresenter()->redirect('this');
		};

		return $form;
	}
}
