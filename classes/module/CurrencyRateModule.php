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
 *  @author    Thirty Bees <contact@thirtybees.com>
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2017 Thirty Bees
 *  @copyright 2007-2016 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/**
 * Class CurrencyModuleCore
 *
 * @since 1.0.0
 */
abstract class CurrencyRateModuleCore extends Module
{
    /**
     * @param string $baseCurrency Uppercase base currency code
     *                             Only codes that have been added to the
     *                             `supportedCurrencies` array will be called.
     *                             The module will have to accept all currencies
     *                             from that array as a base.
     *
     * @return false|array Associate array with all supported currency codes as key (uppercase) and the actual
     *                     amounts as values (floats - be as accurate as you like), e.g.:
     *                     ```php
     *                     [
     *                         'EUR' => 1.233434,
     *                         'USD' => 1.343,
     *                     ]
     *                     ```
     *                     Returns `false`  if there were problems with retrieving the exchange rates
     *
     *
     * @since 1.0.0
     * @version 1.0.0 Initial version
     */
    abstract public function hookCurrencyRates($baseCurrency);

    /**
     * @param string $fromCurrency From currency code
     * @param string $toCurrency   To currency code
     *
     * @return false|float
     */
    abstract public function hookRate($fromCurrency, $toCurrency);

    /**
     * @return array Supported currencies
     *               An array with uppercase currency codes (ISO 4217)
     */
    abstract public function getSupportedCurrencies();

    /**
     * Install this module and scan currencies
     *
     * @return bool Indicates whether the module was successfully installed
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        self::scanMissingCurrencyRateModules();

        return true;
    }

    /**
     * Retrieve all currencies that have exchange rate modules available
     *
     * @param bool $registeredOnly Show currencies with registered services only
     * @param bool $codesOnly      Return codes only
     *
     * @return array|false Array with currency iso code as key and module instance as value
     *
     * @since 1.0.0
     */
    public static function getCurrencyRateInfo($registeredOnly = false, $codesOnly = false)
    {
        if ($registeredOnly) {
            $sql = new DbQuery();
            $sql->select('`id_currency`, `id_module`');
            $sql->from('currency_module');
        } else {
            $sql = new DbQuery();
            $sql->select('c.`id_currency`, cm.`id_module`');
            $sql->from('currency', 'c');
            $sql->leftJoin('currency_module', 'cm', 'cm.`id_currency` = c.`id_currency`');
            $sql->where('c.`deleted` = 0');
        }

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if (!$results) {
            return false;
        }

        $defaultCurrency = Currency::getDefaultCurrency();
        if (!$defaultCurrency) {
            return false;
        }

        $return = [];
        foreach ($results as $result) {
            /** @var Currency $currency */
            $currency = Currency::getCurrencyInstance($result['id_currency']);
            /** @var CurrencyRateModule $module */
            $module = Module::getInstanceById($result['id_module']);
            if (Validate::isLoadedObject($currency) && Validate::isLoadedObject($module)) {
                if ($codesOnly) {
                    $return[Tools::strtoupper($currency->iso_code)] = null;

                } else {
                    $return[Tools::strtoupper($currency->iso_code)] = $module;
                }
            } elseif (!$registeredOnly && Validate::isLoadedObject($currency)) {
                $return[Tools::strtoupper($currency->iso_code)] = null;
            }
        }

        return $return;
    }

    /**
     * @param bool|string $baseCurrency
     *
     * @return false|array Result
     *
     * @since 1.0.0
     */
    public static function scanMissingCurrencyRateModules($baseCurrency = false)
    {
        if (!$baseCurrency) {
            $defaultCurrency = Currency::getDefaultCurrency();
            if (!Validate::isLoadedObject($defaultCurrency)) {
                return false;
            }
            $baseCurrency = $defaultCurrency->iso_code;
        }

        $registeredModules = self::getCurrencyRateInfo();
        foreach ($registeredModules as $currencyCode => &$module) {
            if (!Validate::isLoadedObject($module)) {
                $idCurrency = Currency::getIdByIsoCode($currencyCode);
                $currency = Currency::getCurrencyInstance($idCurrency);
                if (!Validate::isLoadedObject($currency)) {
                    continue;
                }

                $availableModuleName = self::providesExchangeRate($currency->iso_code, $baseCurrency, true);
                if ($availableModuleName) {
                    $availableModule = Module::getInstanceByName($availableModuleName);
                    if (Validate::isLoadedObject($availableModule)) {
                        $module['id_module'] = $availableModule->id;
                        self::setModule($currency->id, $availableModule->id);
                    }
                }
            }
        }

        return $registeredModules;
    }

