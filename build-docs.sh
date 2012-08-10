#!/bin/sh

phpdoc run \
    -d ./classes/ \
    -d ./controllers/ \
    -d ./middleware/ \
    -t ./public/docs/ \
    --title 'MyProfile REST API'
