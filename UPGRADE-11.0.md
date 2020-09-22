# Upgrade from 10.x to 11.x

Changes: [v10.4.2...v11.0.0](https://github.com/findologic/plugin-shopware-5/compare/v10.4.2...SW-334_switch_to_findologic_api)  
Changelog: [v11.0.0 Release](https://github.com/findologic/plugin-shopware-5/releases/tag/v11.0.0)

This file is **irrelevant** for you in case you do **not have an extension plugin**.

---

This file should help you upgrade from 10.x to 11.x, by providing you with
information that you will need, in case you have an extension plugin that
overrides or implements any classes of the main plugin.  
Information about private methods won't be preserved.

## Changes

### Controllers

* `Shopware_Controllers_Frontend_Findologic`
  * Method `indexAction`
    * Allows an optional query parameter `productId`, which may be used
  to get only the product, or details about why that product cannot be exported.
    * Query parameter `language` is no longer used.

### Export

Changes related to export files.

* `FinSearchUnified\ShopwareProcess`
   * Property visibility of `public $shop` has been updated to `protected $shop`.
   * Property name and visibility of `public $shopKey` has been updated to `protected $shopkey`.
   * Properties `$customerRepository` and `$articleRepository` have been removed. The new class
    `FinSearchUnified\BusinessLogic\ExportService` is now responsible for fetching products.
   * Signature of method `ShopwareProcess::getFindologicXml` has been updated to
    `getFindologicXml($start, $count, $save = false)`.
   * Signature of method `ShopwareProcess::getAllProductsAsXmlArray` has been updated to
    `getAllProductsAsXmlArray($start, $count)`.

### SearchBundle

* `FinSearchUnified\Bundle\ProductNumberSearch`
  * Method `ProductNumberSearch::search` now internally uses the new `QueryBuilder` and `ResponseParser`.
  * Signature of method `ProductNumberSearch::redirectOnLandingpage` has been updated to
   `redirectOnLandingpage(string $landingPageUri)`.
  * Signature of method `ProductNumberSearch::getFacetHandler` has been updated to
   `getFacetHandler(BaseFilter $filter)`.
  * Signature of method `ProductNumberSearch::createFacets` has been updated to
   `createFacets(Criteria $criteria, ShopContextInterface $context, array $filters = [])`.

### SearchBundleFindologic

* `FinSearchUnified\Bundle\SearchBundleFindologic\NavigationQueryBuilder` has been removed and is replaced by
`FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NavigationQueryBuilder`.
* `FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface`
  * Signature of method `PartialFacetHandlerInterface::generatePartialFacet` has been updated to
  `generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)`
  * Signature of method `PartialFacetHandlerInterface::supportsFilter` has been updated to
  `supportsFilter(BaseFilter $filter)`
* `FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder` has been removed and is replaced by
`FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder`.

### ConditionHandler

* `FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\CategoryConditionHandler`
  * Signature of method `CategoryConditionHandler::generateCondition` has been updated to
  `generateCondition(ConditionInterface $condition, QueryBuilder $query, ShopContextInterface $context)`
* `FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\PriceConditionHandler`
  * Signature of method `PriceConditionHandler::generateCondition` has been updated to
  `generateCondition(ConditionInterface $condition, QueryBuilder $query, ShopContextInterface $context)`
* `FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\ProductAttributeConditionHandler`
  * Signature of method `ProductAttributeConditionHandler::generateCondition` has been updated to
  `generateCondition(ConditionInterface $condition, QueryBuilder $query, ShopContextInterface $context)`
* `FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SearchTermConditionHandler`
  * Signature of method `SearchTermConditionHandler::generateCondition` has been updated to
  `generateCondition(ConditionInterface $condition, QueryBuilder $query, ShopContextInterface $context)`

#### FacetHandler

* `FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\CategoryFacetHandler`
  * Signature of method `CategoryFacetHandler::generatePartialFacet` has been updated to
  `generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)`.
  * Signature of method `CategoryFacetHandler::supportsFilter` has been updated to
  `supportsFilter(BaseFilter $filter)`.
* `FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ColorFacetHandler`
  * Signature of method `ColorFacetHandler::generatePartialFacet` has been updated to
  `generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)`.
  * Signature of method `ColorFacetHandler::supportsFilter` has been updated to
  `supportsFilter(BaseFilter $filter)`.
* `FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ImageFacetHandler`
  * Signature of method `ImageFacetHandler::generatePartialFacet` has been updated to
  `generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)`.
  * Signature of method `ImageFacetHandler::supportsFilter` has been updated to
  `supportsFilter(BaseFilter $filter)`.
* `FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\RangeFacetHandler`
  * Signature of method `RangeFacetHandler::generatePartialFacet` has been updated to
  `generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)`.
  * Signature of method `RangeFacetHandler::supportsFilter` has been updated to
  `supportsFilter(BaseFilter $filter)`.
* `FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\TextFacetHandler`
  * Signature of method `TextFacetHandler::generatePartialFacet` has been updated to
  `generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)`.
  * Signature of method `TextFacetHandler::supportsFilter` has been updated to
  `supportsFilter(BaseFilter $filter)`.

#### SortingHandler

* All `SortingHandlers` in namespace `FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler` now use
the new query builder.
* `FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator`
  * Signature of method `CustomListingHydrator::hydrateFacet` has been updated to
  `hydrateFacet(BaseFilter $filter)`.

#### Gateway

* `FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\CustomFacetGateway` now uses the new query builder.

#### Helper

* `FinSearchUnified\Helper\Statichelper`
   * Signature of method `StaticHelper::getXmlFromResponse` has been updated to
   `getXmlFromRawResponse($responseText)`.
   * Signature of method `StaticHelper::setPromotion` has been updated to
   `setPromotion(Promotion $promotion = null)`.
   * Signature of method `StaticHelper::setSmartDidYouMean` has been updated to
   `setSmartDidYouMean(SmartDidYouMean $smartDidYouMean)`.
   * Signature of method `StaticHelper::setQueryInfoMessage` has been updated to
   `setQueryInfoMessage(QueryInfoMessage $queryInfoMessage)`.
   * Method `StaticHelper::checkIfRedirect` has been removed without a replacement.
   * Method `StaticHelper::getProductsFromXml` has been removed without a replacement.
   * Method `StaticHelper::getDetailIdForOrdernumber` has been removed without a replacement.

### Misc

Other changes that are not explicitly related to classes/methods.

* `FinSearchUnified/composer.json`/`FinSearchUnified/composer.lock`
   * `findologic/findologic-api` with version `^1.5` is now required.
   * `ext-json` with version `*` is now required.
* `FinSearchUnified/plugin.xml`
   * Plugin label has been updated to `Findologic - Search & Navigation Platform`.
* `FinSearchUnified/Resources/services.xml`
   * Service `FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilderFactory` no longer uses
   the `http_client` argument.
* `FinSearchUnified/Resources/views/frontend/search/fuzzy.tpl`
   * Filters are now explicitly checked to show the query info message.
   * `FinSearchUnified/Resources/snippets/frontend/search/query_info_message.ini`
     * Filter-specific translations `frontend/search/query_info_message/cat`/`frontend/search/query_info_message/vendor` have been merged
   to `frontend/search/query_info_message/filter`.
* `FinSearchUnified/Resources/views/frontend/listing/filter/_includes/filter-color-selection.tpl`
   * The color filter template has been slightly adapted.
   * New LESS file for the color filter `FinSearchUnified/Resources/views/frontend/less/color-filter.less`

---

## New Classes/Files/Methods

We have added some new classes to make the overall code easier readable and at the same time
easier to extend.

* `FinSearchUnified\Constants` received two new constants
   * `CONTENT_TYPE_XML` for responses of type XML.
   * `CONTENT_TYPE_JSON` for responses of type JSON.

### Export

* `FinSearchUnified\ShopwareProcess`
   * Added a new property `protected $exportService`.
   * New method `getProductsById($productId)` allows for searching for a product by a specific id. Returns errors in
   case the product cannot be exported.
   * New method `setUpExportService`, which initializes the export service.
   * New method `getExportService`, which returns the export service.

### SearchBundle

#### QueryBuilder

* `FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\SearchQueryBuilder` is a new class which is responsible
for building search requests to Findologic.
* `FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NavigationQueryBuilder` is a new class which is
responsible for building navigation requests to Findologic.

#### ResponseParser

This namespace is responsible for parsing the response from Findologic.

* `FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\BaseFilter` general purpose class for
a filter. Every filter type must extend from it.
* `FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Promotion` holds information about a promotion.
* `FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\SmartDidYouMean` is responsible for showing
the "Did you mean" messages.
* `FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Struct` is a general purpose class for a serializable
object holding certain data.

##### QueryInfoMessage

Holds all query info messages, such as "Search results for query xxx". May show a different message
depending on the user action.

* `FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\QueryInfoMessage` base class for all
other query info messages. Every query info message must extend from this class.

##### Filter

All supported filter types can be found in here.

* `FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\Filter` is the base class for all filters.

##### Xml21

Specific implementation for the `XML_2.1` output adapter response format.

#### Helper

* `FinSearchUnified\Helper\Statichelper`
   * New method `StaticHelper::isVersionLowerThan($version)` allows for checking the Shopware version.
* `FinSearchUnified\Helper\HeaderHandler`
   * New method `HeaderHandler::setContentType` allows overriding the default content-type header.

### BusinessLogic

* New class `FinSearchUnified\BusinessLogic\ExportErrorInformation` is responsible
for building error messages for debugging specific products.
* New class `FinSearchUnified\BusinessLogic\ExportService` is responsible for fetching
all products and building error message in case a product id has been provided.
* New class `FinSearchUnified\BusinessLogic\XmlInformation` holds general information about the generated XML.
