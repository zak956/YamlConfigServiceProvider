<?php

/*
 * This file is part of YamlPimpleConfigServiceProvider.
 *
 * (c) Aliaksandr Zakashanski <zak956@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Pimple\Container;
use zak956\Pimple\YamlPimpleConfigServiceProvider;

/**
 * @author Aliaksandr Zakashanski <zak956@gmail.com>
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Jérôme Macias <jerome.macias@gmail.com>
 */
class YamlPimpleConfigServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideFilename
     */
    public function testRegisterWithoutReplacement($filename)
    {
        $pimple = new Container();

        $pimple->register(new YamlPimpleConfigServiceProvider($filename));

        $this->assertSame(true, $pimple['debug']);
        $this->assertSame('%data%', $pimple['data']);
    }

    /**
     * @dataProvider provideFilename
     */
    public function testRegisterWithReplacement($filename)
    {
        $pimple = new Container();

        $pimple->register(new YamlPimpleConfigServiceProvider($filename, array(
            'data' => 'test-replacement'
        )));

        $this->assertSame(true, $pimple['debug']);
        $this->assertSame('test-replacement', $pimple['data']);
    }

    /**
     * @dataProvider provideEmptyFilename
     */
    public function testEmptyConfigs($filename)
    {
        $readConfigMethod = new \ReflectionMethod('zak956\Pimple\YamlPimpleConfigServiceProvider', 'readConfig');
        $readConfigMethod->setAccessible(true);

        $this->assertEquals(
            array(),
            $readConfigMethod->invoke(new YamlPimpleConfigServiceProvider($filename))
        );
    }

    /**
     * @dataProvider provideReplacementFilename
     */
    public function testInFileReplacements($filename)
    {
        $pimple = new Container();

        $pimple->register(new YamlPimpleConfigServiceProvider($filename));

        $this->assertSame('/var/www', $pimple['%path%']);
        $this->assertSame('/var/www/web/images', $pimple['path.images']);
        $this->assertSame('/var/www/upload', $pimple['path.upload']);
        $this->assertSame('http://example.com', $pimple['%url%']);
        $this->assertSame('http://example.com/images', $pimple['url.images']);
    }

    /**
     * @dataProvider provideFilename
     */
    public function testConfigWithPrefix($filename)
    {
        $pimple = new Container();
        $pimple->register(new YamlPimpleConfigServiceProvider($filename, array(), 'prefix'));

        $this->assertNotNull($pimple['prefix']);
        $this->assertSame(true, $pimple['prefix']['debug']);
        $this->assertSame('%data%', $pimple['prefix']['data']);
    }

    /**
     * @dataProvider provideMergeFilename
     */
    public function testMergeConfigsWithPrefix($filenameBase, $filenameExtended)
    {
        $pimple = new Container();
        $pimple->register(new YamlPimpleConfigServiceProvider($filenameBase, array(), 'prefix'));
        $pimple->register(new YamlPimpleConfigServiceProvider($filenameExtended, array(), 'prefix'));

        $this->assertNotNull($pimple['prefix']);

        $this->assertSame('pdo_mysql', $pimple['prefix']['db.options']['driver']);
        $this->assertSame(null, $pimple['prefix']['db.options']['password']);

        $this->assertSame('123', $pimple['prefix']['myproject.test']['param1']);
        $this->assertSame('123', $pimple['prefix']['myproject.test']['param3']['param2A']);
        $this->assertSame(array(4, 5, 6), $pimple['prefix']['myproject.test']['param4']);

        $this->assertSame(array(1,2,3,4), $pimple['prefix']['test.noparent.key']['test']);
    }

    /**
     * @dataProvider provideMergeFilename
     */
    public function testConfigsWithMultiplePrefixes($filenameBase, $filenameExtended)
    {
        $pimple = new Container();
        $pimple->register(new YamlPimpleConfigServiceProvider($filenameBase, array(), 'base'));
        $pimple->register(new YamlPimpleConfigServiceProvider($filenameExtended, array(), 'extended'));

        $this->assertSame(null, $pimple['extended']['db.options']['password']);
        $this->assertSame('123', $pimple['base']['myproject.test']['param1']);
        $this->assertSame('123', $pimple['base']['myproject.test']['param3']['param2A']);
        $this->assertSame(array(4, 5, 6), $pimple['extended']['myproject.test']['param4']);

        $this->assertSame(array(1,2,3,4), $pimple['extended']['test.noparent.key']['test']);
    }

    /**
     * @dataProvider provideMergeFilename
     */
    public function testMergeConfigs($filenameBase, $filenameExtended)
    {
        $pimple = new Container();
        $pimple->register(new YamlPimpleConfigServiceProvider($filenameBase));
        $pimple->register(new YamlPimpleConfigServiceProvider($filenameExtended));

        $this->assertSame('pdo_mysql', $pimple['db.options']['driver']);
        $this->assertSame('utf8', $pimple['db.options']['charset']);
        $this->assertSame('127.0.0.1', $pimple['db.options']['host']);
        $this->assertSame('mydatabase', $pimple['db.options']['dbname']);
        $this->assertSame('root', $pimple['db.options']['user']);
        $this->assertSame(null, $pimple['db.options']['password']);

        $this->assertSame('123', $pimple['myproject.test']['param1']);
        $this->assertSame('456', $pimple['myproject.test']['param2']);
        $this->assertSame('123', $pimple['myproject.test']['param3']['param2A']);
        $this->assertSame('456', $pimple['myproject.test']['param3']['param2B']);
        $this->assertSame('456', $pimple['myproject.test']['param3']['param2C']);
        $this->assertSame(array(4, 5, 6), $pimple['myproject.test']['param4']);
        $this->assertSame('456', $pimple['myproject.test']['param5']);

        $this->assertSame(array(1,2,3,4), $pimple['test.noparent.key']['test']);
    }

    /**
     * @test
     * @expectedException Symfony\Component\Yaml\Exception\ParseException
     */
    public function invalidYamlShouldThrowException()
    {
        $pimple = new Pimple\Container();
        $pimple->register(new YamlPimpleConfigServiceProvider(__DIR__ . "/Fixtures/broken.yml"));
    }

    public function provideFilename()
    {
        return array(
            array(__DIR__ . "/Fixtures/config.yml"),
        );
    }

    public function provideReplacementFilename()
    {
        return array(
            array(__DIR__ . "/Fixtures/config_replacement.yml"),
        );
    }

    public function provideEmptyFilename()
    {
        return array(
            array(__DIR__ . "/Fixtures/config_empty.yml"),
        );
    }

    public function provideMergeFilename()
    {
        return array(
            array(__DIR__ . "/Fixtures/config_base.yml", __DIR__ . "/Fixtures/config_extend.yml"),
        );
    }
}
