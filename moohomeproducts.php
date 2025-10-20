<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\Entity\Language;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration as AdapterConfiguration;

require_once __DIR__ . '/src/ProductFetcher.php';

class Moohomeproducts extends Module
{
    public function __construct()
    {
        $this->name = 'moohomeproducts';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'moonia';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Home Page Products Block');
        $this->description = $this->l('Show configurable category product blocks on the home page.');

        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHook('displayHome')) {
            return false;
        }

        // Default configuration (module-specific keys)
        Configuration::updateValue('MOOHP_CATEGORY_IDS', '');
        Configuration::updateValue('MOOHP_SORT_ORDER', 'date_desc');
        Configuration::updateValue('MOOHP_PRODUCTS_PER_BLOCK', 8);
        Configuration::updateValue('MOOHP_INCLUDE_CHILDREN', 1);
        Configuration::updateValue('MOOHP_CHILDREN_DEPTH', 1);
        Configuration::updateValue('MOOHP_IN_STOCK_ONLY', 0);

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('MOOHP_CATEGORY_IDS');
        Configuration::deleteByName('MOOHP_SORT_ORDER');
        Configuration::deleteByName('MOOHP_PRODUCTS_PER_BLOCK');
        Configuration::deleteByName('MOOHP_INCLUDE_CHILDREN');
        Configuration::deleteByName('MOOHP_CHILDREN_DEPTH');
        Configuration::deleteByName('MOOHP_IN_STOCK_ONLY');

        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitMoohp')) {
            $category_ids = Tools::getValue('MOOHP_CATEGORY_IDS', '');
            $sort_order = Tools::getValue('MOOHP_SORT_ORDER', 'date_desc');
            $per_block = (int) Tools::getValue('MOOHP_PRODUCTS_PER_BLOCK', 8);
            $include_children = (int) Tools::getValue('MOOHP_INCLUDE_CHILDREN', 1);
            $children_depth = (int) Tools::getValue('MOOHP_CHILDREN_DEPTH', 1);
            $in_stock_only = (int) Tools::getValue('MOOHP_IN_STOCK_ONLY', 0);

            $clean_ids = implode(',', array_filter(array_map('intval', explode(',', $category_ids))));
            Configuration::updateValue('MOOHP_CATEGORY_IDS', $clean_ids);
            Configuration::updateValue('MOOHP_SORT_ORDER', $sort_order);
            Configuration::updateValue('MOOHP_PRODUCTS_PER_BLOCK', max(1, $per_block));
            Configuration::updateValue('MOOHP_INCLUDE_CHILDREN', $include_children ? 1 : 0);
            Configuration::updateValue('MOOHP_CHILDREN_DEPTH', max(0, $children_depth));
            Configuration::updateValue('MOOHP_IN_STOCK_ONLY', $in_stock_only ? 1 : 0);

