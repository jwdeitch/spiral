<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use Spiral\Console\ConsoleDispatcher;
use Spiral\Core\Components\Loader;
use Spiral\Core\Exceptions\ConfiguratorException;
use Spiral\Core\Exceptions\ControllerException;
use Spiral\Core\Exceptions\CoreException;
use Spiral\Core\Exceptions\FatalException;
use Spiral\Debug\SnapshotInterface;
use Spiral\Files\FilesInterface;
use Spiral\Http\HttpDispatcher;
use Spiral\Modules\ModuleManager;

/**
 * He made 9 rings... i mean this is default spiral core responsible for many things at the same
 * time.
 *
 * @property \Spiral\Core\Core                        $core
 * @property \Spiral\Core\Components\Loader           $loader
 * @property \Spiral\Modules\ModuleManager            $modules
 * @property \Spiral\Debug\Debugger                   $debugger
 *
 * @property \Spiral\Console\ConsoleDispatcher        $console
 * @property \Spiral\Http\HttpDispatcher              $http
 *
 * @property \Spiral\Cache\CacheProvider              $cache
 * @property \Spiral\Encrypter\Encrypter              $encrypter
 * @property \Spiral\Http\InputManager                $input
 * @property \Spiral\Files\FileManager                $files
 * @property \Spiral\Session\SessionStore             $session
 * @property \Spiral\Tokenizer\Tokenizer              $tokenizer
 * @property \Spiral\Translator\Translator            $i18n
 * @property \Spiral\Views\ViewManager                $views
 * @property \Spiral\Storage\StorageManager           $storage
 *
 * @property \Spiral\Redis\RedisManager               $redis
 * @property \Spiral\Image\ImageManager               $images
 *
 * @property \Spiral\Database\DatabaseProvider        $dbal
 * @property \Spiral\ODM\ODM                          $odm
 * @property \Spiral\ORM\ORM                          $orm
 *
 * @property \Spiral\Http\Cookies\CookieManager       $cookies Scope depended.
 * @property \Spiral\Http\Routing\Router              $router  Scope depended.
 * @property \Psr\Http\Message\ServerRequestInterface $request Scope depended.
 */
class Core extends Container implements CoreInterface, ConfiguratorInterface, HippocampusInterface
{
    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * I need a constant for Symfony Console. :/
     */
    const VERSION = '0.8.9-alpha';

    /**
     * Name of bootstrap file to be called if no application core were defined.
     */
    const BOOTSTRAP = 'bootstrap.php';

    /**
     * Runtime files and config extensions.
     */
    const EXTENSION = 'php';

    /**
     * Some environment constants to use to produce more clean code with less magic values.
     */
    const DEVELOPMENT = 'development';
    const PRODUCTION  = 'production';
    const STAGING     = 'staging';
    const TESTING     = 'testing';

    /**
     * Every application should have defined timezone.
     *
     * @see setTimezone()
     * @see timezone()
     * @var string
     */
    private $timezone = 'UTC';

    /**
     * Application environment will be change additional hash value to be assigned for memory data
     * (hippocampus).
     *
     * @see setEnvrionment()
     * @see environment()
     * @var string
     */
    private $environment = null;

    /**
     * Theoretical unique value should be assigned to every environment and application location.
     *
     * @see applicationID()
     * @var string
     */
    private $applicationID = '';

