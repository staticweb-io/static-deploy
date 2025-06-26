#!/usr/bin/env bash

set -e

rm -rf mariadb/data/*
mkdir -p mariadb/data
local-mariadb-install "$PWD/mariadb/data"
local-mariadb-run "$PWD/mariadb/data"
