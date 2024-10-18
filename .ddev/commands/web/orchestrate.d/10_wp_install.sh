#!/bin/bash

if [ ! -z "${RECREATE_ENV}" ]; then
  echo "Deleting database before creating a new one"
  wp db clean --yes
fi

if [ "${WP_MULTISITE}" = "true" ]; then
  wp core multisite-install \
    --title="${WP_TITLE}" \
    --admin_user="${ADMIN_USER}" \
    --admin_password="${ADMIN_PASS}" \
    --url="${DDEV_PRIMARY_URL}" \
    --admin_email="${ADMIN_EMAIL}" \
    --skip-email

readarray -d , -t slugs <<< "${WP_MULTISITE_SLUGS},"; unset "slugs[-1]";
for slug in "${slugs[@]}"; do
  if [ ! -z "${slug}" ]; then
    wp site create --slug="${slug}"
  fi
done

else
  wp core install \
    --title="${WP_TITLE}" \
    --admin_user="${ADMIN_USER}" \
    --admin_password="${ADMIN_PASS}" \
    --url="${DDEV_PRIMARY_URL}" \
    --admin_email="${ADMIN_EMAIL}" \
    --skip-email
fi
