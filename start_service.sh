#!/bin/bash
DIR=`dirname $0`
cd $DIR
while read -r line
do
    service=`echo $line | cut -d ' ' -f 1`
    count=`echo $line | cut -d ' ' -f 2`
    for i in `seq 1 $count`; do
        ./yii $service &
    done
done < 'services.txt'