    /**
     * List all installed and active currency rate modules
     *
     * @return array Available modules
     *
     * @since 1.0.0
     */
    public static function getInstalledCurrencyRateModules()
    {
        $sql = new DbQuery();
        $sql->select('m.`id_module`, m.`name`');
        $sql->from('module', 'm');
        $sql->leftJoin('hook_module', 'hm', 'hm.`id_module` = m.`id_module` '.Shop::addSqlRestriction(false, 'hm'));
        $sql->leftJoin('hook', 'h', 'hm.`id_hook` = h.`id_hook`');
        $sql->innerJoin('module_shop', 'ms', 'm.`id_module` = ms.`id_module`');
        $sql->where('ms.`id_shop` = '.(int) Context::getContext()->shop->id);
        $sql->where('h.`name` = \'currencyRates\'');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Same as `CurrencyRateModule::getInstalledCurrencyRateModules`
     * but also returns the list of supported currencies by every module
     *
     * @return array Available modules
     *
     * @since 1.0.0
     */
    public static function getCurrencyRateModules()
    {
        $modules = [];
        $installedModules = self::getInstalledCurrencyRateModules();
        foreach ($installedModules as $moduleInfo) {
            /** @var CurrencyRateModule $module */
            $module = Module::getInstanceById($moduleInfo['id_module']);
            $modules[$module->name] = $module->getSupportedCurrencies();
        }

        return $modules;
    }

    /**
     * Get providing modules
     *
     * @param string      $to      To currency code
     * @param null|string $from    From given base currency code
     * @param bool        $justOne Search for just one module
     *
     * @return array|string
     */
    public static function providesExchangeRate($to, $from = null, $justOne = false)
    {
        if (!$from) {
            $fromCurrency = Currency::getDefaultCurrency();
            $from = Tools::strtoupper($fromCurrency->iso_code);
        }

        $modules = self::getCurrencyRateModules();
        if ($justOne) {
            $providingModules = '';
        } else {
            $providingModules = [];
        }
        foreach ($modules as $moduleName => $supportedCurrencies) {
            if (in_array(Tools::strtoupper($to), $supportedCurrencies) && in_array($from, $supportedCurrencies)) {
                if ($justOne) {
                    return $moduleName;
                }
                $providingModules[] = $moduleName;
            }
        }

        return $providingModules;
    }

    /**
     * Get providing modules
     *
     * @param int    $idCurrency To currency code
     * @param string $selected   Selected module
     *
     * @return array|false
     */
    public static function getServices($idCurrency, $selected)
    {
        $currency = new Currency($idCurrency);
        $defaultCurrency = Currency::getDefaultCurrency();
        if (!Validate::isLoadedObject($defaultCurrency)) {
            return false;
        }

        if ($currency->iso_code == $defaultCurrency->iso_code) {
            return false;
        }

        $availableServices = self::providesExchangeRate($currency->iso_code, $defaultCurrency->iso_code, false);

        $serviceModules = [];
        foreach ($availableServices as $service) {
            $module = Module::getInstanceByName($service);
            if (!Validate::isLoadedObject($module)) {
                continue;
            }

            $serviceModules[] = [
                'id_module' => $module->id,
                'name' => $module->name,
                'display_name' => $module->displayName,
                'selected' => $module->name === $selected,
            ];
        }

        return $serviceModules;
    }

    protected static function getModuleForCurrency($idCurrency)
    {
        $sql = new DbQuery();
        $sql->select('`id_module`');
        $sql->from('currency_module');
        $sql->where('`id_currency` = '.(int) $idCurrency);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Set module
     *
     * @param int $idCurrency
     * @param int $idModule
     */
    public static function setModule($idCurrency, $idModule)
    {
        if (Db::getInstance()->getValue('SELECT `id_currency` FROM `'._DB_PREFIX_.'currency_module` WHERE `id_currency` = '.(int) $idCurrency)) {
            Db::getInstance()->update(
                'currency_modules',
                [
                    'id_module' => (int) $idModule,
                ],
                '`id_currency` = '.(int) $idCurrency
            );
        } else {
            Db::getInstance()->insert(
                'currency_module',
                [
                    'id_currency' => (int) $idCurrency,
                    'id_module'   => (int) $idModule,
                ]
            );
        }
    }
}
