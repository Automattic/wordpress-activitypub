#!/bin/bash

if ! wp core download --version="${WP_VERSION:-latest}"; then
 echo 'WordPress is already installed.'
 exit
fi
