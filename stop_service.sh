#!/bin/bash
DIR=`dirname $0| xargs realpath`
cd $DIR
PIDS=`ps -ef | grep 'yii' | grep -v 'grep' | awk '{print $2}'`
for PID in $PIDS; do
    WORKING_DIR=`realpath /proc/${PID}/cwd`
    if [[ "$WORKING_DIR" = "$DIR" ]]; then
        kill $PID
    fi
done