    /**
     * Set of primary application directories.
     *
     * @see setDirectory()
     * @see directory()
     * @see getDirectories()
     * @var array
     */
    private $directories = [
        'root'        => null,
        'public'      => null,
        'libraries'   => null,
        'framework'   => null,
        'application' => null,
        'runtime'     => null,
        'config'      => null,
        'cache'       => null
    ];

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $bindings = [
        //Core interface bindings
        'Spiral\Core\ContainerInterface'               => 'Spiral\Core\Core',
        'Spiral\Core\ConfiguratorInterface'            => 'Spiral\Core\Core',
        'Spiral\Core\HippocampusInterface'             => 'Spiral\Core\Core',
        'Spiral\Core\CoreInterface'                    => 'Spiral\Core\Core',
        //Instrumental bindings
        'Psr\Log\LoggerInterface'                      => 'Spiral\Debug\Logger',
        'Spiral\Debug\SnapshotInterface'               => 'Spiral\Debug\Snapshot',
        'Spiral\Cache\StoreInterface'                  => 'Spiral\Cache\CacheStore',
        'Spiral\Cache\CacheProviderInterface'          => 'Spiral\Cache\CacheProvider',
        'Spiral\Files\FilesInterface'                  => 'Spiral\Files\FileManager',
        'Spiral\Views\ViewProviderInterface'           => 'Spiral\Views\ViewManager',
        'Spiral\Storage\StorageInterface'              => 'Spiral\Storage\StorageManager',
        'Spiral\Storage\BucketInterface'               => 'Spiral\Storage\Entities\StorageBucket',
        'Spiral\Session\StoreInterface'                => 'Spiral\Session\SessionStore',
        'Spiral\Encrypter\EncrypterInterface'          => 'Spiral\Encrypter\Encrypter',
        'Spiral\Tokenizer\TokenizerInterface'          => 'Spiral\Tokenizer\Tokenizer',
        'Spiral\Validation\ValidatorInterface'         => 'Spiral\Validation\Validator',
        'Spiral\Translator\TranslatorInterface'        => 'Spiral\Translator\Translator',
        'Spiral\Database\DatabaseProviderInterface'    => 'Spiral\Database\DatabaseProvider',
        'Spiral\Database\Migrations\MigratorInterface' => 'Spiral\Database\Migrations\Migrator',
        //Spiral aliases
        'core'                                         => 'Spiral\Core\Core',
        'loader'                                       => 'Spiral\Core\Components\Loader',
        'modules'                                      => 'Spiral\Modules\ModuleManager',
        'debugger'                                     => 'Spiral\Debug\Debugger',
        //Dispatchers
        'console'                                      => 'Spiral\Console\ConsoleDispatcher',
        'http'                                         => 'Spiral\Http\HttpDispatcher',
        //Component aliases
        'cache'                                        => 'Spiral\Cache\CacheProvider',
        'dbal'                                         => 'Spiral\Database\DatabaseProvider',
        'encrypter'                                    => 'Spiral\Encrypter\Encrypter',
        'input'                                        => 'Spiral\Http\InputManager',
        'files'                                        => 'Spiral\Files\FileManager',
        'odm'                                          => 'Spiral\ODM\ODM',
        'orm'                                          => 'Spiral\ORM\ORM',
        'session'                                      => 'Spiral\Session\SessionStore',
        'storage'                                      => 'Spiral\Storage\StorageManager',
        'tokenizer'                                    => 'Spiral\Tokenizer\Tokenizer',
        'i18n'                                         => 'Spiral\Translator\Translator',
        'views'                                        => 'Spiral\Views\ViewManager',
        //Scope dependend aliases
        'cookies'                                      => 'Spiral\Http\Cookies\CookieManager',
        'router'                                       => 'Spiral\Http\Routing\Router',
        'request'                                      => 'Psr\Http\Message\ServerRequestInterface'
    ];

    /**
     * @var DispatcherInterface
     */
    protected $dispatcher = null;

    /**
     * Components to be autoloader while application initialization.
     *
     * @var array
     */
    protected $autoload = [Loader::class, ModuleManager::class];

    /**
     * Core class will extend default spiral container and initiate set of directories. You must
     * provide application, libraries and root directories to constructor.
     *
     * @param array $directories Core directories list.
     */
    public function __construct(array $directories)
    {
        //Container constructing
        parent::__construct();

        $this->directories = $directories + [
                'public'  => $directories['root'] . '/webroot',
                'config'  => $directories['application'] . '/config',
                'runtime' => $directories['application'] . '/runtime',
                'cache'   => $directories['application'] . '/runtime/cache'
            ];

        if (empty($this->environment)) {
            //This is spiral shortcut to set environment, can be redefined by custom application class.
            $filename = $this->directory('runtime') . '/environment.php';
            $this->setEnvironment(file_exists($filename) ? (require $filename) : self::DEVELOPMENT);
        }

        date_default_timezone_set($this->timezone);
    }

