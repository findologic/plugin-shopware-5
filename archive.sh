#!/usr/bin/env bash

# Get current directory of the script
ROOT_DIR=$( cd "$(dirname "${BASH_SOURCE[0]}")" ; pwd -P )

# Extract version from the plugin.xml file
VERSION_RAW="$(sed -n 's|<version>\(.*\)</version>|\1|p' ./FinSearchUnified/plugin.xml)"

# Trim the whitespaces from the version otherwise it would cause problems
# in creating the archive zip file
VERSION="$(echo -e "${VERSION_RAW}" | tr -d '[:space:]')"
echo "Version: ${VERSION}"

echo $(git stash)

git fetch --all --tags --prune

TAG=$(git tag -l "v${VERSION}")

# If tag is available we proceed with the checkout and zip commands
# else exit with code 1
if [[ -z "${TAG}" ]]
then
echo "[ERROR]: Tag v${TAG} not found"
exit 1
else
echo "Checking out ${TAG} from master .."
fi

git checkout tags/${TAG} -b master

# Copying plugins files
echo "Copying files ... "
cp -rf ./FinSearchUnified/ /tmp/FinSearchUnified

# Get into the created directory for running the archive command
cd /tmp/FinSearchUnified

# Install dependencies
composer install --no-dev

cd /tmp

# Run archive command to create the zip in the root directory
zip -r9 ${ROOT_DIR}/FinSearchUnified-${VERSION}.zip FinSearchUnified -x FinSearchUnified/phpunit.xml.dist FinSearchUnified/shopware-phpcs.xml FinSearchUnified/Tests/\*

# Delete the directory after script execution
rm -rf "/tmp/FinSearchUnified"

cd ${ROOT_DIR}

git checkout master

git stash pop
