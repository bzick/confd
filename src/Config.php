<?php

namespace Confd;


class Config implements \ArrayAccess {
    /**
     * @var array config storage
     */
	private $_main;
	/**
	 * @var array<string, \ArrayObject>
	 */
	private $_parts = [];
    /**
     * @var string
     */
	private $_backup_dir;
    /**
     * @var string
     */
	private $_conf_path;
    /**
     * @var string
     */
	private $_configs_dir;

	/**
	 * @param string $conf_path path to config file
	 * @param string $confd_dir path to directory with defaults
	 */
	public function __construct($conf_path, $confd_dir) {
		$this->_main        = includeFile($conf_path);
		$this->_conf_path   = $conf_path;
		$this->_configs_dir = $confd_dir;
	}

    /**
     * @return string
     */
	public function getConfigPath() {
	    return $this->_conf_path;
    }

    /**
     * @return string
     */
	public function getConfigsDirPath() {
	    return $this->_configs_dir;
    }

    /**
     * Sets backup dir for config
     * @see flush
     * @param string $backup_dir
     */
	public function setBackupDir($backup_dir) {
	    $this->_backup_dir = $backup_dir;
    }

    /**
     * @return string
     */
    public function getBackupDir() {
	    return $this->_backup_dir ? $this->_backup_dir : dirname($this->_conf_path);
    }

	/**
	 * Using cache via properties
	 * @param string $part
	 * @throws \LogicException
	 * @return \ArrayObject|array
	 */
	public function __get($part) {
		if (isset($this->_parts[$part])) {
			return $this->_parts[$part];
		}
		if (isset($this->_main[$part])) {
			$conf = $this->_main[$part];
		} else {
			$conf = array();
		}
		if (file_exists($this->_configs_dir.'/'.$part.'.php')) {
			if ($conf) {
				$conf += includeFile($this->_configs_dir.'/'.$part.'.php');
			} else {
				$conf = includeFile($this->_configs_dir.'/'.$part.'.php');
			}
		} elseif (!$conf) {
			return array();
		}
		return $this->_parts[$part] = new \ArrayObject($conf, \ArrayObject::ARRAY_AS_PROPS);
	}

	/**
	 * Alias of __get()
	 * @param string $part
	 * @return \ArrayObject
	 */
	public function get($part) {
		return $this->$part;
	}

    /**
     * Returns changes from user-config
     * @return array|mixed
     */
	public function getChanges() {
	    return $this->_main;
    }

