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
		\SimpleSAML\Logger::info('MdxHelperTest'); // CLI esetén mindig stderr-re loggol!
    }

    protected function _after()
    {
    }

    // tests
    public function testLoadRemote() {
        $cacheDir = dirname(__DIR__) .'/_output/runtime/cache';

		// Loading from invalid source
		ob_start();
		$data = \uhi67\mdxhelper\MdxHelper::loadRemote('https://rr.pte.hu/eduid-mdx', $cacheDir);
		$output = ob_get_clean();
		// Check no stdout output is generate
		$this->assertEquals('', $output);
		// Check results
		$this->assertEquals([], $data);
		// Check error is logged

		// Loading valid data using cache
		ob_start();
		$data = \uhi67\mdxhelper\MdxHelper::loadRemote('https://rr.pte.hu/eduid-mdx.php', $cacheDir);
		$output = ob_get_clean();
		// Check no stdout output is generate
		$this->assertEquals('', $output);
		// Check results
		$this->assertEquals([
			'server' => 'https://mdx-2020.eduid.hu',
			'validateFingerprint' => 'C3:72:DC:75:4C:FA:BA:65:63:52:D9:6B:47:5B:44:7E:AA:F6:45:61',
		], $data);

		// Check data in cache directory
        $cacheFile = 'rr.pte.hu__eduid-mdx.php_';
        $this->assertFileExists($cacheDir.'/'.$cacheFile);
        $this->assertEquals('{"server":"https:\/\/mdx-2020.eduid.hu","validateFingerprint":"C3:72:DC:75:4C:FA:BA:65:63:52:D9:6B:47:5B:44:7E:AA:F6:45:61"}', file_get_contents($cacheDir.'/'.$cacheFile));

        // Loading from insecure (invalid https cert) source (logs an error and returns empty array despite the remote contains data)
        $data = \uhi67\mdxhelper\MdxHelper::loadRemote('https://rr.dev.pte.hu/eduid-mdx.php');
        $this->assertEquals([], $data);
    }

    public function testLoadFromMdq() {
        // Check MDQ server in config
        $config = \SimpleSAML\Configuration::getInstance();
        $sources = array_values(array_filter($config->getArray('metadata.sources'), function($source) { return in_array($source['type'], ['mdx', 'mdq']); }));
        $this->assertEquals($sources[0]['server'], 'https://mdx-2020.eduid.hu');
        $this->assertEquals($sources[0]['validateFingerprint'], 'C3:72:DC:75:4C:FA:BA:65:63:52:D9:6B:47:5B:44:7E:AA:F6:45:61');
        $cacheDir = $sources[0]['cachedir'];

        // Ensure mdx-cache dir exists
        if($cachedir = $sources[0]['cachedir']) {
            if(!is_dir($cachedir)) mkdir($cachedir, 0774, true);
            $this->assertTrue(is_dir($cachedir));
        }

        // Load metadata from mdq
        $metadata = [];
        $idp = 'https://idp.pte.hu/saml2/idp/metadata.php';
        \uhi67\mdxhelper\MdxHelper::loadFromMdq($metadata, $idp);
        $this->assertArrayHasKey($idp, $metadata);

        // Check data in cache directory
        $cacheFile = 'saml20-idp-remote-e1713ddc96e1b4eeac6218d35b1d2cbe41f5aea8.cached.xml';
        $this->assertFileExists($cacheDir.'/'.$cacheFile);
    }
}
