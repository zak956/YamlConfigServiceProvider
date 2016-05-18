<?php
/**
 * Created by PhpStorm.
 * User: zak956
 * Date: 13.05.16
 * Time: 16:46
 */

namespace zak956\Pimple;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Yaml\Yaml;


class YamlPimpleConfigServiceProvider implements ServiceProviderInterface, \ArrayAccess
{
    const DEFAULT_PREFIX = 'config';

    protected $filename;
    protected $replacements;
    protected $prefix;

    public function __construct($filename, array $replacements = array(), $prefix = null)
    {
        $this->filename = $filename;
        $this->prefix = $prefix;

        if ($replacements) {
            foreach ($replacements as $key => $value) {
                $this->replacements["%$key%"] = $value;
            }
        }
    }

    public function register(Container $pimple) {
        $config = $this->readConfig();

        if (is_array($config)) {
            $this->importSearch($config, $pimple);

            foreach ($config as $key => $value)
                if ('%' === substr($key, 0, 1))
                    $this->replacements[$key] = (string) $value;

            $this->merge($pimple, $config);
        }
    }

    private function importSearch(&$config, Container $pimple) {
        foreach ($config as $key => $value) {
            if ($key == 'imports') {
                foreach ($value as $resource) {
                    $base_dir = str_replace(basename($this->filename), '', $this->filename);
                    $new_config = new YamlPimpleConfigServiceProvider($base_dir . $resource['resource']);
                    $new_config->register($pimple);
                }
                unset($config['imports']);
            }
            if ('%' === substr($key, 0, 1))
                $this->replacements[$key] = (string) $value;
        }
    }

    private function merge(Container $pimple, array $config)
    {
        if ($this->prefix) {
            $config = array($this->prefix => $config);
        }

        foreach ($config as $key => $value) {
            if ($pimple->offsetExists($key) && is_array($value)) {
                $pimple->offsetSet($key, $this->mergeRecursively($pimple->offsetGet($key), $value));
            } else {
                $pimple->offsetSet($key, $this->doReplacements($value));
            }
        }
    }

    private function mergeRecursively(array $currentValue, array $newValue)
    {
        foreach ($newValue as $key => $value) {
            if (is_array($value) && isset($currentValue[$key])) {
                $currentValue[$key] = $this->mergeRecursively($currentValue[$key], $value);
            } else {
                $currentValue[$key] = $this->doReplacements($value);
            }
        }

        return $currentValue;
    }

    private function doReplacements($value)
    {
        if (!$this->replacements) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->doReplacements($v);
            }

            return $value;
        }

        if (is_string($value)) {
            return strtr($value, $this->replacements);
        }

        return $value;
    }

    private function readConfig()
    {
        if (!$this->filename) {
            throw new \RuntimeException('A valid configuration file must be passed before reading the config.');
        }

        if (!file_exists($this->filename)) {
            throw new \InvalidArgumentException(
                sprintf("The config file '%s' does not exist.", $this->filename));
        }

        $config = Yaml::parse(file_get_contents($this->filename));

        return is_null($config) ? [] : $config;

    }

    public function offsetExists($offset)
    {
        // TODO: Implement offsetExists() method.
    }

    public function offsetGet($offset)
    {
        // TODO: Implement offsetGet() method.
    }

    public function offsetSet($offset, $value)
    {
        // TODO: Implement offsetSet() method.
    }

    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }
}