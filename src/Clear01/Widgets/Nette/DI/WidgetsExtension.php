<?php

namespace Clear01\Widgets\Nette\DI;

use Clear01\Widgets\IWidgetDeclarationFactory;
use Clear01\Widgets\Nette\IWidgetComponentFactory;
use Clear01\Widgets\Nette\NetteComponentStateSerializer;
use Clear01\Widgets\Nette\NetteUserIdentityAccessor;
use Clear01\Widgets\WidgetManager;
use Nette\Caching\Cache;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\ServiceDefinition;
use Nette\Utils\AssertionException;
use Nette\Utils\Strings;

/**
 * @inspiredBy Kdyby extensions (https://github.com/kdyby/) :-)
 */
class WidgetsExtension extends CompilerExtension {

	const DEFAULT_NAMESPACE = 'default';
	const WIDGET_FACTORY_TAG = 'clear01.widgetFactory';
	const CACHE_PREFIX = 'clear01widgets';

	protected $defaults = [
		'available'	=>	[],
		'validate' => TRUE,
		'optimize' => TRUE,
	];

	/** @var array Widget namespaces that are used. */
	protected $namespacesInUse = [];

	/** @var array Used for removing setup constructs when the 'optimized', lazy-loading version of WidgetManager is used */
	protected $allowedManagerSetupByNS = [];

	/** @var array Used for storing factory services by widget type id and namespace during the validation & optimization */
	protected $widgetFactoryServiceNamesByNS = [];


	public function loadConfiguration()
	{
		$this->allowedManagerSetupByNS = [];

		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		// Loads the widgets section
		$this->loadWidgets($config['available']);

		// Enumerate used widget namespaces
		$this->namespacesInUse = $this->getNamespacesInUse($builder);

		// Create manager for each namespace
		$managerServiceNameMap = [];
		foreach($this->namespacesInUse as $namespace) {
			$builder->addDefinition($managerServiceName = $this->getManagerServiceName($namespace))
				->setClass(WidgetManager::class)
				->addSetup('setContextPrefix', [$namespace])
				->setAutowired(FALSE);

			$managerServiceNameMap[$namespace] = $managerServiceName;

			$builder->addDefinition($this->getCacheServiceName($namespace))
				->setClass(Cache::class)
				->setAutowired(FALSE)
				->setArguments(['namespace' => self::CACHE_PREFIX . $namespace]);
		}

		// Manager accessor service.
		$builder->addDefinition($this->prefix('managerAccessor'))
			->setClass(WidgetManagerAccessor::class, [$managerServiceNameMap])
			->setAutowired(true);
		
		// Nette component serializer
		$builder->addDefinition($this->prefix('componentSerializer'))
			->setClass(NetteComponentStateSerializer::class)
			->setAutowired(true);

		// if only the default namespace is used, enable autowiring for manager
		if(count($this->namespacesInUse) == 1 && $this->namespacesInUse == [self::DEFAULT_NAMESPACE]) {
			$builder->getDefinition($this->prefix('manager.' . self::DEFAULT_NAMESPACE))->setAutowired(TRUE);
		}

		// User identity accessor definition
		$builder->addDefinition($this->prefix('userIdentityAccessor'))
			->setClass(NetteUserIdentityAccessor::class)
			->setAutowired(true);
	}

