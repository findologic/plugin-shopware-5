#!/usr/bin/env bash

# Get current directory of the script
ROOT_DIR=$( cd "$(dirname "${BASH_SOURCE[0]}")" ; pwd -P )

# Extract version from the plugin.xml file
VERSION_RAW="$(sed -n 's|<version>\(.*\)</version>|\1|p' ./FinSearchUnified/plugin.xml)"

# Trim the whitespaces from the version otherwise it would cause problems
# in creating the archive zip file
VERSION="$(echo -e "${VERSION_RAW}" | tr -d '[:space:]')"
echo "Version: ${VERSION}"

STASH=$(git stash)

echo ${STASH}

git fetch --all --tags --prune

TAG=$(git tag -l "v${VERSION}")

# Get current working branch
BRANCH=$(git branch | grep \* | cut -d ' ' -f2)

# If tag is available we proceed with the checkout and zip commands
# else exit with code 1
if [[ -z "${TAG}" ]]; then
    echo "[ERROR]: Tag ${TAG} not found"
    exit 1
fi

git checkout tags/${TAG}

# Copying plugins files
echo "Copying files ... "
cp -rf ./FinSearchUnified/ /tmp/FinSearchUnified

# Get into the created directory for running the archive command
cd /tmp/FinSearchUnified

# Install dependencies
composer install --no-dev

cd /tmp

# Run archive command to create the zip in the root directory
zip -r9 ${ROOT_DIR}/FinSearchUnified-${VERSION}.zip FinSearchUnified -x FinSearchUnified/phpunit.xml.dist \
FinSearchUnified/shopware-phpcs.xml FinSearchUnified/Tests/\*

# Delete the directory after script execution
rm -rf "/tmp/FinSearchUnified"

cd ${ROOT_DIR}

echo "Restoring work in progress ... "

git checkout ${BRANCH}

# Only apply stash if there are some local changes
if [[ ${STASH} != "No local changes to save" ]]; then
    git stash pop
fi
