<?php
namespace unit;

use Codeception\Util\Debug;
use SAML2\Compat\Ssp\Logger;
use SimpleSAML\Kernel;
use SimpleSAML\Module\core\Stats\Output\Log;

class MdxHelperTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

	public $logFile;

    protected function _before()
    {
		$configDir = dirname(__DIR__).'/_data/config';
		putenv('SIMPLESAMLPHP_CONFIG_DIR='.$configDir);
		$this->assertEquals($configDir, getenv('SIMPLESAMLPHP_CONFIG_DIR'));
		$config = \SimpleSAML\Configuration::getInstance();
		$this->assertEquals(\SimpleSAML\Logger::DEBUG, $config->getInteger('logging.level'));
		$this->assertEquals('simplesamlphp.log', $logFile = $config->getString('logging.logfile'));

		$kernel = new Kernel('main');
		$logdir = $kernel->getLogDir();
		if(!is_dir($logdir)) mkdir($logdir, 0774, true);
		$this->logFile = $logdir . $logFile; // $config->getString('loggingdir')
		\SimpleSAML\Logger::info('MdxHelperTest');
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
		// Check no stdout output is generate
		$this->assertEquals('', $output);
		// Check results
		$this->assertEquals([], $data);
		// Check error is logged

		// Loading valid data
		ob_start();
		$data = \uhi67\mdxhelper\MdxHelper::loadRemote('https://rr.pte.hu/eduid-mdx.php', dirname(__DIR__, 3) .'/runtime/simplesaml/mdxhelper');
		$output = ob_get_clean();
		// Check no stdout output is generate
		$this->assertEquals('', $output);
		// Check results
		$this->assertEquals([
			'type' => 'mdx',
			'server' => 'https://mdx-2020.eduid.hu',
			'validateFingerprint' => 'C3:72:DC:75:4C:FA:BA:65:63:52:D9:6B:47:5B:44:7E:AA:F6:45:61',
		], $data);
		// Check log
		$curDate = date('M, d H:i');
		if($this->logFile) $this->tester->assertFileContainsLastLine($this->logFile, '/$curDate:\d{2} simplesamlphp INFO [\w{10}] MdxHelper::loadRemote using cached data.');

    }
}