            // Invalidate cache by bumping version
            $ver = (int) (Configuration::get('MOOHP_CACHE_VER') ?: 1);
            Configuration::updateValue('MOOHP_CACHE_VER', $ver + 1);

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        } elseif (Tools::isSubmit('submitMoohpClearCache')) {
            $ver = (int) (Configuration::get('MOOHP_CACHE_VER') ?: 1);
            Configuration::updateValue('MOOHP_CACHE_VER', $ver + 1);
            $output .= $this->displayConfirmation($this->l('Module cache cleared'));
        }

        $this->context->controller->addCSS($this->_path . 'assets/css/style.css', 'all');
        $output .= $this->renderForm();

        return $output;
    }

    protected function renderForm()
    {
        $categories = $this->getAllCategoriesAsOptions();
        $fields_form = [
            'form' => [
                'legend' => ['title' => $this->l('Settings'), 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Category IDs (comma separated)'),
                        'name' => 'MOOHP_CATEGORY_IDS',
                        'cols' => 40,
                        'rows' => 4,
                        'hint' => $this->l('Enter category IDs separated by commas (e.g. 3,4,5). Use the category selector below to find IDs.'),
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Category selector'),
                        'name' => 'category_selector',
                        'html_content' => $this->renderCategorySelector($categories),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Sort order'),
                        'name' => 'MOOHP_SORT_ORDER',
                        'options' => [
                            'query' => [
                                ['id' => 'position_asc', 'name' => $this->l('Position (asc)')],
                                ['id' => 'position_desc', 'name' => $this->l('Position (desc)')],
                                ['id' => 'date_desc', 'name' => $this->l('Date added (newest first)')],
                                ['id' => 'date_asc', 'name' => $this->l('Date added (oldest first)')],
                                ['id' => 'price_asc', 'name' => $this->l('Price (low to high)')],
                                ['id' => 'price_desc', 'name' => $this->l('Price (high to low)')],
                                ['id' => 'random', 'name' => $this->l('Random')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Products per block'),
                        'name' => 'MOOHP_PRODUCTS_PER_BLOCK',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Include children products'),
                        'name' => 'MOOHP_INCLUDE_CHILDREN',
                        'values' => [
                            [
                                'id' => 'include_children_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'include_children_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'desc' => $this->l('When parent category has no direct products, pull from child categories.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Children depth'),
                        'name' => 'MOOHP_CHILDREN_DEPTH',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->l('How deep to include children (0 = disabled, 1 = direct children, 2 = grandchildren, etc).'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Only in-stock products'),
                        'name' => 'MOOHP_IN_STOCK_ONLY',
                        'values' => [
                            [
                                'id' => 'in_stock_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'in_stock_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'desc' => $this->l('Filter out products that are out of stock.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
                'buttons' => [
                    [
                        'type' => 'submit',
                        'title' => $this->l('Clear module cache'),
                        'icon' => 'process-icon-eraser',
                        'name' => 'submitMoohpClearCache',
                    ],
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?? 0;

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMoohp';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value['MOOHP_CATEGORY_IDS'] = Configuration::get('MOOHP_CATEGORY_IDS');
        $helper->fields_value['MOOHP_SORT_ORDER'] = Configuration::get('MOOHP_SORT_ORDER');
        $helper->fields_value['MOOHP_PRODUCTS_PER_BLOCK'] = Configuration::get('MOOHP_PRODUCTS_PER_BLOCK');
        $helper->fields_value['MOOHP_INCLUDE_CHILDREN'] = (int)Configuration::get('MOOHP_INCLUDE_CHILDREN');
        $helper->fields_value['MOOHP_CHILDREN_DEPTH'] = (int)Configuration::get('MOOHP_CHILDREN_DEPTH');
        $helper->fields_value['MOOHP_IN_STOCK_ONLY'] = (int)Configuration::get('MOOHP_IN_STOCK_ONLY');

        return $helper->generateForm([$fields_form]);
    }

    protected function getAllCategoriesAsOptions()
    {
        $categories = Category::getCategories((int)$this->context->language->id, true, false);
        $flat = [];
        foreach ($categories as $cat) {
            $flat[] = ['id_category' => (int)$cat['id_category'], 'name' => $cat['name']];
        }
        return $flat;
    }

    protected function renderCategorySelector(array $categories)
    {
        $html = '<div class="moohp-category-list">';
        $html .= '<table class="table"><thead><tr><th>' . $this->l('ID') . '</th><th>' . $this->l('Name') . '</th></tr></thead><tbody>';
        foreach ($categories as $c) {
            $html .= '<tr><td>' . (int)$c['id_category'] . '</td><td>' . htmlspecialchars($c['name']) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }

    public function hookDisplayHome($params)
    {
        $category_ids_csv = Configuration::get('MOOHP_CATEGORY_IDS');
        if (empty($category_ids_csv)) {
            return '';
        }

        $category_ids = array_filter(array_map('intval', explode(',', $category_ids_csv)));
        if (empty($category_ids)) {
            return '';
        }

        $sort = Configuration::get('MOOHP_SORT_ORDER') ?: 'date_desc';
        $per_block = (int) Configuration::get('MOOHP_PRODUCTS_PER_BLOCK') ?: 8;
        $includeChildren = (bool) Configuration::get('MOOHP_INCLUDE_CHILDREN');
        $childrenDepth = (int) Configuration::get('MOOHP_CHILDREN_DEPTH');
        $inStockOnly = (bool) Configuration::get('MOOHP_IN_STOCK_ONLY');

        $fetcher = new \Moohp\ProductFetcher($this->context);
        $blocks = [];

        foreach ($category_ids as $cid) {
            try {
                $products = $fetcher->getProductsForCategory($cid, $sort, $per_block, [
                    'include_children' => $includeChildren,
                    'children_depth' => $childrenDepth,
                    'in_stock_only' => $inStockOnly,
                ]);
            } catch (Exception $e) {
                $products = [];
            }

            if (empty($products)) {
                continue;
            }

            $category = new Category($cid, (int)$this->context->language->id);
            $catThumbPath = _PS_CAT_IMG_DIR_ . (int)$cid . '_thumb-category_default.jpg';
            $catThumbUrl = $this->context->link->getMediaLink(_THEME_CAT_DIR_ . (int)$cid . '_thumb-category_default.jpg');
            $catImage = file_exists($catThumbPath) ? $catThumbUrl : ($this->context->smarty->getTemplateVars('urls')['no_picture_image']['small']['url'] ?? '');

            $blocks[] = [
                'id_category' => $cid,
                'category_name' => $category->name,
                'category_desc' => trim(strip_tags((string)$category->description)),
                'category_image' => $catImage,
                'link' => $this->context->link->getCategoryLink($cid),
                'products' => $products,
            ];
        }

        if (empty($blocks)) {
            return '';
        }

        $this->context->controller->addCSS($this->_path . 'assets/css/style.css', 'all');
        $this->context->smarty->assign('moohp_blocks', $blocks);

        return $this->display(__FILE__, 'views/templates/hook/displayHome.tpl');
    }
}
