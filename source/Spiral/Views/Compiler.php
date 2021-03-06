<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

use Spiral\Core\Component;
use Spiral\Core\Container\SaturableInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\Container\ContainerException;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Files\FilesInterface;

/**
 * Default spiral compiler implementation. Provides ability to cache compiled views and use set
 * of processors to prepare view source.
 */
class Compiler extends Component implements CompilerInterface, SaturableInterface
{
    /**
     * Configuration and compilation benchmarks.
     */
    use ConfigurableTrait, BenchmarkTrait;

    /**
     * Extension for compiled views.
     */
    const EXTENSION = 'php';

    /**
     * @var string
     */
    private $namespace = '';

    /**
     * @var string
     */
    private $view = '';

    /**
     * @var string
     */
    private $filename = '';

    /**
     * Compiled view filename.
     *
     * @var string
     */
    private $compiledFilename = '';

    /**
     * ViewManager dependencies.
     *
     * @var array
     */
    private $dependencies = [];

    /**
     * Chain of view processors to be applied to view source.
     *
     * @var array|ProcessorInterface[]
     */
    private $processors = [];

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @invisible
     * @var ViewManager
     */
    protected $views = null;

    /**
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        ViewManager $views,
        FilesInterface $files,
        $namespace,
        $view,
        $filename
    ) {
        //Our configuration is stored in parent ViewManager config
        $this->config = $views->config()['compiler'];
        $this->config['cache'] = $views->config()['cache'];

        $this->views = $views;
        $this->files = $files;

        $this->namespace = $namespace;
        $this->view = $view;
        $this->filename = $filename;

        $this->dependencies = $views->getDependencies();
    }

    /**
     * @param ContainerInterface $container
     */
    public function init(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * Non compiled view filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        $source = $this->getSource();
        foreach ($this->getProcessors() as $processor) {
            $reflection = new \ReflectionClass($processor);

            $context = $this->namespace . ViewProviderInterface::NS_SEPARATOR . $this->view;

            $benchmark = $this->benchmark($reflection->getShortName(), $context);
            try {
                $source = $processor->process($source);
            } finally {
                $this->benchmark($benchmark);
            }
        }

        $this->files->write($this->compiledFilename(), $source, FilesInterface::RUNTIME, true);
    }

    /**
     * {@inheritdoc}
     */
    public function compiledFilename()
    {
        if (!empty($this->compiledFilename)) {
            return $this->compiledFilename;
        }

        //Escaped view name
        $view = trim(str_replace(['\\', '/'], '-', $this->view), '-');

        //Unique cache postfix
        $postfix = '-' . hash('crc32b', join(',', $this->dependencies)) . '.' . self::EXTENSION;

        //Unique cache filename
        $filename = $this->namespace . '-' . $view . $postfix;;

        return $this->compiledFilename = $this->config['cache']['directory'] . '/' . $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function isCompiled()
    {
        if (!$this->config['cache']['enabled']) {
            return false;
        }

        if (!$this->files->exists($viewFilename = $this->compiledFilename())) {
            return false;
        }

        return $this->files->time($viewFilename) >= $this->files->time($this->filename);
    }

    /**
     * Get source of non compiler view file.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->files->read($this->filename);
    }

    /**
     * Create processors list based on compiler configuration.
     *
     * @return ProcessorInterface[]
     * @throws ContainerException
     */
    public function getProcessors()
    {
        if (!empty($this->processors)) {
            return $this->processors;
        }

        foreach ($this->config['processors'] as $processor => $options) {
            $this->processors[] = $this->container->get($processor, [
                'views'    => $this->views,
                'compiler' => $this,
                'options'  => $options
            ]);
        }

        return $this->processors;
    }

    /**
     * Clone compiler with reconfigured namespace and view.
     *
     * @param string $namespace
     * @param string $view
     * @return Compiler
     */
    public function reconfigure($namespace, $view)
    {
        $compiler = clone $this;

        $compiler->namespace = $namespace;
        $compiler->view = $view;

        //Must be the same engine
        $compiler->filename = $this->views->getFilename($namespace, $view);
        $compiler->compiledFilename = '';

        //Processors has to be regenerated to flush content
        $compiler->processors = [];

        return $compiler;
    }
}