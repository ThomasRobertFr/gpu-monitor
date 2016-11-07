# GPU Monitor

This is a tool intended to monitor the GPU usage on the various GPU-servers at the LIP6 Lab, UPMC, Paris. This code has been written with the "quickest and dirtiest" principle in mind, it is absolutely awful, please do not read it :persevere:

The principle is as follows. The script `gpuDataExtract.sh` is ran each _x_ seconds (by a looping main script or a cron). This scripts runs `nvidia-smi` and `ps` to extract data and sends them to my `public_html` space using `scp` (with SSH keys for passwordless access). Each time someone wants to see the status of the GPUs, the page `index.php` reads the latest data files for each server and displays those.
