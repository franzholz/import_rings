<?php
namespace JambageCom\ImportRings\Slots;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

use JambageCom\Import\Api\Api;


/**
 * Class for example slots to import files into TYPO3 tables
 */
class ImportSlots implements \TYPO3\CMS\Core\SingletonInterface
{
    protected $tables = array('tt_products');
    protected $baseProduct = array();
    protected $variant = 'material';

    /**
     * Constructor
     */
    public function __construct ()
    {
        $languageFile = 'EXT:' . IMPORT_RINGS_EXT . '/Resources/Private/Language/locallang.xlf';
        $this->getLanguageService()->includeLLFile($languageFile);
    }

    public function getTables () {
        return $this->tables;
    }

    /**
     * Adds entries to the menu selector of the import extension
     *
     * @return mixed[] Array with entries for the import menu
     */
    public function getMenu (
        $pObj,
        array $menu
    )
    {
        $tables = $this->getTables();
        foreach ($tables as $table) {
            $menuItem = $this->getLanguageService()->getLL('menu.' . $table);
            $menu[$table] = $menuItem;
        }
        $result = array($pObj, $menu);
        return $result;
    }

    /**
     * imports into the tables tt_products and tt_products_articles if tt_products is part of the given tables
     *
     * @return mixed[] Array with entries for the import menu
     */
    public function importTables (
        $pObj,
        $pid,
        array $paramTables
    )
    {
        debug ($paramTables, 'import $paramTables +++');
            // Rendering of the output via fluid
        $api = GeneralUtility::makeInstance(Api::class);

        $tables = $this->getTables();
        foreach ($tables as $table) {
            if (in_array($table, $paramTables)) {
                switch ($table) {
                case 'tt_products' :
                        // import the base product
                    $file =
                        GeneralUtility::getFileAbsFileName(
                            'EXT:' . IMPORT_RINGS_EXT . '/Resources/Private/Files/product-base.csv'
                        );

            debug ($file, 'import $file +++');
                    $mode = 0;
                    $api->importTableFile($table, $file, $pid, "\t", '"', $mode, true);

                        // import the products and articles
                    $file =
                        GeneralUtility::getFileAbsFileName(
                            'EXT:' . IMPORT_RINGS_EXT . '/Resources/Private/Files/product-article.csv'
                        );
                    $mode = 1;
                    $api->importTableFile($table, $file, $pid, "\t", '"', $mode, true);
                    break;
                }
            }
        }
    }

    /**
     * reads the titles of the variant fields
     * It does nothing. This information can be used later.
     *
     * @return void
     */
    public function setBaseProductTitle ($row)
    {
        debug ($row, 'setBaseProductTitle $row');
    }

    public function getBaseProduct ()
    {
        return $this->baseProduct;
    }

    public function getVariant ()
    {
        return $this->variant;
    }

    /**
     * Set the row of the default product.
     * This will be used in the next file imports to merge new inserted products with it.
     *
     * @return void
     */
    public function setBaseProduct ($row)
    {
        debug ($row, 'setBaseProduct $row');
        $this->baseProduct = $row;
    }

