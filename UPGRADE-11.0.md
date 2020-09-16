# Upgrade from 10.x to 11.x

This file should help you upgrade from 10.x to 11.x, by providing you with
information that you will need, in case you have an extension plugin that
overrides or implements any classes of the main plugin.  
Information about private methods **are not preserved**.

* Diff [v10.4.1...v11.0.0](https://github.com/findologic/plugin-shopware-5/compare/v10.4.1...SW-334_switch_to_findologic_api).

### Search Bundle

* `FinSearchUnified\Bundle\ProductNumberSearch`
  * Method `ProductNumberSearch::search` now internally uses the new `QueryBuilder` and `ResponseParser`.
  * Signature of method `ProductNumberSearch::redirectOnLandingpage` has been updated to
   `redirectOnLandingpage(string $landingPageUri)`.
  * Signature of method `ProductNumberSearch::getFacetHandler` has been updated to
   `getFacetHandler(BaseFilter $filter)`.
  * Signature of method `ProductNumberSearch::createFacets` has been updated to
   `createFacets(Criteria $criteria, ShopContextInterface $context, array $filters = [])`.
* `FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\CategoryFacetHandler`.
  * Signature of method `CategoryFacetHandler::generatePartialFacet` has been updated to
  `generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)`.

... more to come ...
