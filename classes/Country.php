<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 * @author    Thirty Bees <contact@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/**
 * Class CountryCore
 *
 * @since 1.0.0
 */
class CountryCore extends ObjectModel
{
    // @codingStandardsIgnoreStart
    protected static $_idZones = [];
    protected static $cache_iso_by_id = [];
    public $id;
    /** @var int Zone id which country belongs */
    public $id_zone;
    /** @var int Currency id which country belongs */
    public $id_currency;
    /** @var string 2 letters iso code */
    public $iso_code;
    /** @var int international call prefix */
    public $call_prefix;
    /** @var string Name */
    public $name;
    /** @var bool Contain states */
    public $contains_states;
    /** @var bool Need identification number dni/nif/nie */
    public $need_identification_number;
    /** @var bool Need Zip Code */
    public $need_zip_code;
    /** @var string Zip Code Format */
    public $zip_code_format;
    /** @var bool Display or not the tax incl./tax excl. mention in the front office */
    public $display_tax_label = true;
    /** @var bool Status for delivery */
    public $active = true;
    // @codingStandardsIgnoreEnd

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'        => 'country',
        'primary'      => 'id_country',
        'multilang'    => true,
        'fields'       => [
            'id_zone'                    => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_currency'                => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'call_prefix'                => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'iso_code'                   => ['type' => self::TYPE_STRING, 'validate' => 'isLanguageIsoCode', 'required' => true, 'size' => 3],
            'active'                     => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'contains_states'            => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
            'need_identification_number' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
            'need_zip_code'              => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'zip_code_format'            => ['type' => self::TYPE_STRING, 'validate' => 'isZipCodeFormat'],
            'display_tax_label'          => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],

