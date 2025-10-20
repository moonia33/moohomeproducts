<?php

namespace Moohp;

use Context;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Adapter\Category\CategoryProductSearchProvider;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use ProductAssembler;
use ProductPresenterFactory;

class ProductFetcher
{
    /** @var Context */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getProductsForCategory($idCategory, $sortKey = 'date_desc', $limit = 8, array $options = [])
    {
        $includeChildren = !empty($options['include_children']);
        $childrenDepth = isset($options['children_depth']) ? (int)$options['children_depth'] : 0;
        $inStockOnly = !empty($options['in_stock_only']);

        // Cache key (module-specific)
        $langId = (int)$this->context->language->id;
        $shopId = (int)$this->context->shop->id;
        $currencyId = (int)$this->context->currency->id;
        $groupId = (int)($this->context->customer && $this->context->customer->id ? $this->context->customer->id_default_group : (\Configuration::get('PS_UNIDENTIFIED_GROUP') ?: 0));
        $ver = (int) (\Configuration::get('MOOHP_CACHE_VER') ?: 1);
        $cacheKey = sprintf('moohp_v%d_cat_%d_%s_%d_lang%d_shop%d_cur%d_grp%d_ch%d_d%d_is%d', $ver, (int)$idCategory, (string)$sortKey, (int)$limit, $langId, $shopId, $currencyId, $groupId, $includeChildren ? 1 : 0, $childrenDepth, $inStockOnly ? 1 : 0);

        return $this->getFromCache($cacheKey, function () use ($idCategory, $sortKey, $limit, $includeChildren, $childrenDepth, $inStockOnly) {
            return $this->fetchProductsNoCache($idCategory, $sortKey, $limit, $includeChildren, $childrenDepth, $inStockOnly);
        });
    }

    private function fetchProductsNoCache($idCategory, $sortKey, $limit, $includeChildren, $childrenDepth, $inStockOnly)
    {
        list($type, $direction) = $this->parseSortKey($sortKey);

        $context = new ProductSearchContext($this->context);
        $query = new ProductSearchQuery();
        $query->setIdCategory((int) $idCategory);
        $fetchLimit = $inStockOnly ? (int) min(max(((int)$limit) * 2, ((int)$limit) + 4), 60) : (int)$limit;
        $query->setResultsPerPage($fetchLimit);

        switch ($type) {
            case 'position': $field = 'position'; break;
            case 'price': $field = 'price'; break;
            case 'date': $field = 'date_add'; break;
            case 'random': $field = 'random'; break;
            default: $field = 'date_add';
        }
        $dir = ($direction === 'asc') ? 'asc' : 'desc';
        $sortOrder = new SortOrder('product', $field, $dir);
        $query->setSortOrder($sortOrder);

        $translator = $this->context->getTranslator();
        $catEntity = new \Category((int)$idCategory, (int)$this->context->language->id);
        $provider = new CategoryProductSearchProvider($translator, $catEntity);
        $results = $provider->runQuery($context, $query);

        $presented = [];
        if ($results && method_exists($results, 'getProducts')) {
            $raw = $results->getProducts();
            $presented = $this->presentRawProducts($raw, (int)$limit, $inStockOnly);
        }

        if (empty($presented)) {
            list($orderBy, $orderWay) = $this->mapOrder($type, $direction);
            $cat = new \Category((int)$idCategory, (int)$this->context->language->id);
            $isRandom = ($type === 'random');
            $raw = $cat->getProducts((int)$this->context->language->id, 1, (int)$fetchLimit, $orderBy, $orderWay, false, true, $isRandom, (int)$fetchLimit);
            $presented = $this->presentRawProducts($raw, (int)$limit, $inStockOnly);

            if (empty($presented) && $includeChildren && $childrenDepth > 0) {
                $childRaw = $this->getProductsFromChildren($idCategory, (int)$limit, $orderBy, $orderWay, $isRandom, $childrenDepth, (int)$fetchLimit);
                $presented = $this->presentRawProducts($childRaw, (int)$limit, $inStockOnly);
            }
        }

        if ($includeChildren && $childrenDepth > 0 && count($presented) < (int)$limit) {
            list($orderBy, $orderWay) = $this->mapOrder($type, $direction);
            $isRandom = ($type === 'random');
            $remaining = (int)$limit - count($presented);
            $childFetchLimit = $inStockOnly ? (int) min(max($remaining * 2, $remaining + 4), 60) : $remaining;
            $childRaw = $this->getProductsFromChildren($idCategory, $remaining, $orderBy, $orderWay, $isRandom, $childrenDepth, $childFetchLimit);
            $presentedChildren = $this->presentRawProducts($childRaw, $remaining, $inStockOnly);
            if (!empty($presentedChildren)) {
                $existing = [];
                foreach ($presented as $p) { if (isset($p['id_product'])) { $existing[(int)$p['id_product']] = true; } }
                foreach ($presentedChildren as $p) {
                    $pid = (int)($p['id_product'] ?? 0);
                    if ($pid && empty($existing[$pid])) {
                        $presented[] = $p;
                        $existing[$pid] = true;
                        if (count($presented) >= (int)$limit) { break; }
                    }
                }
            }
        }

        $presented = $this->ensureThemeKeys($presented);
        return $presented;
    }

