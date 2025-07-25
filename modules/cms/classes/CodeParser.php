<?php namespace Cms\Classes;

use File;
use Lang;
use Cache;
use Config;
use SystemException;
use Exception;

/**
 * CodeParser parses the PHP code section of CMS objects.
 *
 * @package october\cms
 * @author Alexey Bobkov, Samuel Georges
 */
class CodeParser
{
    /**
     * @var \Cms\Classes\CmsCompoundObject A reference to the CMS object being parsed.
     */
    protected $object;

    /**
     * @var string Contains a path to the CMS object's file being parsed.
     */
    protected $filePath;

    /**
     * @var mixed The internal cache, keeps parsed object information during a request.
     */
    protected static $cache = [];

    /**
     * @var string dataCacheKey is the key for the parsed PHP file information cache.
     */
    protected $dataCacheKey = '';

    /**
     * __construct
     * @param \Cms\Classes\CmsCompoundObject A reference to a CMS object to parse.
     */
    public function __construct(CmsCompoundObject $object)
    {
        $this->object = $object;
        $this->filePath = $object->getFilePath();
        $this->dataCacheKey = 'cms_code_parser_'.$object->theme->getDirName();
    }

    /**
     * parse the CMS object's PHP code section and returns an array with the following keys:
     * - className
     * - filePath (path to the parsed PHP file)
     * - offset (PHP section offset in the template file)
     * - source ('parser', 'request-cache', or 'cache')
     * @return array
     */
    public function parse()
    {
        // If the object has already been parsed in this request return the cached data.
        if (array_key_exists($this->filePath, self::$cache)) {
            self::$cache[$this->filePath]['source'] = 'request-cache';
            return self::$cache[$this->filePath];
        }

        // Try to load the parsed data from the cache
        $path = $this->getCacheFilePath();

        $result = [
            'filePath' => $path,
            'className' => null,
            'source' => null,
            'offset' => 0
        ];

        // There are two types of possible caching scenarios, either stored
        // in the cache itself, or stored as a cache file. In both cases,
        // make sure the cache is not stale and use it.
        if (is_file($path)) {
            $cachedInfo = $this->getCachedFileInfo();
            $hasCache = $cachedInfo !== null;

            // Valid cache, return result
            if ($hasCache && $cachedInfo['mtime'] == $this->object->mtime) {
                $result['className'] = $cachedInfo['className'];
                $result['source'] = 'cache';

                return self::$cache[$this->filePath] = $result;
            }

            // Cache expired, cache file not stale, refresh cache and return result
            if (!$hasCache && filemtime($path) >= $this->object->mtime) {
                $className = $this->extractClassFromFile($path);
                if ($className) {
                    $result['className'] = $className;
                    $result['source'] = 'file-cache';

                    $this->storeCachedInfo($result);
                    return $result;
                }
            }
        }

        $result['className'] = $this->rebuild($path);
        $result['source'] = 'parser';

        $this->storeCachedInfo($result);
        return $result;
    }

   /**
    * rebuild the current file cache.
    * @param string The path in which the cached file should be stored
    */
    protected function rebuild($path)
    {
        $className = 'Cms'.hash('sha256', $path).'Class';

        $count = 0;
        while (class_exists($className)) {
            $className = 'Cms'.hash('sha256', $path.$count).'Class';
            if ($count++ > 100) {
                throw new SystemException('Maximum call stack exceeded for Cms\Classes\CodeParser class name generation.');
            }
        }

        $body = (string) $this->object->code;
        $body = preg_replace('/^\s*function/m', 'public function', $body);

        $pattern = '/(use\s+[a-z0-9_\\\\]+(\s+as\s+[a-z0-9_]+)?;\n?)/mi';
        preg_match_all($pattern, $body, $namespaces);
        $body = preg_replace($pattern, '', $body);

        $parentClass = $this->object->getCodeClassParent();
        if ($parentClass !== null) {
            $parentClass = ' extends '.$parentClass;
        }

        $fileContents = '<?php '.PHP_EOL;

        foreach ($namespaces[0] as $namespace) {
            $fileContents .= $namespace;
        }

        $fileContents .= 'class '.$className.$parentClass.PHP_EOL;
        $fileContents .= '{'.PHP_EOL;
        $fileContents .= $body.PHP_EOL;
        $fileContents .= '}'.PHP_EOL;

        $this->validate($fileContents);

        $this->makeDirectorySafe(dirname($path));

        $this->writeContentSafe($path, $fileContents);

        return $className;
    }

    /**
     * source runs the object's PHP file and returns the corresponding object.
     * @param \Cms\Classes\Page $page Specifies the CMS page.
     * @param \Cms\Classes\Layout $layout Specifies the CMS layout.
     * @param \Cms\Classes\Controller $controller Specifies the CMS controller.
     * @return mixed
     */
    public function source($page, $layout, $controller)
    {
        $data = $this->parse();
        $className = $data['className'];

        if (!class_exists($className)) {
            require_once $data['filePath'];
        }

        // Handle corrupt cache during concurrent access
        $count = 0;
        while (!class_exists($className)) {
            if ($count !== 0) {
                usleep(rand(50000, 200000));
            }

            $data = $this->handleCorruptCache($data);
            $className = $data['className'];

            if ($count++ > 10) {
                $path = $data['filePath'] ?? $this->getCacheFilePath();
                throw new SystemException(Lang::get('system::lang.file.create_fail', ['name'=>$path]));
            }
        }

        return new $className($page, $layout, $controller);
    }

