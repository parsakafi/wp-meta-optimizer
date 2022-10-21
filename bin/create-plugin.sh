#!/usr/bin/env bash

gitDirName="wp-meta-optimizer"
pluginDirName="wp-meta-optimizer-plugin"
dirSeparator="/"
# parentdir="$(dirname "$PWD")"
# plugindir="${parentdir}/wp-meta-optimizer"

cd ..

if [ -e $pluginDirName ]; then
    rm -rf $pluginDirName
    echo "Delete WP plugin dir: ${pluginDirName}"
fi

if [ ! -d "$pluginDirName" ]; then
    mkdir "$pluginDirName"
    mkdir "${pluginDirName}${dirSeparator}assets"
    echo "Make WP plugin dir: ${pluginDirName}"
fi

cp "${gitDirName}${dirSeparator}WPMetaOptimizer.php" "${pluginDirName}${dirSeparator}WPMetaOptimizer.php"
cp "${gitDirName}${dirSeparator}readme.txt" "${pluginDirName}${dirSeparator}readme.txt"
cp -r "${gitDirName}${dirSeparator}inc" "${pluginDirName}${dirSeparator}inc"
cp "${gitDirName}${dirSeparator}assets${dirSeparator}wpmo.js" "${pluginDirName}${dirSeparator}assets${dirSeparator}wpmo.js"
cp "${gitDirName}${dirSeparator}assets${dirSeparator}style.min.css" "${pluginDirName}${dirSeparator}assets${dirSeparator}style.min.css"
# cp -r "${gitDirName}${dirSeparator}assets" "${pluginDirName}${dirSeparator}assets"
echo "Copy plugin files from '${gitDirName}' to '${pluginDirName}'"

zip -r "${pluginDirName}.zip" $pluginDirName
echo "zip plugin dir: ${pluginDirName}"

read -s -n 1 -p "Press any key to continue . . ."