    /**
     * Change application timezone.
     *
     * @param string $timezone
     * @return $this
     * @throws CoreException
     */
    public function setTimezone($timezone)
    {
        try {
            date_default_timezone_set($timezone);
        } catch (\Exception $exception) {
            throw new CoreException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Get active application timezone.
     *
     * @return string
     */
    public function timezone()
    {
        return $this->timezone;
    }

    /**
     * Change application environment in runtime.
     *
     * @param mixed $environment
     * @param bool  $regenerateID Update applicationID based on provided value.
     * @return $this
     */
    public function setEnvironment($environment, $regenerateID = true)
    {
        $this->environment = $environment;
        if ($regenerateID) {
            $this->applicationID = abs(crc32($this->directory('root') . $this->environment));
        }

        return $this;
    }

    /**
     * Application environment value.
     *
     * @return string
     */
    public function environment()
    {
        return $this->environment;
    }

    /**
     * Get unique applicationID linked to application root directory and active environment.
     *
     * @return mixed
     */
    public function applicationID()
    {
        return $this->applicationID;
    }

    /**
     * Set application directory.
     *
     * @param string $alias Directory alias, ie. "framework".
     * @param string $path  Directory path without ending slash.
     * @return $this
     */
    public function setDirectory($alias, $path)
    {
        $this->directories[$alias] = $path;

        return $this;
    }

    /**
     * Get application directory.
     *
     * @param string $alias
     * @return string
     */
    public function directory($alias)
    {
        return $this->directories[$alias];
    }

    /**
     * All application directories.
     *
     * @return array
     */
    public function getDirectories()
    {
        return $this->directories;
    }

    /**
     * Bootstrap application. Must be executed before start method.
     */
    public function bootstrap()
    {
        if (file_exists($this->directory('application') . '/' . static::BOOTSTRAP)) {
            $application = $this;
            //Old Fashion, btw there is very tasty cocktail under the same name
            require($this->directory('application') . '/' . static::BOOTSTRAP);
        }
    }

    /**
     * Start application using custom or default dispatcher.
     *
     * @param DispatcherInterface $dispatcher Custom dispatcher.
     */
    public function start(DispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = !empty($dispatcher) ? $dispatcher : $this->createDispatcher();
        $this->dispatcher->start();
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig($section = null)
    {
        $filename = $this->createFilename($section, $this->directories['config']);

        //Configuration cache ID
        $cached = str_replace(['/', '\\'], '-', 'config-' . $section);

        //Cached configuration
        if (is_null($data = $this->loadData($cached, null, $cachedFilename))) {
            if (!file_exists($filename)) {
                throw new ConfiguratorException(
                    "Unable to load '{$section}' configuration, file not found."
                );
            }

            $data = (require $filename);

            //Let's check for environment specific config
            $environment = $this->createFilename(
                $section,
                $this->directories['config'] . '/' . $this->environment
            );

            if (file_exists($environment)) {
                $data = array_merge($data, (require $environment));
            }

            $this->saveData($cached, $data);

            return $data;
        }

        if (!file_exists($filename)) {
            throw new CoreException("Unable to load '{$section}' configuration, file not found.");
        }

        if (file_exists($cachedFilename) && filemtime($cachedFilename) < filemtime($filename)) {
            //We can afford skipping FilesInterface here
            unlink($cachedFilename);

            //Configuration were updated, reloading
            return $this->getConfig($section);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $filename Cache filename.
     */
    public function loadData($name, $location = null, &$filename = null)
    {
        $filename = $this->createFilename($name, $location);

        try {
            return include($filename);
        } catch (\ErrorException $exception) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveData($name, $data, $location = null)
    {
        //We need help to write file with directory creation
        $this->get(FilesInterface::class)->write(
            $this->createFilename($name, $location),
            '<?php return ' . var_export($data, true) . ';',
            FilesInterface::RUNTIME,
            true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function callAction($controller, $action = '', array $parameters = [])
    {
        if (!class_exists($controller)) {
            throw new ControllerException(
                "No such controller '{$controller}' found.",
                ControllerException::NOT_FOUND
            );
        }

        //Initiating controller with all required dependencies
        $controller = $this->get($controller);
        if (!$controller instanceof ControllerInterface) {
            throw new ControllerException(
                "No such controller '{$controller}' found.",
                ControllerException::NOT_FOUND
            );
        }

        return $controller->callAction($action, $parameters);
    }

    /**
     * Handle php shutdown and search for fatal errors.
     */
    public function handleShutdown()
    {
        if (!empty($error = error_get_last())) {
            $this->handleException(new FatalException(
                $error['message'], $error['type'], 0, $error['file'], $error['line']
            ));
        }
    }

    /**
     * Convert application error into exception.
     *
     * @param int    $code
     * @param string $message
     * @param string $filename
     * @param int    $line
     * @throws \ErrorException
     */
    public function handleError($code, $message, $filename = '', $line = 0)
    {
        throw new \ErrorException($message, $code, 0, $filename, $line);
    }

    /**
     * Handle exception using associated application dispatcher.
     *
     * @param \Exception $exception
     */
    public function handleException($exception)
    {
        restore_error_handler();
        restore_exception_handler();

        /**
         * @var SnapshotInterface $snapshot
         */
        $snapshot = $this->get(SnapshotInterface::class, compact('exception'));

        //Reporting
        $snapshot->report();

        if (!empty($this->dispatcher)) {
            //Now dispatcher can handle snapshot it's own way
            $this->dispatcher->handleSnapshot($snapshot);
        } else {
            echo $snapshot;
        }
    }

    /**
     * Create default application dispatcher based on environment value.
     *
     * @return DispatcherInterface|ConsoleDispatcher|HttpDispatcher
     */
    private function createDispatcher()
    {
        if (php_sapi_name() === 'cli') {
            return $this->get(ConsoleDispatcher::class);
        }

        if ($this->hasBinding(HttpDispatcher::class)) {
            return $this->get(HttpDispatcher::class);
        }

        //Microseconds :0.
        $http = new HttpDispatcher($this, $this);
        $this->bind(HttpDispatcher::SINGLETON, $http);

        return $http;
    }

    /**
     * Get extension to use for runtime data or configuration cache, all file in cache directory
     * will additionally get applicationID postfix.
     *
     * @param string $name     Runtime data file name (without extension).
     * @param string $location Location to store data in.
     * @return string
     */
    private function createFilename($name, $location = null)
    {
        $name = str_replace(['/', '\\'], '-', $name);

        if (!empty($location)) {
            return $location . '/' . $name . '.' . static::EXTENSION;
        }

        //Runtime cache
        return $this->directories['cache'] . "/$name-{$this->applicationID}" . '.' . static::EXTENSION;
    }

    /**
     * Singleton core instance.
     *
     * @return static
     */
    public static function instance()
    {
        return self::container()->get(self::class);
    }

    /**
     * Initiate application core.
     *
     * @param array $directories Spiral directories should include root, libraries and application
     *                           directories.
     * @param bool  $catchErrors
     * @return static
     */
    public static function init(array $directories, $catchErrors = true)
    {
        /**
         * @var Core $core
         */
        $core = new static($directories + ['framework' => dirname(__DIR__)]);

        $core->bindings = [
                static::class                => $core,
                self::class                  => $core,
                ContainerInterface::class    => $core,
                ConfiguratorInterface::class => $core,
                HippocampusInterface::class  => $core,
                CoreInterface::class         => $core,
            ] + $core->bindings;

        //Error and exception handlers
        if ($catchErrors) {
            register_shutdown_function([$core, 'handleShutdown']);
            set_error_handler([$core, 'handleError']);
            set_exception_handler([$core, 'handleException']);
        }

        foreach ($core->autoload as $module) {
            $core->get($module);
        }

        //Bootstrapping our application
        $core->bootstrap();

        return $core;
    }
}