    /**
     * modifies the given row of a table during the import
     *
     * @return mixed[] Array with the parameters of the function call
     */
    public function processImport (
        $tableName,
        $row,
        $pid,
        $count,
        $mode
    )
    {
     debug ($tableName, 'processImport $tableName');
     debug ($row, '$row');
     debug ($pid, 'processImport $pid');
     debug ($count, 'processImport $count');
        if ($tableName == 'tt_products') {
            switch ($mode) {
                case 0:
                    switch ($count) {
                        case '2':
                            $this->setBaseProductTitle($row);
                        break;
                        case '3':
                            if (!isset($row['pid'])) {
                                $row['pid'] = $pid;
                            }
                            $this->setBaseProduct($row);
                        break;
                    }
                    break;
                case 1:
     debug ($row, '$row mode 1');
                    $baseProductRow = $this->getBaseProduct();
                    if ($baseProductRow) {
                        $time = time();
                        if (!empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['serverTimeZone'])) {
                            $time += ($GLOBALS['TYPO3_CONF_VARS']['SYS']['serverTimeZone'] * 3600);
                        }

                        if (
                            $count &&
                            isset($row['Artikelnummer'])
                        ) {
     debug ($count, '$count > 0 mode 1');
//                                 $this->setBaseProduct($row);
                            $fieldArticleNumber = 'itemnumber';
                            $fieldTitle = 'title';
                            $articleNumber = trim($row['Artikelnummer']);
                            $where_clause = $fieldArticleNumber . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($articleNumber, $tableName);

                        debug ($where_clause, '$where_clause +++');

                            $currentRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
                                'uid',
                                $tableName,
                                $where_clause
                            );
                        debug ($currentRow, '$currentRow');
                            if ($currentRow) {
                                // This product already exists.
                                return;
                            }
                            $newProductRow = $baseProductRow;
                            $newProductRow[$fieldArticleNumber] = $articleNumber;
                            $newProductRow[$fieldTitle] = $articleNumber;
                            $baseArticleRow = array();
                            $baseArticleRow['crdate'] = $time;
                            $baseArticleRow['tstamp'] = $time;
                            $configFile =
                                GeneralUtility::getFileAbsFileName(
                                    'EXT:' . IMPORT_RINGS_EXT . '/Resources/Private/Files/config.xml'
                                );
                            $xml = simplexml_load_file($configFile);
                            $baseArticleRow['config'] = $xml->asXML();
                        debug ($baseArticleRow, '$baseArticleRow +++');

                            $priceRow = array();
                            foreach ($row as $key => $value) {
                                $key = trim($key);
                                if ($key == 'Artikelnummer') {
                                    $baseArticleRow[$fieldArticleNumber] = $articleNumber;
                                } else {
                                debug ($key, '$key');
                                    $parts = explode(' ', $key);
                                debug ($parts, '$parts');
                                    $variant = $parts['0'];
                                debug ($variant, '$variant');
                                    if (!isset($priceRow[$variant])) {
                                        $priceRow[$variant] = 0;
                                    }
                                    $priceRow[$variant] += $value;
                                }
                            }
                            $variantField = $this->getVariant();
                            debug ($variantField, '$variantField +++');

                            debug ($priceRow, '$priceRow +++');
                            $variantArray = array_keys($priceRow);
                            debug ($variantArray, '$variantArray +++');
                            $newProductRow['article_uid'] = count($priceRow);
                            $newProductRow['crdate'] = $time;
                            $newProductRow['tstamp'] = $time;

                            $newProductRow[$variantField] = implode(';', $variantArray);
                            $articleRows = array();
                            foreach ($priceRow as $variant => $price) {
                                $articleRow = $baseArticleRow;
                                $articleRow[$fieldTitle] = $articleNumber . '-' . $variant;
                                $articleRow[$variantField] = str_replace(',', ';', $variant);
                                $articleRow['price'] = $price;
                                $articleRow['pid'] = $pid;
                                $articleRows[] = $articleRow;
                            }

                        debug ($newProductRow, '$newProductRow +++');
                        debug ($articleRows, '$articleRows +++');
                            $GLOBALS['TYPO3_DB']->exec_INSERTquery(
                                $tableName,
                                $newProductRow
                            );
                            $insertProductUid = $GLOBALS['TYPO3_DB']->sql_insert_id();
                        debug ($newProductRow, '$newProductRow +++');
                        debug ($insertProductUid, '$insertProductUid +++');

                            $insertUids = array();
                            foreach ($articleRows as $articleRow) {
                        debug ($articleRow, '$articleRow +++');

                                $GLOBALS['TYPO3_DB']->exec_INSERTquery(
                                    'tt_products_articles',
                                    $articleRow
                                );
                                $insertUids[] = $GLOBALS['TYPO3_DB']->sql_insert_id();
                            }
                        debug ($insertUids, '$insertUids +++');

                            foreach ($insertUids as $insertUid) {
                                $insertRow = array();
                                $insertRow['pid'] = $pid;
                                $insertRow['crdate'] = $time;
                                $insertRow['tstamp'] = $time;
                                $insertRow['uid_local'] = $insertProductUid;
                                $insertRow['uid_foreign'] = $insertUid;
                        debug ($insertRow, '$insertRow +++');

                                $GLOBALS['TYPO3_DB']->exec_INSERTquery(
                                    'tt_products_products_mm_articles',
                                    $insertRow
                                );
                            }

//                             public function INSERTquery($table, $fields_values);
                        }
                    }
                    break;
            }
        }

        $result = array(
            $tableName,
            $row,
            $pid,
            $count,
            $mode
        );
        return $result;
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}

