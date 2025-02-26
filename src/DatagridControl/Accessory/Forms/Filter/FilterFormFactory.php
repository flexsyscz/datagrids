<?php

declare(strict_types = 1);

namespace Flexsyscz\Datagrids\DatagridControl\Accessory\Forms\Filter;

use Flexsyscz\Forms\FormFactory;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;
use Nette\Http\SessionSection;
use Nette\InvalidArgumentException;
use Nette\Utils\Callback;


abstract class FilterFormFactory
{
	protected const Values = 'values';

	protected FormFactory $formFactory;

	protected SessionSection $filterStorage;
	protected SubmitButton $submitBtn;
	protected ?SubmitButton $cancelBtn = null;

	/** @var callable */
	protected $callback;


	public function __construct(FormFactory $formFactory)
	{
		$this->formFactory = $formFactory;
	}


	public function create(SessionSection $filterStorage, callable $callback): Form
	{
		$this->filterStorage = $filterStorage;
		$this->callback = $callback;

		$form = $this->formFactory->create();

		$this->submitBtn = $form->addSubmit('submit', '!datagrid.filter.submit.label');

		if ($filterStorage->get(self::Values)) {
			$this->cancelBtn = $form->addSubmit('cancel', '!datagrid.filter.cancel.label')
				->setValidationScope([]);
		}

		try {
			$form->onSuccess[] = Callback::check([$this, 'onSuccess']);
		} catch (InvalidArgumentException $e) {
			$form->addError($e->getMessage());
		}

		return $form;
	}


	protected function setDefaults(Form $form): void
	{
		try {
			$defaults = $this->filterStorage->get(self::Values);
			$form->setDefaults($defaults instanceof FilterFormValues ? $defaults : []);
		} catch (InvalidArgumentException) {}
	}


	protected function saveValues(FilterFormValues $values): void
	{
		if ($this->cancelBtn?->isSubmittedBy()) {
			$this->filterStorage->remove();
		} else {
			$this->filterStorage->set(self::Values, $values);
		}
	}
}
