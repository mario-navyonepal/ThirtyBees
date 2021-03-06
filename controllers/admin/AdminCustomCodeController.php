<?php

/**
 * Class AdminCustomCodeControllerCore
 *
 * Used to add custom meta, js and css to the front office
 *
 * @since 1.0.0
 */
class AdminCustomCodeControllerCore extends AdminController
{
    /**
     * AdminCustomCodeControllerCore constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->className = 'Configuration';
        $this->table = 'configuration';
        $this->bootstrap = true;

        $fields = array(
            'TB_CUSTOMCODE_METAS' => [
                'title'                     => $this->l('Add extra metas to the header'),
                'desc'                      => $this->l('You can use this form to add extra meta tags to your front office'),
                'type'                      => 'code',
                'mode'                      => 'html',
                'enableBasicAutocompletion' => true,
                'enableSnippets'            => true,
                'enableLiveAutocompletion'  => true,
                'visibility'                => Shop::CONTEXT_ALL,
            ],
            'TB_CUSTOMCODE_CSS'   => [
                'title'                     => $this->l('Add extra css to your pages'),
                'desc'                      => $this->l('You can use this form to add extra css styles to your front office'),
                'type'                      => 'code',
                'mode'                      => 'css',
                'enableBasicAutocompletion' => true,
                'enableSnippets'            => true,
                'enableLiveAutocompletion'  => true,
                'visibility'                => Shop::CONTEXT_ALL,
            ],
            'TB_CUSTOMCODE_JS'    => [
                'title'                     => $this->l('Add extra JavaScript to your pages'),
                'desc'                      => $this->l('You can use this form to add extra JavaScript code to your front office'),
                'type'                      => 'code',
                'mode'                      => 'javascript',
                'enableBasicAutocompletion' => true,
                'enableSnippets'            => true,
                'enableLiveAutocompletion'  => true,
                'visibility'                => Shop::CONTEXT_ALL,
            ],
        );

        $fields2 = [
            'TB_CUSTOMCODE_ORDERCONF_JS' => [
                'title'                     => $this->l('Add extra JS to the order confirmation page'),
                'desc'                      => $this->l('You can use this form to add extra javascript code to the order confirmation page (trackings, etc)'),
                'type'                      => 'code',
                'mode'                      => 'javascript',
                'enableBasicAutocompletion' => true,
                'enableSnippets'            => true,
                'enableLiveAutocompletion'  => true,
                'visibility'                => Shop::CONTEXT_ALL,
            ],
        ];

        $this->fields_options = [
            'general'            => [
                'title'  => $this->l('General'),
                'icon'   => 'icon-cogs',
                'fields' => $fields,
                'submit' => ['title' => $this->l('Save')],
            ],
            'order_confirmation' => [
                'title'       => $this->l('Order Confirmation Page'),
                'description' => $this->l('Available variables:').'<br/>
                                bought_products '.$this->l('(List of products in JSON format)').'<br/>
                                total_products_tax_incl<br/>
                                total_products_tax_excl<br/>
                                total_shipping_tax_incl<br/>
                                total_shipping_tax_excl<br/>
                                total_discounts_tax_incl<br/>
                                total_discounts_tax_excl<br/>
                                total_paid_tax_incl<br/>
                                total_paid_tax_excl<br/>
                                id_customer<br/>
                                currency.iso_code',
                'icon'        => 'icon-cogs',
                'fields'      => $fields2,
                'submit'      => ['title' => $this->l('Save')],
            ],
        ];

        parent::__construct();
    }

}
