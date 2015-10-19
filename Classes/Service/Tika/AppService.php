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

namespace Causal\Extractor\Service\Tika;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A Tika service implementation using the tika-app-x.x.jar.
 *
 * @category    Service/Tika
 * @package     TYPO3
 * @subpackage  tx_extractor
 * @author      Ingo Renner <ingo@typo3.org>
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
class AppService extends AbstractTikaService
{

    /**
     * AppService constructor.
     */
    public function __construct()
    {
        parent::__construct();

        if (!is_file(GeneralUtility::getFileAbsFileName($this->settings['tika_jar_path'], false))) {
            throw new \RuntimeException(
                'Invalid path or filename for Tika application jar: ' . $this->settings['tika_jar_path'],
                1445096468
            );
        }

        if (!CommandUtility::checkCommand('java')) {
            throw new \RuntimeException('Could not find Java', 1445096476);
        }
    }

    /**
     * Returns the Tika version.
     *
     * @return string
     */
    public function getTikaVersion()
    {
        $version = '';
        $tikaCommand = CommandUtility::getCommand('java')
            . ' -jar ' . escapeshellarg(GeneralUtility::getFileAbsFileName($this->settings['tika_jar_path'], false))
            . ' --version';

        $shellOutput = array();
        CommandUtility::exec($tikaCommand, $shellOutput);
        $version = $shellOutput[0];

        return $version;
    }

    /**
     * Returns Java runtime information.
     *
     * @return array
     */
    public function getJavaRuntimeInfo()
    {
        $cmd = CommandUtility::getCommand('java');
        $info = array(
            'path' => $cmd,
        );

        $shellOutput = array();
        CommandUtility::exec($cmd . ' -version 2>&1', $shellOutput);
        if (!empty($shellOutput)) {
            if (preg_match('/^.*"(.+)"/', $shellOutput[0], $matches)) {
                $info['version'] = $matches[1];
            }
            if (!empty($shellOutput[1])) {
                $info['description'] = $shellOutput[1];
            }
        }

        return $info;
    }

    /**
     * Takes a file reference and extracts the text from it.
     *
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return string
     */
    public function extractText(File $file)
    {
        $localTempFilePath = $file->getForLocalProcessing(false);
        $tikaCommand = CommandUtility::getCommand('java')
            . ' -Dfile.encoding=UTF8' // forces UTF8 output
            . ' -jar ' . escapeshellarg(GeneralUtility::getFileAbsFileName($this->settings['tika_jar_path'], false))
            . ' -t'
            . ' ' . escapeshellarg($localTempFilePath);

        $extractedText = CommandUtility::exec($tikaCommand);
        $this->cleanupTempFile($localTempFilePath, $file);

        //$this->log('Text Extraction using local Tika', array(
        //    'file' => $file,
        //    'tika command' => $tikaCommand,
        //    'shell output' => $extractedText
        //));

        return $extractedText;
    }

    /**
     * Takes a file reference and extracts its metadata.
     *
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return array
     */
    public function extractMetaData(File $file)
    {
        $localTempFilePath = $file->getForLocalProcessing(false);
        $tikaCommand = CommandUtility::getCommand('java')
            . ' -Dfile.encoding=UTF8'
            . ' -jar ' . escapeshellarg(GeneralUtility::getFileAbsFileName($this->settings['tika_jar_path'], false))
            . ' -m'
            . ' ' . escapeshellarg($localTempFilePath);

        $shellOutput = array();
        CommandUtility::exec($tikaCommand, $shellOutput);
        $metaData = $this->shellOutputToArray($shellOutput);
        $this->cleanupTempFile($localTempFilePath, $file);

        //$this->log('Meta Data Extraction using local Tika', array(
        //    'file' => $file,
        //    'tika command' => $tikaCommand,
        //    'shell output' => $shellOutput,
        //    'meta data' => $metaData
        //));

        return $metaData;
    }

    /**
     * Takes a file reference and detects its content's language.
     *
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return string Language ISO code
     */
    public function detectLanguageFromFile(File $file)
    {
        $localTempFilePath = $file->getForLocalProcessing(false);
        $language = $this->detectLanguageFromLocalFile($localTempFilePath);

        $this->cleanupTempFile($localTempFilePath, $file);

        return $language;
    }

    /**
     * Takes a string as input and detects its language.
     *
     * @param string $input
     * @return string Language ISO code
     */
    public function detectLanguageFromString($input)
    {
        $tempFilePath = GeneralUtility::tempnam('Tx_Extractor_Tika_AppService_DetectLanguage');
        GeneralUtility::writeFile($tempFilePath, $input);

        // Detect language
        $language = $this->detectLanguageFromLocalFile($tempFilePath);

        // Cleanup
        unlink($tempFilePath);

        return $language;
    }

    /**
     * The actual language detection
     *
     * @param string $localFilePath Path to a local file
     * @return string The file content's language
     */
    protected function detectLanguageFromLocalFile($localFilePath)
    {
        $tikaCommand = CommandUtility::getCommand('java')
            . ' -Dfile.encoding=UTF8'
            . ' -jar ' . escapeshellarg(GeneralUtility::getFileAbsFileName($this->settings['tikaPath'], false))
            . ' -l'
            . ' ' . escapeshellarg($localFilePath);

        $language = trim(CommandUtility::exec($tikaCommand));

        //$this->log('Language Detection using local Tika', array(
        //    'file' => $localFilePath,
        //    'tika command' => $tikaCommand,
        //    'shell output' => $language
        //));

        return $language;
    }

    /**
     * Takes shell output from exec() and turns it into an array of key => value
     * pairs.
     *
     * @param array $shellOutput An array containing shell output from exec() with one line per entry
     * @return array key => value pairs
     */
    protected function shellOutputToArray(array $shellOutput)
    {
        $metadata = array();

        foreach ($shellOutput as $line) {
            list($key, $value) = explode(':', $line, 2);
            $value = trim($value);

            if (in_array($key, array('dc', 'dcterms', 'meta', 'tiff', 'xmp', 'xmpTPg'))) {
                // Dublin Core metadata and co
                $keyPrefix = $key;
                list($key, $value) = explode(':', $value, 2);

                $key = $keyPrefix . ':' . $key;
                $value = trim($value);
            }

            if (array_key_exists($key, $metadata)) {
                if ($metadata[$key] == $value) {
                    // first duplicate key hit, but also duplicate value
                    continue;
                }

                // Allow a meta data key to appear multiple times
                if (!is_array($metadata[$key])) {
                    $metadata[$key] = array($metadata[$key]);
                }

                // But do not allow duplicate values
                if (!in_array($value, $metadata[$key])) {
                    $metadata[$key][] = $value;
                }
            } else {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

}