    /**
     * handleCorruptCache in some rare cases the cache file will not contain the class
     * name we expect. When this happens, destroy the corrupt file,
     * flush the request cache, and repeat the cycle.
     * @return void
     */
    protected function handleCorruptCache($data)
    {
        $path = $data['filePath'] ?? $this->getCacheFilePath();

        if (is_file($path)) {
            if (($className = $this->extractClassFromFile($path)) && class_exists($className)) {
                $data['className'] = $className;
                return $data;
            }

            @unlink($path);
        }

        unset(self::$cache[$this->filePath]);

        return $this->parse();
    }

    //
    // Cache
    //

    /**
     * storeCachedInfo stores result data inside cache.
     * @param array $result
     * @return void
     */
    protected function storeCachedInfo($result)
    {
        $cacheItem = $result;
        $cacheItem['mtime'] = $this->object->mtime;

        $cached = $this->getCachedInfo() ?: [];
        $cached[$this->filePath] = $cacheItem;

        $toStore = base64_encode(serialize($cached));

        $minutes = Config::get('cms.template_cache_ttl', 1440);
        if ($minutes < 0) {
            Cache::forever($this->dataCacheKey, $toStore);
        }
        else {
            $expiresAt = now()->addMinutes($minutes);
            Cache::put($this->dataCacheKey, $toStore, $expiresAt);
        }

        self::$cache[$this->filePath] = $result;
    }

    /**
     * getCacheFilePath returns path to the cached parsed file
     * @return string
     */
    protected function getCacheFilePath()
    {
        $hash = md5($this->filePath);
        $result = storage_path().'/cms/cache/';
        $result .= substr($hash, 0, 2).'/';
        $result .= substr($hash, 2, 2).'/';
        $result .= basename($this->filePath);
        $result .= '.php';

        return $result;
    }

    /**
     * getCachedInfo returns information about all cached files.
     * @return mixed Returns an array representing the cached data or NULL.
     */
    protected function getCachedInfo()
    {
        $cached = Cache::memo()->get($this->dataCacheKey, false);

        if (
            $cached !== false &&
            ($cached = @unserialize(@base64_decode($cached))) !== false
        ) {
            return $cached;
        }

        return null;
    }

    /**
     * getCachedFileInfo returns information about a cached file
     * @return integer
     */
    protected function getCachedFileInfo()
    {
        $cached = $this->getCachedInfo();

        if ($cached !== null && array_key_exists($this->filePath, $cached)) {
            return $cached[$this->filePath];
        }

        return null;
    }

    //
    // Helpers
    //

    /**
     * validate evaluates PHP content in order to detect syntax errors.
     * The method handles PHP errors and throws exceptions.
     */
    protected function validate($php)
    {
        eval('?>'.$php);
    }

    /**
     * extractClassFromFile extracts the class name from a cache file
     * @return string
     */
    protected function extractClassFromFile($path)
    {
        try {
            $fileContent = File::sharedGet($path);
            $matches = [];
            $pattern = '/Cms\S+_\S+Class/';
            preg_match($pattern, $fileContent, $matches);

            if (!empty($matches[0])) {
                return $matches[0];
            }
        }
        catch (Exception $ex) {
        }

        return null;
    }

    /**
     * writeContentSafe writes content with concurrency support and cache busting
     * This work is based on the Twig\Cache\FilesystemCache class
     */
    protected function writeContentSafe($path, $content)
    {
        $count = 0;
        $tmpFile = tempnam(dirname($path), basename($path));

        if (@file_put_contents($tmpFile, $content) === false) {
            throw new SystemException(Lang::get('system::lang.file.create_fail', ['name'=>$tmpFile]));
        }

        while (!@rename($tmpFile, $path)) {
            usleep(rand(50000, 200000));

            if ($count++ > 10) {
                throw new SystemException(Lang::get('system::lang.file.create_fail', ['name'=>$path]));
            }
        }

        File::chmod($path);

        /*
         * Compile cached file into bytecode cache
         */
        if (Config::get('cms.force_bytecode_invalidation', false)) {
            if (function_exists('opcache_invalidate') && ini_get('opcache.enable')) {
                opcache_invalidate($path, true);
            }
            elseif (function_exists('apc_compile_file')) {
                apc_compile_file($path);
            }
        }
    }

    /**
     * makeDirectorySafe makes a directory with concurrency support
     */
    protected function makeDirectorySafe($dir)
    {
        $count = 0;

        if (is_dir($dir)) {
            if (!is_writable($dir)) {
                throw new SystemException(Lang::get('system::lang.directory.create_fail', ['name'=>$dir]));
            }

            return;
        }

        while (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            usleep(rand(50000, 200000));

            if ($count++ > 10) {
                throw new SystemException(Lang::get('system::lang.directory.create_fail', ['name'=>$dir]));
            }
        }

        File::chmodRecursive($dir);
    }
}
