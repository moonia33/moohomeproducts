# Home Page Products Block (moohomeproducts)

<a href="https://github.com/moonia33/moohomeproducts/releases/latest/download/moohomeproducts.zip">
  <img alt="Download moohomeproducts.zip" src="https://img.shields.io/badge/download-moohomeproducts.zip-blue?style=for-the-badge">
  </a>

Lightweight PrestaShop module that shows configurable category product blocks on the home page, leveraging the PS9 presentation pipeline and your theme's product partials — no legacy helpers, no custom product markup.

## Features
- Multiple configurable category blocks on `displayHome` hook
- Sorting: position, price, date or random
- Only in-stock filter (optional)
- Include children products with configurable depth
- Caching with context (language/shop/currency/group) and a "Clear module cache" button
- Strict layout compatibility: products are rendered via core presenter, look & feel is fully controlled by your theme

## Requirements / compatibility
- Target core: PrestaShop 9.x
- Should work with 1.7.8+, but only PS9 is officially targeted. Uses PS presentation pipeline and Search Providers

## Installation
1. Copy `moohomeproducts` into `modules/`
2. BO > Modules > Module Manager > find "Home Page Products Block" and click Install
3. Open module settings and configure categories and options
4. Optionally check BO > Design > Positions to ensure it is hooked on `displayHome`

## Configuration
- Category IDs (comma separated): e.g. `3,4,5`
- Sort order: `position_asc|position_desc|date_asc|date_desc|price_asc|price_desc|random`
- Products per block: number of products to show in each block
- Include children products: when the parent category is short on direct products, pull from children
- Children depth: 0 = disabled; 1 = direct children; 2 = grandchildren; etc
- Only in-stock products: filter out OOS products based on presenter `quantity` / `availability`
- Clear module cache: bumps internal cache version to invalidate all entries

Tip: use the built-in Category selector list in the settings to quickly find category IDs — it lists IDs and names.

## How it works
The module queries products via PS9 pipeline:
- CategoryProductSearchProvider → ProductAssembler → ProductPresenterFactory → ProductListingPresenter
- If the main provider returns nothing, it falls back to `Category::getProducts` and feeds results back through the presenter
- With "Include children" enabled and still below the limit, it tops up from children recursively (up to the configured depth) with de-duplication by `id_product`
- With "Only in-stock" enabled, the module intentionally overfetches and filters so the final limit reflects actually available products

Product cards UI is not hard-coded — your theme renders products via its product partials.

## Templates and hooks
- Hook: `displayHome`
- Template: `modules/moohomeproducts/views/templates/hook/displayHome.tpl`
- Product markup: provided by your theme partials

## Caching
- Uses Symfony `cache.app` when available. The key includes: category, sort, limit, language, shop, currency, group, options (children/in-stock) and a cache version
- Default TTL ~300s (subject to your cache pool config)
- "Clear module cache" button bumps `MOOHP_CACHE_VER` to invalidate

## Troubleshooting
- Fewer products than expected:
  - With "Only in-stock", there may simply not be enough available items. The module overfetches and tops up from children when possible, but cannot invent stock.
  - Clear module cache and try again
- No products for a category:
  - Verify category (and children if enabled) has active products in current shop/language/currency
  - Temporarily disable "Only in-stock" for testing
- Children not used:
  - Ensure "Include children" is enabled and depth > 0

## Known limitations
- Children products are used to top-up when the parent is short. If you want full blending of parents/children at all times, that’s a different feature scope.
- The module depends on your theme’s partials; if your theme expects non-standard fields you may need minor theme adjustments.

## Author
- moonia — ramunas@inultimo.lt

---

# LT (Lietuviškai)

Lengvas PrestaShop modulis, rodantis pasirinktų kategorijų produktų blokus pagrindiniame puslapyje, naudojant PS9 pristatymo grandinę ir temos produktų partial'us — be „legacy“ helper'ių ar custom produktų HTML.

## Funkcionalumas
- Keli konfigūruojami kategorijų blokai `displayHome` hake
- Rikiavimas: pozicija, kaina, data arba atsitiktinai
- „Tik sandėlyje“ filtras (pasirenkamas)
- „Įtraukti vaikų kategorijas“ su nustatomo gylio rekursija
- Kešavimas su kontekstu (kalba/parduotuvė/valiuta/grupė) ir mygtukas „Clear module cache“
- Griežtas suderinamumas su tema: produktų korteles renderina branduolio presenter'is, o išvaizdą pilnai valdo jūsų tema

