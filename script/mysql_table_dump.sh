#!/bin/bash

pwConfig=${PW_PATH}/site/config.php

dbHost=`sed -ne "s/\\$config->dbHost = '\([^']\+\)';/\1/p" $pwConfig`
dbPort=`sed -ne "s/\\$config->dbPort = '\([^']\+\)';/\1/p" $pwConfig`
dbName=`sed -ne "s/\\$config->dbName = '\([^']\+\)';/\1/p" $pwConfig`
dbUser=`sed -ne "s/\\$config->dbUser = '\([^']\+\)';/\1/p" $pwConfig`
dbPass=`sed -ne "s/\\$config->dbPass = '\([^']\+\)';/\1/p" $pwConfig`

table=$1

if [ -n "$table" ]; then
	mysqldump -h "$dbHost" -P "$dbPort" -u "$dbUser" -p"$dbPass" "$dbName" "$table" --compact
else
	mysql -h "$dbHost" -P "$dbPort" -u "$dbUser" -p"$dbPass" "$dbName" -sN -e 'SHOW TABLES'
fi