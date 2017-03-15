<?php

namespace Clear01\Widgets\Nette;

use Nette\Application\UI\Control;

class TemplateWidgetComponent extends Control
{
	protected $templateFilename;

	protected $templateArgs = [];

	/**
	 * TemplateWidgetComponent constructor.
	 * @param string $templateFilename
	 * @param array $templateArgs
	 */
	public function __construct($templateFilename, $templateArgs = [])
	{
		parent::__construct();
		$this->templateFilename = $templateFilename;
		$this->templateArgs = $templateArgs;
	}

	public function render() {
		foreach($this->templateArgs as $key => $value) {
			$this->template->$key = $value;
		}
		$this->template->setFile($this->templateFilename);
		$this->template->render();
	}

}