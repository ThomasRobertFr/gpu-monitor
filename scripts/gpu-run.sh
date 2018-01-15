#!/bin/bash

path=/tmp/gpuReadings
mkdir -p $path

if [ "$1" -eq "1" ]; then
    nvidia-smi --format=csv,noheader,nounits --query-gpu=index,uuid,name,memory.used,memory.total,utilization.gpu,utilization.memory,temperature.gpu,timestamp -l 20 > $path/gpus.csv
fi

if [ "$1" -eq "2" ]; then
    nvidia-smi --format=csv,noheader,nounits --query-compute-apps=timestamp,gpu_uuid,used_gpu_memory,process_name,pid -l 20 > $path/processes.csv
fi

if [ "$1" -eq "3" ]; then
    while true; do
        df -l | grep " /local$" > $path/${HOST}_status.csv
        free -m | grep "Mem" >> $path/${HOST}_status.csv
        #top -b -n 1 | grep %Cpu >> $path/${HOST}_status.csv
        nproc --all >> $path/${HOST}_status.csv
        uptime >> $path/${HOST}_status.csv

        python /home/robert/gpuMonitor/gpu-processes.py $path/processes.csv > $path/${HOST}_users.csv
        echo $(uptime | grep -o -P ': \K[0-9]*[,]?[0-9]*')\;$(nproc) > $path/${HOST}_cpus.csv
        tail -n 20 $path/gpus.csv > $path/${HOST}_gpus.csv
        tail -n 40 $path/processes.csv > $path/${HOST}_processes.csv
        scp $path/${HOST}_*.csv $2/web/robert/public_html/gpu/data
        sleep 10
    done
fi
