MdxHelper
=========

version 1.1 -- 2022-10-26

A configuration helper for SimpleSAMLphp.

Using MdxHelper
---------------

### Prerequisites

- **simplesamlphp/simplesamlphp** component installed with composer. Not listed as explicit dependency.

### Installation

- `composer require uhi67/mdxhelper dev-master`

### Usage example in main config

	'metadata.sources' => [
		[
			'type' => 'flatfile'
		],
        // Default MDX config as last resort. MdxHelper::loadRemote returns empty array on failure. 
		array_merge([
                'type' => 'mdx', // Can also be 'pte:MDQ' if pte-module is loaded
                'server' => 'https://mdx-2020.eduid.hu',
                'cachedir' => dirname(__DIR__, 2) .'/_output/runtime/simplesaml/mdx-cache',
                'cachelength' => 86400,
                'validateFingerprint' => 'C3:72:DC:75:4C:FA:BA:65:63:52:D9:6B:47:5B:44:7E:AA:F6:45:61',
		    ],
            // Remote data overwrites default data
            \uhi67\mdxhelper\MdxHelper::loadRemote($mdxDataUrl, dirname(__DIR__, 3) .'/runtime/simplesaml/mdxhelper')
		),
	],

### Usage example in saml20-idp-remote

    // Load IdP entity from mdq and add to $metadata array
    \uhi67\mdxhelper\MdxHelper::loadFromMdq($metadata, 'https://idp.pte.hu/saml2/idp/metadata.php');

Security Warning
================

- MdxHelper::loadRemote should load data only from https sources. If cert is invalid, no data is returned.
- MdxHelper::loadFromMdq uses the first configured MDQ source. Always specify a 'validateFingerprint' value for security. 

Change log
==========

## 1.1 -- 2022-10-26

- php 7,8
- Recognize 'SimpleSAML\Module\pte\MetadataStore\MDQ' metadata source class as well

## 1.0 -- 2022-05-03

- first release
