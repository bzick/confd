<?php

namespace Confd;


use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
	/**
	 * @var Config
	 */
	public $config;

	protected function setUp()
    {
		$this->config = new Config(__DIR__.'/fixtures/main.php', __DIR__.'/fixtures/config.d');
	}

	/**
	 * @param string $property like Class::_property_name
	 * @param mixed $value
	 * @param mixed $obj - context
	 */
	protected function setPrivateProperty($property, $value, $obj = null) {
		list($class, $property) = explode('::', $property);
		$reflect = new \ReflectionClass($class);
		$prop = $reflect->getProperty($property);
		$prop->setAccessible(true);
		$prop->setValue($obj, $value);
	}

	public function testLoad() {
		$this->assertEquals('default value', $this->config->part1->default);
		$this->assertEquals(['ford', 'hyundai', 'kia'], $this->config->part1->cars);
		$this->assertEquals(1, $this->config->part1->one);
		$this->assertEquals('two', $this->config->part1->two);
		$this->assertEquals('pear', $this->config->part1->fruit);
	}

	public function testNested() {
		$this->assertEquals(23.98, $this->config->part2->planets['earth']['rotation_period']);
		$this->assertEquals(23.98, $this->config->get('part2')->planets['earth']['rotation_period']);
	}

	public function testGetWithPath() {
		$this->assertEquals(2, $this->config->{"parts/part5"}->deep);
		$this->assertEquals(2, $this->config->get('parts/part5')->deep);

	}

	public function testGetUsers() {
		$this->assertEquals('megagroup.ru', $this->config->part3->site);
		$this->assertEquals('megagroup.ru', $this->config->get('part3')->site);
	}

	public function testGetDefault() {
		$this->assertEquals('owner', $this->config->part4->access);
		$this->assertEquals('owner', $this->config->get('part4')->access);

		$this->assertEmpty($this->config->getDefaults("unknown".time()));
		$this->assertNotEmpty($this->config->getDefaults('part1'));
	}

	public function testFindPath() {
		$this->assertSame(23.98, $this->config->findPath(['part2', 'planets', 'earth', 'rotation_period']));
	}

	/**
	 * @group testGetAllFrom
	 */
	public function testGetAllFrom() {
		$expected = [
			'parts6' => [
				'part' => 7,
                'add-part' => 8,
			    'old-part' => 5,
			],
            'part5' => [
				'deep' => 2
            ]
		];
		$this->assertSame($expected, $this->config->getAllFrom('parts', true));
	}

	/**
	 * @covers ::set
	 */
	public function testSet() {
		$path  = 'any_path';
		$key   = 'any_key';
		$value = 'any_value';
		$this->config->set($path, $key, $value);
		$this->assertEquals($value, $this->config->$path->$key);
	}

	/**
	 * @covers ::remove
	 */
	public function testRemove() {
		$this->assertFalse($this->config->remove('nonexistent'));
		$this->assertFalse($this->config->remove('part2', 'nonexistent'));

		$this->assertEquals(3, $this->config->part2->three);
		$this->assertTrue($this->config->remove('part2', 'three'));
		$changes = $this->config->getChanges();
		$this->assertArrayNotHasKey('three', $changes['part2']);

		$this->assertTrue($this->config->remove('part2'));
		$this->assertArrayNotHasKey('part2', $this->config->getChanges());
	}

	/**
	 * @covers ::varExport
	 */
	public function testVarExport() {
		$method = new \ReflectionMethod('\Confd\Config', 'varExport');
		$method->setAccessible(true);
		$origin = $this->config->getChanges();
		$export = $method->invoke($this->config, $origin);
		$this->assertEquals($origin, eval("return $export;"));
	}

	/**
	 * @covers ::undo
	 */
	public function testUndo() {
		$origin = $this->config->getChanges();
		$bak = $this->config->getConfigPath() . ".bak.php";
		if (file_exists($bak)) {
			unlink($bak);
		}
		$this->config->undo();
		// no backup file => no changes
		$this->assertSame($origin, $this->config->getChanges());

		$data = ['test_key' => 'test_value'];
		file_put_contents($bak, "<?php return " . var_export($data, 1) . ";");
		$this->config->undo();
		$this->assertSame($data, $this->config->getChanges());
	}

	/**
	 * @covers ::flush
	 */
	public function testFlush() {
		$data1 = [
			'int'   => 123,
			'float' => 123.45,
			'text'  => 'string',
			'arr'   => [1,'2.3','four', true],
		];
		$cnf = __DIR__ . '/var/test_config.php';
		if (file_exists($cnf)) {
		    unlink($cnf);
        }
		file_put_contents($cnf, '<?php return '.var_export($data1, true).';');

		$config = new Config($cnf, __DIR__.'/fixtures/config.d');

		// config is not changed - nothing to do
		$this->assertFalse($config->flush());

		// set new data to config
		$data2 = [
			'int'   => 234,
			'float' => 234.56,
			'text'  => 'val',
			'arr'   => [2,'3.4','five', false],
		];
		$this->setPrivateProperty('\Confd\Config::_main', $data2, $config);

		$bak = $config->getConfigPath() . ".bak.php";

		$this->assertTrue($config->flush());
		$this->assertFileExists($bak);
		$this->assertSame($data1, include($bak));
		$this->assertSame($data2, include($cnf));

		unlink($bak);
	}
}