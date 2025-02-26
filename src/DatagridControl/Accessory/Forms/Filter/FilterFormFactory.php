<?php

declare(strict_types = 1);

namespace Flexsyscz\Datagrids\DatagridControl\Accessory\Forms\Filter;

use Flexsyscz\Forms\FormFactory;
use Nette\Application\UI\Form;
use Nette\Http\SessionSection;
use Nette\InvalidArgumentException;
use Nette\Utils\Callback;


abstract class FilterFormFactory
{
	protected FormFactory $formFactory;

	protected SessionSection $filterStorage;

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

		$form->addSubmit('submit', 'submit.label');

		try {
			$form->onSuccess[] = Callback::check([$this, 'onSuccess']);
		} catch (InvalidArgumentException $e) {
			$form->addError($e->getMessage());
		}

		return $form;
	}
}
