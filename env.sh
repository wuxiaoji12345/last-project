#!/bin/bash
if [[ $env == "dev" ]];then
  /application/init --env=Development --overwrite=y
elif [[ $env == "test" ]];then
  /application/init --env=Test --overwrite=y
elif [[ $env == "pre" ]];then
  /application/init --env=PreRelease --overwrite=y
elif [[ $env == "pro" ]];then
  /application/init --env=Production --overwrite=y
else
  echo 'Unknown environment type'
fi

bash -c "cd /application; echo y | ./yii migrate"