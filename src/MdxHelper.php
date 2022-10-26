<?php
namespace uhi67\mdxhelper;

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Metadata\MetaDataStorageSource;
use SimpleSAML\Metadata\Sources\MDQ;

class MdxHelper {
	/**
     * Loads an entity from the configured MDX/MDQ source into a (metadata) array.
     * For example an idp entity can be loaded into saml20-idp-remote in order to be shown in the local discovery selection.
     *
     *
	 * @param array $metadata -- tha array to load metadata into
	 * @param string $entity -- entity ID
	 * @return void
	 * @throws Exception -- if loaded metadata is expired (e.g. the source is unaccessible and cache is expired)
	 */
	public static function loadFromMdq(&$metadata, $entity) {
		$config = Configuration::getInstance();
		$sourcesConfig = $config->getArray('metadata.sources', null);
		$sources = MetaDataStorageSource::parseSources($sourcesConfig);
		$set = 'saml20-idp-remote';
		$metadataSet = null;
		foreach ($sources as $source) {
			if(!($source instanceof MDQ) && !is_a($source, 'SimpleSAML\Module\pte\MetadataStore\MDQ')) continue;
			try {
				$metadataSet = $source->getMetaData($entity, $set);
			} /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
                /** @noinspection PhpFullyQualifiedNameUsageInspection */
            catch(\Throwable $e) {
				$metadataSet = null;
			} /** @noinspection PhpWrongCatchClausesOrderInspection */
                /** @noinspection PhpRedundantCatchClauseInspection */
            catch(Exception $e) {
                $metadataSet = null;
            }
			if ($metadataSet !== null) {
				if (array_key_exists('expire', $metadataSet)) {
					if ($metadataSet['expire'] < time()) {
						throw new Exception(
							'Metadata for the entity [' . $entity . '] expired ' .
							(time() - $metadataSet['expire']) . ' seconds ago.'
						);
					}
				}
				break;
			}
		}
		if($metadataSet) {
			$idpMetadata = Configuration::loadFromArray($metadataSet, $set . '/' . var_export($entity, true));
			$metadata[$entity] = $idpMetadata->toArray();
		}
	}

    /**
     * Loads an array from URL in json format, caching.
     * If cached data exists and didn't expire, returns the cached data.
     * If the remote is inaccessible, returns the cached data even if it has expired.
     * If no cached data, returns [].
     * Logs errors and infos into SimpleSAMLphp configured log.
     *
     * @param string $url -- an URL returning the json data
     * @param string $cacheDir --
     * @param float|int $cacheTime
     * @return array
     * @throws Exception
     */
	public static function loadRemote($url, $cacheDir=null, $cacheTime=24*3600) {
		if($cacheDir) {
			if(!is_dir($cacheDir)) mkdir($cacheDir, 0774, true);
			$filename = $cacheDir.'/'.preg_replace('~[^\w_.-]+~', '_', parse_url($url, PHP_URL_HOST).'_'.parse_url($url, PHP_URL_PATH).'_'.parse_url($url, PHP_URL_QUERY));
			if(file_exists($filename) && filemtime($filename)+$cacheTime > time()) {
				$jsonData = file_get_contents($filename);
				Logger::info('MdxHelper::loadRemote using cached data.');
			}
			else {
				$default = function() use($filename) {
					if(file_exists($filename)) {
						Logger::info('MdxHelper::loadRemote fallback using cached data.');
						return file_get_contents($filename);
					}
					return false;
				};
				$store = function($jsonData) use ($filename) {
					file_put_contents($filename, $jsonData);
				};
				$jsonData = static::loadRemoteInner($url, $default, $store);
			}
		}
		else {
			$jsonData = static::loadRemoteInner($url);
		}
		return json_decode($jsonData, true, 20, JSON_OBJECT_AS_ARRAY);
	}

	/**
	 * Load (json) data from the given URL.
	 *
	 * If data is loaded from remote and $store callable is given, $store is called with the data.
	 * If loading fails, and $default callable is given, returns result of $default call.
	 *
	 * On error, logs error into SimpleSAMLphp log and returns null.
	 *
	 * @param string $url
	 * @param callable $default -- (string|bool) return the cached value if exists or false if not
	 * @param callable $store -- (void) store the value into the cache
	 *
	 * @return array|string
     * @throws Exception
	 */
	private static function loadRemoteInner($url, $default=null, $store=null) {
		try {
			$jsonData = @file_get_contents($url);
			if($jsonData && $store) {
				Logger::info('MdxHelper::loadRemote remote data is stored.');
				$store($jsonData);
			}
			$message = 'Url returned no data';
		} /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
        catch(\Throwable $e) {
			$jsonData = false;
			$message = $e->getMessage();
		} /** @noinspection PhpWrongCatchClausesOrderInspection */
            /** @noinspection PhpRedundantCatchClauseInspection */
        catch(Exception $e) {
            $jsonData = false;
            $message = $e->getMessage();
        }
		if(!$jsonData) {
			if($default) {
				// Loading from original source failed
				if(!is_callable($default)) {
					// Invalid $default callable
					Logger::error('MdxHelper::loadRemote default must be a callable or null');
				}
				if(is_callable($default) && ($jsonData = $default()) && is_array($jsonData)) return $jsonData;
				// Loading from default value also failed
				Logger::warning('MdxHelper::loadRemote URL returned no data and default source returned no valid data.');
			}
			else {
				Logger::warning('MdxHelper::loadRemote failed: '.$message);
			}
			return '[]';
		}
		return $jsonData;
	}

}
