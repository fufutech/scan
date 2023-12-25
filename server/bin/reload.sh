#!/bin/bash

basepath=$(cd `dirname $0`; pwd)

cd $basepath

if [ -f "../runtime/hyperf.pid" ];then

cat ../runtime/Hyperf.pid | awk '{print $1}' | xargs kill && rm -rf ../runtime/Hyperf.pid && rm -rf ../runtime/container

fi

#php Hyperf.php start