    private function parseSortKey($key)
    {
        if ($key === 'random') { return ['random', '']; }
        $parts = explode('_', $key);
        $field = $parts[0] ?? 'date';
        $dir = $parts[1] ?? 'desc';
        return [$field, $dir];
    }

    private function mapOrder($type, $direction)
    {
        $orderWay = ($direction === 'asc') ? 'asc' : 'desc';
        switch ($type) {
            case 'position': return ['position', $orderWay];
            case 'price': return ['price', $orderWay];
            case 'date': return ['date_add', $orderWay];
            default: return ['date_add', $orderWay];
        }
    }

    private function getProductsFromChildren($idCategory, $limit, $orderBy, $orderWay, $isRandom, $depth = 1, $fetchLimit = null)
    {
        $langId = (int)$this->context->language->id;
        $shopId = (int)$this->context->shop->id;
        $children = \Category::getChildren((int)$idCategory, $langId, true, $shopId);
        if (empty($children)) { return []; }
        $collected = [];
        $seen = [];
        $perNodeLimit = $fetchLimit ? (int)$fetchLimit : (int)$limit;
        foreach ($children as $child) {
            $cid = (int)($child['id_category'] ?? 0);
            if ($cid <= 0) { continue; }
            $c = new \Category($cid, $langId, $shopId);
            $raw = $c->getProducts($langId, 1, (int)$perNodeLimit, $orderBy, $orderWay, false, true, $isRandom, (int)$perNodeLimit);
            foreach ($raw as $rp) {
                $pid = (int)($rp['id_product'] ?? 0);
                if ($pid && !isset($seen[$pid])) {
                    $collected[] = $rp;
                    $seen[$pid] = true;
                    if (count($collected) >= (int)$limit) { break 2; }
                }
            }
            if ($depth > 1 && count($collected) < (int)$limit) {
                $more = $this->getProductsFromChildren($cid, (int)$limit - count($collected), $orderBy, $orderWay, $isRandom, $depth - 1, $perNodeLimit);
                foreach ($more as $rp) {
                    $pid = (int)($rp['id_product'] ?? 0);
                    if ($pid && !isset($seen[$pid])) {
                        $collected[] = $rp;
                        $seen[$pid] = true;
                        if (count($collected) >= (int)$limit) { break; }
                    }
                }
            }
        }
        return $collected;
    }

    private function ensureLinkAndUrlKeys(array $products)
    {
        $langId = (int)$this->context->language->id;
        foreach ($products as &$p) {
            if (!is_array($p)) { continue; }
            if (empty($p['url']) && !empty($p['canonical_url'])) { $p['url'] = $p['canonical_url']; }
            if (empty($p['link']) && !empty($p['canonical_url'])) { $p['link'] = $p['canonical_url']; }
            if (empty($p['link']) && !empty($p['url'])) { $p['link'] = $p['url']; }
            if (empty($p['url']) && !empty($p['link'])) { $p['url'] = $p['link']; }
            if (empty($p['url']) && empty($p['link'])) {
                $pid = (int)($p['id_product'] ?? 0);
                if ($pid > 0) {
                    $rewrite = isset($p['link_rewrite']) ? (is_array($p['link_rewrite']) ? ($p['link_rewrite'][$langId] ?? '') : $p['link_rewrite']) : '';
                    $computed = $this->context->link->getProductLink($pid, $rewrite);
                    $p['url'] = $computed; $p['link'] = $computed;
                }
            }
        }
        unset($p); return $products;
    }