	protected function loadWidgets($widgets)
	{
		/**
		 * Widget declaration can be:
		 * - component
		 * - component factory (implementing IWidgetComponentFactory)
		 * - WidgetDeclarationFactory
		 */

		\Nette\Utils\Validators::assert($widgets, 'array');

		if(!count($widgets)) {
			return;
		}

		// list is provided directly, without namespace
		if(isset($widgets[0])) {
			$widgets = [
				self::DEFAULT_NAMESPACE => $widgets
			];
		}

		$builder = $this->getContainerBuilder();

		foreach($widgets as $namespace => $widgetList) {
			foreach($widgetList as $widgetTypeId => $widgetDefinition) {

				if(!is_array($widgetDefinition)) {
					$widgetDefinition = [
						'unique'	=>	true,
						'class'		=>	$widgetDefinition
					];
				}

				$widgetFactoryDefinition = $widgetDefinition['class'];

				list($statementRecord) = \Nette\DI\Compiler::filterArguments(array(
					is_string($widgetFactoryDefinition) ? new \Nette\DI\Statement($widgetFactoryDefinition) : $widgetFactoryDefinition
				));

				if(class_exists($statementRecord->entity) && in_array(IWidgetDeclarationFactory::class, class_implements($statementRecord->entity))) {
					// Declaration factory was given directly
					$factoryDef = $builder->addDefinition($this->prefix('widgetFactoryService.' . $namespace . '.' . Strings::random(32)));
					$factoryDef->class = $statementRecord->entity;
					$factoryDef->factory = $statementRecord;
					$factoryDef->setAutowired(FALSE);
					$factoryDef->setInject(TRUE);
					$factoryDef->addTag($this->getTagWithNamespace($namespace));

				} else {
					// Component or component factory was specified. Generic declaration factory will be used.

					// for arrays (config example: - \Comp1 \n - \Comp2)
					if (!is_string($widgetTypeId)) {
						$widgetTypeId = md5(\Nette\Utils\Json::encode($widgetFactoryDefinition));
					}

					// create definition of the services with unique id
					$serviceDef = $builder->addDefinition($widgetServiceId = $this->prefix('widgetService.' . $namespace . '.' . $widgetTypeId));
					$serviceDef->factory = $statementRecord;
					if (class_exists($statementRecord->entity)) {
						$serviceDef->setImplement(IWidgetComponentFactory::class);
						$serviceDef->setClass($statementRecord->entity);
					} elseif (interface_exists($statementRecord->entity) && in_array(IWidgetComponentFactory::class, class_implements($statementRecord->entity))) {
						if (count($statementRecord->arguments)) {
							throw new \Nette\Utils\AssertionException('Interface extending ' . IWidgetComponentFactory::class . ' cannot have any arguments! The widget should be creatable with no runtime dependencies. Use custom factory implementation if needed.');
						}
						$serviceDef->setImplement($statementRecord->entity);
						$serviceDef->setClass(null);
						$serviceDef->setFactory(null);
					}

					$serviceDef->setAutowired(FALSE);
					$serviceDef->setInject(TRUE);

					// create widget factory for the service
					$factoryDef = $builder->addDefinition($this->prefix('widgetFactoryService.' . $namespace . '.' . $widgetTypeId));
					$factoryDef->setClass(ContainerWidgetDeclarationFactory::class, [$widgetTypeId, $widgetServiceId, $widgetDefinition['unique']]);
					$factoryDef->setAutowired(FALSE);
					$factoryDef->setInject(TRUE);
					$factoryDef->addTag($this->getTagWithNamespace($namespace));
				}
			}
		}
	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		foreach($this->namespacesInUse as $namespace) {
			$managerDef = $builder->getDefinition($this->getManagerServiceName($namespace));
			foreach (array_keys($builder->findByTag($this->getTagWithNamespace($namespace))) as $serviceName) {
				$managerDef->addSetup('addWidgetFactory', array('@' . $serviceName));
			}
		}

		\Nette\Utils\Validators::assertField($config, 'validate', 'bool');
		if ($config['validate']) {
			$this->validateWidgets($builder);
		}

		\Nette\Utils\Validators::assertField($config, 'optimize', 'bool');
		if ($config['optimize']) {
			if (!$config['validate']) {
				throw new \RuntimeException("Cannot optimize without validation.");
			}
			$this->optimizeWidgets($builder);
		}
	}


