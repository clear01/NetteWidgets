<?php

namespace Clear01\Widgets\Nette;

use Clear01\Widgets\IWidgetDeclarationFactory;
use Clear01\Widgets\WidgetDeclaration;

class TemplateWidgetDeclarationFactory implements IWidgetDeclarationFactory
{
	protected $templateFilename;

	protected $templateArgs = [];

	protected $unique;

	/**
	 * TemplateWidgetDeclarationFactory constructor.
	 * @param $templateFilename string
	 * @param $templateArgs array
	 * @param bool $unique
	 */
	public function __construct($templateFilename, $templateArgs = [], $unique = true)
	{
		$this->templateFilename = $templateFilename;
		$this->templateArgs = $templateArgs;
		$this->unique = $unique;
	}

	/** @return WidgetDeclaration|WidgetDeclaration[] */
	public function create()
	{
		return new WidgetDeclaration(
			md5(self::class . $this->templateFilename . serialize($this->templateArgs)),
			$this->unique,
			[$this, 'createComponent']
		);
	}

	public function createComponent() {
		return new TemplateWidgetComponent($this->templateFilename, $this->templateArgs);
	}
}