<?php
namespace unit;

class MdxHelperTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
		putenv('SIMPLESAMLPHP_CONFIG_DIR='.dirname(__DIR__).'/_data/config');
    }

    protected function _after()
    {
    }

    // tests
    public function testLoadRemote() {
		// Loading from invalid source
		ob_start();
		$data = \uhi67\mdxhelper\MdxHelper::loadRemote('https://rr.pte.hu/eduid-mdx', dirname(__DIR__, 3) .'/runtime/simplesaml/mdxhelper');
		$output = ob_get_clean();
		$this->assertEquals('', $output);

		// Loading valid data
		ob_start();
		$data = \uhi67\mdxhelper\MdxHelper::loadRemote('https://rr.pte.hu/eduid-mdx.php', dirname(__DIR__, 3) .'/runtime/simplesaml/mdxhelper');
		$output = ob_get_clean();
		$this->assertEquals('', $output);

    }
}
