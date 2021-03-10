#!/bin/bash
DIR=`dirname $0| xargs realpath`
cd $DIR
while read -r line
do
    ONE_SERVICE=`echo $line | cut -d ' ' -f 1`
    COUNT=`echo $line | cut -d ' ' -f 2`
    PIDS=`ps -ef | grep "$ONE_SERVICE$" | grep -v 'grep' | awk '{print $2}'`
    NUM=0
    for PID in $PIDS; do
        WORKING_DIR=`realpath /proc/${PID}/cwd`
        if [[ "$WORKING_DIR" = "$DIR" ]]; then
            ((NUM++))
        fi
    done
    if [[ "$NUM" != "$COUNT" ]]; then
        let NUM_STOPPED=$COUNT-$NUM
        SERVICE=${SERVICE}"\n"${ONE_SERVICE}" "${NUM_STOPPED}
    fi
done < 'services.txt'
if [[ "$SERVICE" != "" ]]; then
    DATA=$DIR" services stopped: "$SERVICE
#    echo -e "$DATA"
    echo -e $DATA | mail -s "Snapshot services stopped" -a "From: noreply@lingmou.ai" snapshot_dev@lingmou.ai
fi