	protected function getNamespacesInUse(ContainerBuilder $builder)
	{
		$namespaces = [];
		/** @var ServiceDefinition $definition */
		foreach($builder->getDefinitions() as $definition) {
			$tags = array_keys($definition->tags);
			foreach($tags as $tag) {
				if (Strings::startsWith($tag, self::WIDGET_FACTORY_TAG)) {
					$namespaces[] = $this->getNamespaceFromTag($tag);
				}
			}
		}
		return count($namespaces) ? array_unique($namespaces) : [self::DEFAULT_NAMESPACE];
	}

	private function validateWidgets(\Nette\DI\ContainerBuilder $builder) {
		foreach($this->namespacesInUse as $namespace) {
			$managerDef = $builder->getDefinition($this->getManagerServiceName($namespace));
			foreach($managerDef->setup as $setup) {
				if($setup->entity != 'addWidgetFactory') {
					// save the setup construct for later
					if (!isset($this->allowedManagerSetupByNS[$namespace])) {
						$this->allowedManagerSetupByNS[$namespace] = [];
					}
					$this->allowedManagerSetupByNS[$namespace][] = $setup;
				} else {
					// validate widget factories
					try {
						$serviceName = $builder->getServiceName(reset($setup->arguments));
						$factoryDef = $builder->getDefinition($serviceName);
					} catch (\Exception $e) {
						throw new AssertionException(
							"Please, do not register widget factories directly to service '" . $this->prefix('manager') . "'. " .
							"Use section '" . $this->name . ": available: ' for direct component definition, or tag the factory service (implementing the IWidgetFactory) as '" . $this->getTagWithNamespace(self::DEFAULT_NAMESPACE) . "'.",
							0, $e
						);
					}

					if (!$factoryDef->class) {
						throw new AssertionException(
							"Please, specify existing class for " . (is_numeric($serviceName) ? 'anonymous ' : '') . "service '$serviceName' explicitly, " .
							"and make sure, that the class exists and can be autoloaded."
						);

					} elseif (!class_exists($factoryDef->class)) {
						throw new AssertionException(
							"Class '{$factoryDef->class}' of " . (is_numeric($serviceName) ? 'anonymous ' : '') . "service '$serviceName' cannot be found. " .
							"Please make sure, that the class exists and can be autoloaded."
						);
					}

					if (!in_array(IWidgetDeclarationFactory::class, class_implements($factoryDef->class))) {
						throw new AssertionException("Widget factory '$serviceName' doesn't implement '" . IWidgetDeclarationFactory::class . "'.");
					}

					$this->widgetFactoryServiceNamesByNS[$namespace][] = $serviceName;
				}
			}
		}
	}

	protected function optimizeWidgets(\Nette\DI\ContainerBuilder $builder) {
		foreach($this->namespacesInUse as $namespace) {
			$managerDef = $builder->getDefinition($this->getManagerServiceName($namespace));
			$managerDef
				->setClass(LazyWidgetManager::class, array_merge([$this->widgetFactoryServiceNamesByNS[$namespace], '@'.$this->getCacheServiceName($namespace), '@container'],
					($managerDef->parameters && is_array($managerDef->parameters) ? $managerDef->parameters : [])))
				->setup = $this->allowedManagerSetupByNS[$namespace];
		}
	}


	protected function getTagWithNamespace($namespace) {
		if($namespace == self::DEFAULT_NAMESPACE) {
			return self::WIDGET_FACTORY_TAG;
		} else {
			return self::WIDGET_FACTORY_TAG . '.' . $namespace;
		}
	}

	protected function getNamespaceFromTag($tag){
		if($tag == self::WIDGET_FACTORY_TAG) {
			return self::DEFAULT_NAMESPACE;
		}
		if(!Strings::startsWith($tag, self::WIDGET_FACTORY_TAG)) {
			throw new \InvalidArgumentException('Not a widget factory tag!');
		}
		return substr($tag, strlen(self::WIDGET_FACTORY_TAG . '.'));
	}

	protected function getManagerServiceName($namespace) {
		return $this->prefix('manager.' . $namespace);
	}

	protected function getCacheServiceName($namespace) {
		return $this->prefix('cache.' . $namespace);
	}


}