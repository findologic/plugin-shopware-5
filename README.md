# FINDOLOGIC Shopware 5 Plugin
Needs to be installed in Shopware 5 eShop in order to export products and its data for a successful integration of the
FINDOLOGIC search.

This plugin goes through all the shop's data to find suitable products for the export. Following conditions must be met:
* Product must be active
* Product must have a title
* Product stock must be greater than zero
* Product must not be configured as variant
* Product categories must be active

# Build Status
[![Build Status](https://travis-ci.org/findologic/plugin-shopware-di.svg?branch=master)](https://travis-ci.org/findologic/plugin-shopware-di)

# Installation
FINDOLOGIC Shopware 5 plugin installation procedure is basically the same as for any other Shopware plugin. It can be
summed up in a few simple steps:
* In the admin panel click on Configuration → Plugin Manager
* In the left side menu, click on "Installed"
* Upload the packaged plugin using the "Upload plugin" button
* The plugin should be listed in the section for uninstalled plugins
* Click on the green circle to install it
* Confirm when you are asked to clear caches
* Enter your shopkey in the plugin's configuration tab
* Set the field "Active" to "Yes"
* Click "Save"
* Finally, the shop's cache must be cleared

# Export
Each export method supports three parameters:
* shopkey (required) → provided by FINDOLOGIC
* start (optional) → must not be lower than zero
* count (optional) → must not be lower than zero
* language (optional) → respective ISO code

## Frontend
FINDOLOGIC starts the export by calling an appropriate URL e.g.:
```url
http://192.168.33.10/shopware/findologic/?shopkey=SHOPKEY
```

## Console command
The following command can be executed manually or using a cron job to store export data locally on the filesystem:
```bash
$ php bin/console findologic:export SHOPKEY
```

# Customization
The plugin's search and export components are registered as services. If those do not meet your requirements, it is
advised to implement a basic plugin which extends/decorates the mentioned services.
See the official Shopware [documentation](https://developers.shopware.com/developers-guide/plugin-extension-by-plugin/) for more information.

# License
Please see [License File](LICENSE) for more information.
