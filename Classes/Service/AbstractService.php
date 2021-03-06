<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\Extractor\Service;

use Causal\Extractor\Service\ServiceInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Abstract service.
 *
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
abstract class AbstractService implements ServiceInterface
{

    /**
     * @var array
     */
    protected $settings;

    /**
     * AbstractService constructor.
     */
    public function __construct()
    {
        $this->settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['extractor']);
    }

    /**
     * Takes a file reference and extracts its metadata.
     *
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return array
     */
    public function extractMetadata(File $file)
    {
        $localTempFilePath = $file->getForLocalProcessing(false);
        $metadata = $this->extractMetadataFromLocalFile($localTempFilePath);
        $this->cleanupTempFile($localTempFilePath, $file);

        return $metadata;
    }

    /**
     * Removes a temporary file.
     *
     * When working with a file, the actual file might be on a remote storage.
     * To work with it it gets copied to local storage, those temporary local
     * copies need to be removed when they're not needed anymore.
     *
     * @param string $localTempFilePath Path to the local file copy
     * @param \TYPO3\CMS\Core\Resource\File $sourceFile Original file
     * @return void
     */
    protected function cleanupTempFile($localTempFilePath, File $sourceFile)
    {
        if (PathUtility::basename($localTempFilePath) !== $sourceFile->getName()) {
            unlink($localTempFilePath);
        }
    }

    /**
     * Escapes a shell argument (for example a filename) to be used on the local system.
     *
     * The setting UTF8filesystem will be taken into account.
     *
     * @param string $input Input-argument to be escaped
     * @return string Escaped shell argument
     * @internal This is an internal method which will be removed onced TYPO3 6.2 is not supported anymore
     */
    protected function escapeShellArgument($input)
    {
        if (version_compare(TYPO3_version, '7.1.0', '>=')) {
            $escapedInput = CommandUtility::escapeShellArgument($input);
        } else {
            if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
                $currentLocale = setlocale(LC_CTYPE, 0);
                setlocale(LC_CTYPE, $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale']);
            }
            $escapedInput = escapeshellarg($input);
            if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
                setlocale(LC_CTYPE, $currentLocale);
            }
        }
        return $escapedInput;
    }

}
