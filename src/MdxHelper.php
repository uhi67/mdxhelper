<?php
namespace uhi67\mdxhelper;

use Exception;

class MdxHelper {
	/**
	 * @param array $metadata -- tha array to load metadata into
	 * @param string $idp -- entity ID
	 * @return void
	 * @throws Exception -- if loaded metadata is expired (e.g. the source is unaccessible and cache is expired)
	 */
	public static function loadFromMdq(&$metadata, $idp) {
		$config = \SimpleSAML\Configuration::getInstance();
		$sourcesConfig = $config->getArray('metadata.sources', null);
		$sources = \SimpleSAML\Metadata\MetaDataStorageSource::parseSources($sourcesConfig);
		$set = 'saml20-idp-remote';
		$metadataSet = null;
		foreach ($sources as $source) {
			if(!($source instanceof \SimpleSAML\Metadata\Sources\MDQ)) continue;
			try {
				$metadataSet = $source->getMetaData($idp, $set);
			}
			catch(Throwable $e) {
				$metadataSet = null;
			}
			if ($metadataSet !== null) {
				if (array_key_exists('expire', $metadataSet)) {
					if ($metadataSet['expire'] < time()) {
						throw new \Exception(
							'Metadata for the entity [' . $idp . '] expired ' .
							(time() - $metadataSet['expire']) . ' seconds ago.'
						);
					}
				}
				break;
			}
		}
		if($metadataSet) {
			$idpMetadata = \SimpleSAML\Configuration::loadFromArray($metadataSet, $set . '/' . var_export($idp, true));
			$metadata[$idp] = $idpMetadata->toArray();
		}
	}

	/**
	 * Loads an array from URL in json format, caching
	 *
	 * @param string $url -- an URL returning the json data
	 * @param string $cacheDir --
	 * @return array
	 */
	public static function loadRemote($url, $cacheDir=null, $cacheTime=24*3600) {
		if($cacheDir) {
			if(!is_dir($cacheDir)) mkdir($cacheDir, 0774, true);
			$filename = $cacheDir.'/'.preg_replace('~[^\w_.-]+~', '_', parse_url($url, PHP_URL_HOST).'_'.parse_url($url, PHP_URL_PATH).'_'.parse_url($url, PHP_URL_QUERY));
			if(file_exists($filename) && filemtime($filename)+$cacheTime > time()) {
				$jsonData = file_get_contents($filename);
			}
			else {
				$jsonData = static::loadRemoteInner($url, function() {
					return file_exists($filename) ? file_get_contents($filename) : false;
				}, function($jsonData) use ($filename) {
					file_put_contents($filename, $jsonData);
				});
			}
		}
		else {
			$jsonData = static::loadRemoteInner($url);
		}
		return json_decode($jsonData, true, 20, JSON_OBJECT_AS_ARRAY);
	}

	/**
	 * @param string $url
	 * @param callable $default -- (string|bool) return the cached value if exists or false if not
	 * @param callable $store -- (void) store the value into the cache
	 *
	 * @return void
	 * @throws Exception
	 */
	private static function loadRemoteInner($url, $default=null, $store=null) {
		try {
			$jsonData = file_get_contents($url);
			if($jsonData && $store) $store($jsonData);
		}
		catch(\Throwable $e) {
			$jsonData = false;
		}
		if(!$jsonData && (!$default || !($jsonData = $default()))) {
			throw new \Exception("Error loading data from '$url', ".$e->getMessage());
		}
		return $jsonData;
	}

}
