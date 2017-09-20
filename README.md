# FINDOLOGIC SHOPWARE 5 DI PLUGIN

  FINDOLOGIC SHOPWARE 5 DI PLUGIN needs to be implemented in SHOPWARE 5 eShop to export products and its data for successful implementation of FINDOLOGIC Search into SHOPWARE 5 eShop.
  
  This plug-in is going through all the shop's data and finding valid products for export. In order to be exported, following conditions must be met:
  
  * product must be set to active
  * product must have title
  * product stock must be grater that zero
  * product should not be configured as variant
  * product categories must be active
  
## INSTALLATION

  FINDOLOGIC SHOPWARE 5 API plug-in installation procedure is basically the same as for any other SHOPWARE 5 plug-in. It can be summed up in a few simple steps:
  
  * Plug-in content needs to copied into *“engine/Shopware/Plugins/Community/Frontend”* folder
  * After this, in Admin panel click on Configuration → Plugin Manager
  * In the left side menu, click on “Installed”
  * Plug-in should be listed in the uninstalled plug-ins
  * Click on the plug-in green circle to install it
  * Then you will be promted to clear cache, click “Yes” “Configuration” tab enter Shopkey provided by “FINDOLOGIC”
  * After you fill this this form, click **“Save”** 
  
  **Note**: Shop key must be entered in valid format or error will be shown
  * Finally, shop's cache must be cleared
  
## RUNNING EXPORT
  
  Export is called via URL. For example:

  *SHOP_URL/Findologic?shopkey=SHOP_KEY&start=NUMBER&count=NUMBER*
  
  Three parameters  that are necessary  for successfully running export are:
  
  * shopkey → SHOPKEY provided by FINDOLOGIC
  * start → number that should not be lower than zero
  * count → number that should not lower than zero and “start” number
  
  If any of these parameters is not according to standards, export would not be run, and error message will be displayed. Generated XML is validated against predefined xsd scheme from:

  [https://raw.githubusercontent.com/FINDOLOGIC/xml-export/master/src/main/resources/findologic.xsd](https://raw.githubusercontent.com/FINDOLOGIC/xml-export/master/src/main/resources/findologic.xsd)
  
## EXPORT CUSTOMIZATION

  If standard export does not export all data that is needed, there is a simple way to customize export. In plug-in root folder there is a file called *“findologicCustomExport_org.php”* that should be renamed to *“findologicCustomExport.php”*. That file contains class *“FindologicCustomExport”* that extends class *“Shopware_Controllers_Frontend_XmlBuilder”* that is responsible  for export. In order to change export, simply override already existing methods in  *“FindologicItemXml”* by writing new methods in “FindologicCustomExport” class. Example code is already placed in *“findologicCustomExport_org.php”*.
