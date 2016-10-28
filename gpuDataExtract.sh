#!/bin/sh

mkdir -p /tmp/gpuReadings

nvidia-smi --format=csv,noheader,nounits --query-gpu=index,uuid,name,memory.used,memory.total,utilization.gpu,utilization.memory,temperature.gpu,timestamp > /tmp/gpuReadings/${HOST}_gpus.csv
nvidia-smi --format=csv,noheader,nounits --query-compute-apps=gpu_uuid,pid,process_name,used_gpu_memory > /tmp/gpuReadings/${HOST}_processes.csv
ps -o pid,user,lstart -p `nvidia-smi --format=csv,noheader,nounits --query-compute-apps=pid | tr '\n' ',' | sed 's/,$//'` | tail -n +2 > /tmp/gpuReadings/${HOST}_users.csv

scp /tmp/gpuReadings/${HOST}_gpus.csv gate:/web/robert/public_html/gpu/data
scp /tmp/gpuReadings/${HOST}_processes.csv gate:/web/robert/public_html/gpu/data
scp /tmp/gpuReadings/${HOST}_users.csv gate:/web/robert/public_html/gpu/data