## Reikalavimai ir suderinamumas
- Tikslinis branduolys: PrestaShop 9.x
- Turėtų veikti su 1.7.8+, tačiau oficialiai taikomasi į PS9. Naudojama prezentavimo grandinė ir Search Provider’iai

## Diegimas
1. Nukopijuokite aplanką `moohomeproducts` į `modules/`.
2. BO > Modules > Module Manager > raskite „Home Page Products Block“ ir spauskite Install.
3. Atidarykite modulio nustatymus ir susikonfigūruokite kategorijas bei parinktis.
4. Jei reikia, BO > Design > Positions patikrinkite, kad modulis prijungtas prie `displayHome`.

## Konfigūracija
- Category IDs (comma separated): kategorijų ID sąrašas, pvz. `3,4,5`
- Sort order: `position_asc|position_desc|date_asc|date_desc|price_asc|price_desc|random`
- Products per block: produktų kiekis kiekvienam blokui
- Include children products: kai trūksta tėvinės kategorijos produktų, pildoma iš vaikų
- Children depth: 0 = išjungta; 1 = vaikai; 2 = anūkai; ir t. t.
- Only in-stock products: rodyti tik turimus pagal presenter'io `quantity`/`availability`
- Clear module cache: pakelia `MOOHP_CACHE_VER` reikšmę ir taip invaliduoja kešą

Patogumas: modulio nustatymuose yra paprastas „Category selector“, padedantis rasti kategorijų ID (rodo ID ir pavadinimus).

## Kaip tai veikia
- CategoryProductSearchProvider → ProductAssembler → ProductPresenterFactory → ProductListingPresenter
- Jei pagrindinis tiekėjas (provider) negrąžina rezultatų, krenta į `Category::getProducts` ir vėl praleidžia per presenter’į
- Įjungus „Include children“ ir trūkstant iki limito, papildoma iš vaikų rekursyviai iki nurodyto gylio, su deduplikavimu pagal `id_product`
- Įjungus „Only in-stock“, modulis specialiai „overfetch’ina“ ir po to filtruoja, kad galutinis limitas atitiktų realiai turimas prekes

Produktų UI neaprašomas modulyje — korteles renderina jūsų temos partial'ai.

## Šablonai ir hook'ai
- Hook: `displayHome`
- Šablonas: `modules/moohomeproducts/views/templates/hook/displayHome.tpl`
- Produktų markup'as: pateikiamas pagal jūsų temą

## Kešavimas
- Naudojamas Symfony `cache.app`, jei yra. Raktas apima kategoriją, rikiavimą, limitą, kalbą, parduotuvę, valiutą, grupę, parinktis (children/in-stock) ir kešo versiją
- Numatyta TTL ~300s (priklauso nuo jūsų cache pool konfigūracijos)
- Mygtukas „Clear module cache“ pakelia `MOOHP_CACHE_VER` ir taip invaliduoja kešą

## Trikčių šalinimas
- Mažiau produktų nei tikitės:
  - Su „Only in-stock“ realiai gali nepakakti sandėlyje turimų. Modulis bando padidinti užklausą ir pildyti iš vaikų, bet stebuklų nepadarys.
  - Išvalykite kešą ir bandykite dar kartą.
- Konkrečiai kategorijai nieko nerodo:
  - Patikrinkite, ar kategorija (ir jos vaikai, jei įjungta) turi aktyvių prekių esamame shop/kalba/valiuta kontekste.
  - Laikinai išjunkite „Only in-stock“ testui.
- Vaikų kategorijos neįtraukiamos:
  - Įsitikinkite, kad „Include children“ įjungta ir „Children depth“ > 0.

## Apribojimai
- Vaikų produktai naudojami tik papildymui, kai trūksta iš tėvinės kategorijos. Jei reikia visada maišyti tėvų/vaikų produktus — tai atskiras funkcionalumas.
- Modulis remiasi temos partial’ais. Jei tema tikisi nestandartinių laukų, gali tekti nežymi temos adaptacija.
