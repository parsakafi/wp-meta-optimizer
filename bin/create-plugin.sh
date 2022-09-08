#!/usr/bin/env bash

gitDirName="wp-meta-optimizer"
pluginDirName="wp-meta-optimizer-plugin"
# parentdir="$(dirname "$PWD")"
# plugindir="${parentdir}/wp-meta-optimizer"

cd ..

if [ -e $pluginDirName ]; then
    rm -rf $pluginDirName
    echo "Delete WP plugin dir: ${pluginDirName}"
fi

if [ ! -d "$pluginDirName" ]; then
    mkdir "$pluginDirName"
    echo "Make WP plugin dir: ${pluginDirName}"
fi

cp "${gitDirName}/WPMetaOptimizer.php" "${pluginDirName}/WPMetaOptimizer.php"
cp "${gitDirName}/readme.txt" "${pluginDirName}/readme.txt"
cp -r "${gitDirName}/inc" "${pluginDirName}/inc"
cp -r "${gitDirName}/assets" "${pluginDirName}/assets"
echo "Copy plugin files from '${gitDirName}' to '${pluginDirName}'"

read -s -n 1 -p "Press any key to continue . . ."
