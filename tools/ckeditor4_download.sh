#!/bin/bash -
CURRENT_PATH=`pwd`
echo $CURRENT_PATH

rm -rf $CURRENT_PATH/ckeditor
mkdir -p $CURRENT_PATH/ckeditor
cd ckeditor

git clone https://github.com/ckeditor/ckeditor4.git
cd $CURRENT_PATH/ckeditor/ckeditor4
git checkout 4.6.2

rm -rf $CURRENT_PATH/ckeditor/ckeditor4/.git
rm -rf $CURRENT_PATH/ckeditor/ckeditor4/tests