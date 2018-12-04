#!/usr/bin/env bash

SHOPWARE_DIRECTORY=../../../../shopware

VERSION_RAW="$(sed -n 's|<version>\(.*\)</version>|\1|p' ./FinSearchUnified/plugin.xml)"

VERSION="$(echo -e "${VERSION_RAW}" | tr -d '[:space:]')"

echo "Version: ${VERSION}"

cp -rvf ./FinSearchUnified/ ${SHOPWARE_DIRECTORY}/tmp

echo "Copying files ... "

cd FinSearchUnified

composer install --no-dev
composer archive --format=zip --file=FinSearchUnified-${VERSION} --dir=${SHOPWARE_DIRECTORY}

rm -rf ${SHOPWARE_DIRECTORY}/tmp