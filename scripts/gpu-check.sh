#!/bin/bash


if [ "$1" = "kill" ]; then
	pid=`ps -x | grep 'gpu-run.s[h] 1' | sed 's/\([0-9]\+\)\s.\+$/\1/'`
	if [ "${pid:-null}" != null ]; then
		kill $pid
		while kill -0 $pid 2> /dev/null; do sleep 0.5; done
	fi
	pid=`ps -x | grep 'gpu-run.s[h] 2' | sed 's/\([0-9]\+\)\s.\+$/\1/'`
	if [ "${pid:-null}" != null ]; then
		kill $pid
		while kill -0 $pid 2> /dev/null; do sleep 0.5; done
	fi
	pid=`ps -x | grep 'gpu-run.s[h] 3' | sed 's/\([0-9]\+\)\s.\+$/\1/'`
	if [ "${pid:-null}" != null ]; then
		kill $pid
		while kill -0 $pid 2> /dev/null; do sleep 0.5; done
	fi
else

	RESULT=`ps -x | grep "gpu-run.s[h] 1"`
	if [ "${RESULT:-null}" = null ]; then
		echo "Launch"
		HOST=$1 /home/robert/gpuMonitor/gpu-run.sh 1 &
	else
		echo "Running"
	fi

	RESULT=`ps -x | grep "gpu-run.s[h] 2"`

	if [ "${RESULT:-null}" = null ]; then
		echo "Launch"
		HOST=$1 /home/robert/gpuMonitor/gpu-run.sh 2 &
	else
		echo "Running"
	fi

	RESULT=`ps -x | grep "gpu-run.s[h] 3"`

	if [ "${RESULT:-null}" = null ]; then
		echo "Launch"
		HOST=$1 /home/robert/gpuMonitor/gpu-run.sh 3 $2 &
	else
		echo "Running"
	fi
fi
