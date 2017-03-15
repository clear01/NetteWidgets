<?php

use Clear01\Widgets\Nette\IRichWidgetComponent;
use Nette\Application\UI\Control;

class SampleComponent extends Control implements IRichWidgetComponent {

	/** @persistent */
	public $number;

	public function handleGenerate() {
		$this->number = rand(0, 100);
		$this->redrawControl('dataSnippet');
	}

	public function render()
	{
		$this->template->setFile(__DIR__ . '/scRender.latte');
		$this->fillTemplate();
		$this->template->render();
	}

	public function renderPlaceholder()
	{
		$this->template->setFile(__DIR__ . '/scPlaceholder.latte');
		$this->fillTemplate();
		$this->template->render();
	}

	public function renderEditable()
	{
		$this->template->setFile(__DIR__ . '/scEdit.latte');
		$this->fillTemplate();
		$this->template->render();
	}

	private function fillTemplate()
	{
		$this->template->number = $this->number;
	}
	
}

?>