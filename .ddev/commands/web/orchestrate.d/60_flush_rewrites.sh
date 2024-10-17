#!/bin/bash

wp rewrite structure '/%postname%'
wp rewrite flush