	/**
     * Aggregate configs from path
	 * @param string $path
	 * @param bool $basename do basename() for key name
	 * @return array
	 */
	public function getAllFrom($path, $basename = true) {
		$result = array();
		foreach ($this->_main as $k => $v) {
			if (strpos($k, $path.'/') === 0) {
				if ($basename && $path.'/'.basename($k) == $k) {
					$result[basename($k)] = $v;
				} else {
					$result[$k] = $v;
				}
			}
		}
		if (is_dir($this->_configs_dir.'/'.$path)) {
			foreach (new \DirectoryIterator($this->_configs_dir.'/'.$path) as $file) {
				/** @var \SplFileInfo $file */
				if ($file->isFile()) {
					if ($basename) {
						$name = $file->getBasename('.'.$file->getExtension());
					} else {
						$name = $path.'/'.$file->getBasename('.'.$file->getExtension());
					}
					if (isset($result[$name])) {
						$result[$name] += includeFile($file->getRealPath());
					} else {
						$result[$name] = includeFile($file->getRealPath());
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Return default configuration for config part
	 * @param $part
	 * @return array
	 */
	public function getDefaults($part) {
		if (file_exists($this->_configs_dir.'/'.$part.'.php')) {
			return includeFile($this->_configs_dir.'/'.$part.'.php');
		} else {
			return array();
		}
	}

	/**
     * Recursive search for values in the config
	 * @param array $args
	 * @return array|null
	 */
	public function findPath(array $args) {
		if (!$args) {
			return null;
		}
		$conf = $this;
		foreach ($args as $arg) {
			if (isset($conf[$arg])) {
				$conf = $conf[$arg];
			} else {
				return null;
			}
		}
		return $conf;
	}

	/**
	 * Whether a offset exists
	 * @param mixed $offset
	 * An offset to check for.
	 * @return boolean true on success or false on failure.
	 * The return value will be casted to boolean if non-boolean was returned.
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists($offset) {
		return isset($this->_parts[$offset]) || isset($this->_main[$offset]) || file_exists($this->_configs_dir.'/'.$offset.'.php');
	}

	/**
	 * Offset to retrieve
	 * @param mixed $offset
	 * @return mixed Can return all value types.
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($offset) {
		return $this->$offset;
	}

	/**
	 * Redefine keys in config parts
	 * @param mixed $offset part name
	 * @param array $value keys.
	 * @throws \LogicException
	 * @return void
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value) {
		if (isset($this->_parts[$offset])) {
			if (!is_array($value)) {
				throw new \LogicException('Unexpected type ' . gettype($value) . ', expected array');
			}
			foreach ($value as $k => $v) {
				$this->_parts[$offset][$k] = $v;
			}
		} else {
			$this->_main[$offset] = $value;
		}
	}

	/**
	 * Offset to unset
	 * @param mixed $offset
	 * @return void
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset($offset) {
		unset($this->_parts[$offset]);
	}

	/**
	 * Set key to part of config
	 * @param string $part
	 * @param string $key
	 * @param string $value
	 */
	public function set($part, $key, $value) {
		$this->_main[$part][$key] = $value;
		unset($this->_parts[$part]);
	}

	/**
	 * Remove config part or key
	 * @param string $part
	 * @param string $key
	 * @return bool
	 */
	public function remove($part, $key = null) {
		if (!isset($this->_main[$part])) {
			return false;
		}

		if ($key) {
			if (!isset($this->_main[$part][$key])) {
				return false;
			}
			unset($this->_main[$part][$key]);
		} else {
			unset($this->_main[$part]);
		}
		return true;
	}

	/**
	 * Undo last config changes, not save
	 */
	public function undo() {
		$this->reload($this->_conf_path . ".bak.php");
	}

	/**
	 * DANGER!! Flush changed config to disk and backup old one
	 * @return bool
	 * @throws \Exception
	 */
	public function flush() {
		if ($this->_main !== includeFile($this->_conf_path)) { // if has changes
			$tmp = $this->_conf_path . ".tmp.php";
			$bak = $this->_conf_path . ".bak.php";

			file_put_contents($tmp, "<?php\n\nreturn " . $this->varExport($this->_main) . ";");
			if (strpos($e = exec("php -l $tmp"), 'No syntax errors detected') === false) {
				throw new \RuntimeException("Error in tmp file: $e\n\nvim $tmp");
			}
			return copy($this->_conf_path, $bak) && rename($tmp, $this->_conf_path);
		}
		return false;
	}

	/**
	 * @param mixed $var
	 * @param string $indent
	 * @return mixed|string
	 */
	private function varExport($var, $indent = '') {
		switch (gettype($var)) {
			case "array":
				$indx = array_keys($var) === range(0, count($var) - 1);
				$r = array();
				foreach ($var as $k => $v) {
					$r[] = "$indent\t" .($indx ? '' : $this->varExport($k) .' => ') .$this->varExport($v, "$indent\t");
				}
				return "[\n" . implode(",\n", $r) . "\n$indent]";
            /** @noinspection PhpMissingBreakStatementInspection */
            case "object":
                if ($var instanceof \Iterator) {
                    return iterator_to_array($var);
                } elseif ($var instanceof \JsonSerializable) {
                    return $var->jsonSerialize();
                }
                // no break
			default:
				return var_export($var, 1);
		}
	}

    /**
     * Перечитываем конфиг заново
     *
     * @param string $file
     */
	public function reload($file = '') {
	    $file = $file ?: $this->_conf_path;
	    if (file_exists($file)) {
            foreach (array_keys($this->_main) as $part) {
                unset($this->_parts[$part]);
                unset($this->_main[$part]);
            }
            foreach (array_keys($this->_parts) as $part) {
                unset($this->_parts[$part]);
            }
            $this->_main = includeFile($file);
        }
    }
}

/**
 * Scope isolated include.
 *
 * Prevents access to $this/self from included files.
 *
 * @param string $file
 *
 * @return mixed
 */
function includeFile($file)
{
    return include($file);
}