            /* Lang fields */
            'name'                       => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => 64],
        ],
        'associations' => [
            'zone'     => ['type' => self::HAS_ONE],
            'currency' => ['type' => self::HAS_ONE],
        ],
    ];

    protected $webserviceParameters = [
        'objectsNodeName' => 'countries',
        'fields'          => [
            'id_zone'     => ['xlink_resource' => 'zones'],
            'id_currency' => ['xlink_resource' => 'currencies'],
        ],
    ];

    /**
     * @param $idShop
     * @param $idLang
     *
     * @return array|false|mysqli_result|null|PDOStatement|resource
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getCountriesByIdShop($idShop, $idLang)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS(
            '
		SELECT *
		FROM `'._DB_PREFIX_.'country` c
		LEFT JOIN `'._DB_PREFIX_.'country_shop` cs ON (cs.`id_country`= c.`id_country`)
		LEFT JOIN `'._DB_PREFIX_.'country_lang` cl ON (c.`id_country` = cl.`id_country` AND cl.`id_lang` = '.(int) $idLang.')
		WHERE `id_shop` = '.(int) $idShop
        );
    }

    /**
     * Get a country ID with its iso code
     *
     * @param string $isoCode Country iso code
     * @param bool   $active  return only active coutries
     *
     * @return int Country ID
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getByIso($isoCode, $active = false)
    {
        if (!Validate::isLanguageIsoCode($isoCode)) {
            die(Tools::displayError());
        }
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            '
			SELECT `id_country`
			FROM `'._DB_PREFIX_.'country`
			WHERE `iso_code` = \''.pSQL(strtoupper($isoCode)).'\''
            .($active ? ' AND active = 1' : '')
        );

        if (isset($result['id_country'])) {
            return (int) $result['id_country'];
        }

        return false;
    }

    /**
     * @param $idCountry
     *
     * @return bool|int
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getIdZone($idCountry)
    {
        if (!Validate::isUnsignedId($idCountry)) {
            die(Tools::displayError());
        }

        if (isset(self::$_idZones[$idCountry])) {
            return (int) self::$_idZones[$idCountry];
        }

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            '
		SELECT `id_zone`
		FROM `'._DB_PREFIX_.'country`
		WHERE `id_country` = '.(int) $idCountry
        );

        if (isset($result['id_zone'])) {
            self::$_idZones[$idCountry] = (int) $result['id_zone'];

            return (int) $result['id_zone'];
        }

        return false;
    }

    /**
     * Get a country name with its ID
     *
     * @param int $idLang    Language ID
     * @param int $idCountry Country ID
     *
     * @return string Country name
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getNameById($idLang, $idCountry)
    {
        $key = 'country_getNameById_'.$idCountry.'_'.$idLang;
        if (!Cache::isStored($key)) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                '
							SELECT `name`
							FROM `'._DB_PREFIX_.'country_lang`
							WHERE `id_lang` = '.(int) $idLang.'
							AND `id_country` = '.(int) $idCountry
            );
            Cache::store($key, $result);

            return $result;
        }

        return Cache::retrieve($key);
    }

    /**
     * Get a country iso with its ID
     *
     * @param int $idCountry Country ID
     *
     * @return string Country iso
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getIsoById($idCountry)
    {
        if (!isset(Country::$cache_iso_by_id[$idCountry])) {
            Country::$cache_iso_by_id[$idCountry] = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                '
			SELECT `iso_code`
			FROM `'._DB_PREFIX_.'country`
			WHERE `id_country` = '.(int) $idCountry
            );
        }
        if (isset(Country::$cache_iso_by_id[$idCountry])) {
            return Country::$cache_iso_by_id[$idCountry];
        }

        return false;
    }

    /**
     * Get a country id with its name
     *
     * @param int    $idLang  Language ID
     * @param string $country Country Name
     *
     * @return int Country ID
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getIdByName($idLang = null, $country)
    {
        $sql = '
		SELECT `id_country`
		FROM `'._DB_PREFIX_.'country_lang`
		WHERE `name` = \''.pSQL($country).'\'';
        if ($idLang) {
            $sql .= ' AND `id_lang` = '.(int) $idLang;
        }

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        if (isset($result['id_country'])) {
            return (int) $result['id_country'];
        }

        return false;
    }

    /**
     * @param $idCountry
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getNeedZipCode($idCountry)
    {
        if (!(int) $idCountry) {
            return false;
        }

        return (bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            '
		SELECT `need_zip_code`
		FROM `'._DB_PREFIX_.'country`
		WHERE `id_country` = '.(int) $idCountry
        );
    }

    /**
     * @param $idCountry
     *
     * @return bool|false|null|string
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getZipCodeFormat($idCountry)
    {
        if (!(int) $idCountry) {
            return false;
        }

        $zipCodeFormat = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            '
		SELECT `zip_code_format`
		FROM `'._DB_PREFIX_.'country`
		WHERE `id_country` = '.(int) $idCountry
        );

        if (isset($zipCodeFormat) && $zipCodeFormat) {
            return $zipCodeFormat;
        }

        return false;
    }

    /**
     * Returns the default country Id
     *
     * @deprecated 1.0.0 use $context->country->id instead
     * @return int default country id
     */
    public static function getDefaultCountryId()
    {
        Tools::displayAsDeprecated();

        return Context::getContext()->country->id;
    }

    /**
     * @param $idZone
     * @param $idLang
     *
     * @return array|false|mysqli_result|null|PDOStatement|resource
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getCountriesByZoneId($idZone, $idLang)
    {
        if (empty($idZone) || empty($idLang)) {
            die(Tools::displayError());
        }

        $sql = ' SELECT DISTINCT c.*, cl.*
				FROM `'._DB_PREFIX_.'country` c
				'.Shop::addSqlAssociation('country', 'c', false).'
				LEFT JOIN `'._DB_PREFIX_.'state` s ON (s.`id_country` = c.`id_country`)
				LEFT JOIN `'._DB_PREFIX_.'country_lang` cl ON (c.`id_country` = cl.`id_country`)
				WHERE (c.`id_zone` = '.(int) $idZone.' OR s.`id_zone` = '.(int) $idZone.')
				AND `id_lang` = '.(int) $idLang;

        return Db::getInstance()->executeS($sql);
    }

    /**
     * @param $idCountry
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function containsStates($idCountry)
    {
        return (bool) Db::getInstance()->getValue(
            '
			SELECT `contains_states`
			FROM `'._DB_PREFIX_.'country`
			WHERE `id_country` = '.(int) $idCountry
        );
    }

    /**
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function delete()
    {
        if (!parent::delete()) {
            return false;
        }

        return Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'cart_rule_country WHERE id_country = '.(int) $this->id);
    }

    /**
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function isNeedDni()
    {
        return Country::isNeedDniByCountryId($this->id);
    }

    /**
     * @param $id_country
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function isNeedDniByCountryId($id_country)
    {
        return (bool) Db::getInstance()->getValue(
            '
			SELECT `need_identification_number`
			FROM `'._DB_PREFIX_.'country`
			WHERE `id_country` = '.(int) $id_country
        );
    }

    /**
     * @param array $idsCountries
     * @param int   $idZone
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function affectZoneToSelection($idsCountries, $idZone)
    {
        // cast every array values to int (security)
        $idsCountries = array_map('intval', $idsCountries);

        return Db::getInstance()->execute(
            '
		UPDATE `'._DB_PREFIX_.'country` SET `id_zone` = '.(int) $idZone.' WHERE `id_country` IN ('.implode(',', $idsCountries).')
		'
        );
    }

    /**
     * Replace letters of zip code format And check this format on the zip code
     *
     * @param string $zipCode
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function checkZipCode($zipCode)
    {
        $zipRegexp = '/^'.$this->zip_code_format.'$/ui';
        $zipRegexp = str_replace(' ', '( |)', $zipRegexp);
        $zipRegexp = str_replace('-', '(-|)', $zipRegexp);
        $zipRegexp = str_replace('N', '[0-9]', $zipRegexp);
        $zipRegexp = str_replace('L', '[a-zA-Z]', $zipRegexp);
        $zipRegexp = str_replace('C', $this->iso_code, $zipRegexp);

        return (bool) preg_match($zipRegexp, $zipCode);
    }

    /**
     * @param bool $autodate
     * @param bool $nullValues
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function add($autodate = true, $nullValues = false)
    {
        $return = parent::add($autodate, $nullValues) && self::addModuleRestrictions([], [['id_country' => $this->id]], []);

        return $return;
    }

    /**
     * @param array $shops
     * @param array $countries
     * @param array $modules
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function addModuleRestrictions(array $shops = [], array $countries = [], array $modules = [])
    {
        if (!count($shops)) {
            $shops = Shop::getShops(true, null, true);
        }

        if (!count($countries)) {
            $countries = Country::getCountries((int) Context::getContext()->cookie->id_lang);
        }

        if (!count($modules)) {
            $modules = Module::getPaymentModules();
        }

        $sql = false;
        foreach ($shops as $idShop) {
            foreach ($countries as $country) {
                foreach ($modules as $module) {
                    $sql .= '('.(int) $module['id_module'].', '.(int) $idShop.', '.(int) $country['id_country'].'),';
                }
            }
        }

        if ($sql) {
            $sql = 'INSERT IGNORE INTO `'._DB_PREFIX_.'module_country` (`id_module`, `id_shop`, `id_country`) VALUES '.rtrim($sql, ',');

            return Db::getInstance()->execute($sql);
        } else {
            return true;
        }
    }

    /**
     * @brief Return available countries
     *
     * @param int  $idLang        Language ID
     * @param bool $active        return only active coutries
     * @param bool $containStates return only country with states
     * @param bool $listStates    Include the states list with the returned list
     *
     * @return array Countries and corresponding zones
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getCountries($idLang, $active = false, $containStates = false, $listStates = true)
    {
        $countries = [];
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS(
            '
		SELECT cl.*,c.*, cl.`name` country, z.`name` zone
		FROM `'._DB_PREFIX_.'country` c '.Shop::addSqlAssociation('country', 'c').'
		LEFT JOIN `'._DB_PREFIX_.'country_lang` cl ON (c.`id_country` = cl.`id_country` AND cl.`id_lang` = '.(int) $idLang.')
		LEFT JOIN `'._DB_PREFIX_.'zone` z ON (z.`id_zone` = c.`id_zone`)
		WHERE 1'.($active ? ' AND c.active = 1' : '').($containStates ? ' AND c.`contains_states` = '.(int) $containStates : '').'
		ORDER BY cl.name ASC'
        );
        foreach ($result as $row) {
            $countries[$row['id_country']] = $row;
        }

        if ($listStates) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'state` ORDER BY `name` ASC');
            foreach ($result as $row) {
                if (isset($countries[$row['id_country']]) && $row['active'] == 1) { /* Does not keep the state if its country has been disabled and not selected */
                    $countries[$row['id_country']]['states'][] = $row;
                }
            }
        }

        return $countries;
    }
}
