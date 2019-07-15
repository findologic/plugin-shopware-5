#!/usr/bin/env bash
set -ev

if [[ ${TRAVIS_BUILD_STAGE_NAME} != "Lint" ]]; then
    if [[ "$(php --version | grep -cim1 xdebug)" -ge 1 ]]; then phpenv config-rm xdebug.ini; fi

    git clone https://github.com/shopware/shopware.git ${SHOPWARE_DIRECTORY} --branch ${SHOPWARE_VERSION}
    ant -f ${SHOPWARE_DIRECTORY}/build/build.xml -Dapp.host=localhost -Ddb.user=travis -Ddb.host=127.0.0.1 -Ddb.name=shopware build-unit
    mv ${TRAVIS_BUILD_DIR}/${PLUGIN_NAME} ${PLUGIN_DIRECTORY}/${PLUGIN_NAME}
    php ${SHOPWARE_DIRECTORY}/bin/console sw:plugin:refresh
    php ${SHOPWARE_DIRECTORY}/bin/console sw:plugin:install ${PLUGIN_NAME}
    php ${SHOPWARE_DIRECTORY}/bin/console sw:plugin:activate ${PLUGIN_NAME}
    php ${SHOPWARE_DIRECTORY}/bin/console sw:cache:clear
    ${SHOPWARE_DIRECTORY}/var/cache/clear_cache.sh
fi

cd ${PLUGIN_DIRECTORY}/${PLUGIN_NAME}