    private function presentRawProducts(array $rawProducts, int $limit, bool $inStockOnly = false)
    {
        if (empty($rawProducts)) { return []; }

        $assembler = new ProductAssembler($this->context);
        $presenterFactory = new ProductPresenterFactory($this->context);
        $presentationSettings = $presenterFactory->getPresentationSettings();
        if (isset($presentationSettings->showPrices)) { $presentationSettings->showPrices = true; }

        $presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter(
            new ImageRetriever($this->context->link),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $productsForTemplate = [];
        $assembleInBulk = method_exists($assembler, 'assembleProducts');
        $assembled = $assembleInBulk ? $assembler->assembleProducts($rawProducts) : $rawProducts;
        foreach ($assembled as $raw) {
            $item = $assembleInBulk ? $raw : $assembler->assembleProduct($raw);
            $presented = $presenter->present($presentationSettings, $item, $this->context->language);
            if ($inStockOnly) {
                $qty = 0;
                if (isset($presented['quantity'])) { $qty = (int)$presented['quantity']; }
                elseif (isset($presented['availability_quantity'])) { $qty = (int)$presented['availability_quantity']; }
                $availableFlag = !empty($presented['availability']) && is_array($presented['availability']) ? ($presented['availability']['available'] ?? null) : null;
                if ($availableFlag === false || $qty <= 0) { continue; }
            }
            $productsForTemplate[] = $presented;
            if (count($productsForTemplate) >= $limit) { break; }
        }

        $productsForTemplate = $this->ensureLinkAndUrlKeys($productsForTemplate);
        return $productsForTemplate;
    }

    private function getFromCache($key, callable $compute, $ttl = 300)
    {
        $cache = null;
        try {
            if ($this->context->controller && method_exists($this->context->controller, 'getContainer')) {
                $cache = $this->context->controller->getContainer()->get('cache.app');
            }
        } catch (\Throwable $e) { $cache = null; }

        if ($cache) {
            if (method_exists($cache, 'get')) {
                try { return $cache->get($key, function () use ($compute) { return $compute(); }); }
                catch (\Throwable $e) {}
            }
            if (method_exists($cache, 'getItem') && method_exists($cache, 'save')) {
                try {
                    $item = $cache->getItem($key);
                    if ($item->isHit()) { return $item->get(); }
                    $value = $compute();
                    $item->set($value);
                    if (method_exists($item, 'expiresAfter')) { $item->expiresAfter($ttl); }
                    $cache->save($item);
                    return $value;
                } catch (\Throwable $e) {}
            }
        }
        return $compute();
    }

    private function ensureThemeKeys(array $products)
    {
        foreach ($products as &$p) {
            if (!is_array($p)) { continue; }
            if (!isset($p['flags']) || !is_array($p['flags'])) { $p['flags'] = []; }
            if (!isset($p['has_discount'])) { $p['has_discount'] = false; }
            if (!isset($p['show_price'])) { $p['show_price'] = true; }
            if (!isset($p['id_product_attribute'])) { $p['id_product_attribute'] = 0; }
            if (!isset($p['cover']) || !is_array($p['cover'])) { $p['cover'] = ['bySize' => []]; }
            if (!isset($p['cover']['bySize']) || !is_array($p['cover']['bySize'])) { $p['cover']['bySize'] = []; }
            $sizes = ['default_xs','default_sm','default_md','default_lg','default_s','default_m','default_l','home_default','home_default_2x','small_default','medium_default','large_default','cart_default','product_main','product_main_2x'];
            foreach ($sizes as $sizeKey) {
                if (!isset($p['cover']['bySize'][$sizeKey]) || !is_array($p['cover']['bySize'][$sizeKey])) {
                    $p['cover']['bySize'][$sizeKey] = ['url' => '', 'width' => 0, 'height' => 0];
                }
            }
            if (empty($p['cover']['bySize']['default_m']['url']) && !empty($p['cover']['bySize']['default_md']['url'])) { $p['cover']['bySize']['default_m'] = $p['cover']['bySize']['default_md']; }
            if (empty($p['cover']['bySize']['default_s']['url']) && !empty($p['cover']['bySize']['default_sm']['url'])) { $p['cover']['bySize']['default_s'] = $p['cover']['bySize']['default_sm']; }
            if (empty($p['cover']['bySize']['default_l']['url']) && !empty($p['cover']['bySize']['default_lg']['url'])) { $p['cover']['bySize']['default_l'] = $p['cover']['bySize']['default_lg']; }
            if (empty($p['cover']['bySize']['product_main']['url'])) {
                if (!empty($p['cover']['bySize']['large_default']['url'])) { $p['cover']['bySize']['product_main'] = $p['cover']['bySize']['large_default']; }
                elseif (!empty($p['cover']['bySize']['medium_default']['url'])) { $p['cover']['bySize']['product_main'] = $p['cover']['bySize']['medium_default']; }
                elseif (!empty($p['cover']['bySize']['home_default']['url'])) { $p['cover']['bySize']['product_main'] = $p['cover']['bySize']['home_default']; }
            }
            if (empty($p['cover']['bySize']['product_main_2x']['url']) && !empty($p['cover']['bySize']['product_main']['url'])) { $p['cover']['bySize']['product_main_2x'] = $p['cover']['bySize']['product_main']; }
            if (!isset($p['cover']['legend'])) { $p['cover']['legend'] = isset($p['name']) ? (string)$p['name'] : ''; }
        }
        unset($p); return $products;
    }
}
