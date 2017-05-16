<?php

namespace Clear01\Widgets\Nette;

use Clear01\Widgets\IWidgetDeclarationFactory;
use Clear01\Widgets\WidgetDeclaration;

class TemplateWidgetDeclarationFactory implements IWidgetDeclarationFactory
{
	/** @var  string Base with template. It will NOT be used to generate widget type ID.  */
	protected $basePath;

	/** @var string Template file path relative to basePath. It WILL be used to generate widget type ID. */
	protected $templatePath;

	/** @var array Args passed to the template. They will be included in widget type ID generation. */
	protected $templateArgs = [];

	/** @var bool Should be the widget unique? */
	protected $unique;

	/**
	 * TemplateWidgetDeclarationFactory constructor.
	 * @param $basePath string
	 * @param $templatePath string
	 * @param $templateArgs array
	 * @param bool $unique
	 */
	public function __construct($basePath, $templatePath, $templateArgs = [], $unique = true)
	{
		$this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
		$this->templatePath = $templatePath;
		$this->templateArgs = $templateArgs;
		$this->unique = $unique;
	}

	/** @return WidgetDeclaration|WidgetDeclaration[] */
	public function create()
	{
		return new WidgetDeclaration(
			md5(self::class . $this->templatePath . serialize($this->templateArgs)),
			$this->unique,
			[$this, 'createComponent']
		);
	}

	public function createComponent() {
		return new TemplateWidgetComponent($this->basePath . DIRECTORY_SEPARATOR . $this->templatePath, $this->templateArgs);
	}
}