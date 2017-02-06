from subprocess import Popen, PIPE
import datetime
from dateutil.parser import parse
import sys

# GET PIDs
p = Popen(["tail", "-n", "40", sys.argv[1] if len(sys.argv) > 1 else "/tmp/gpuReadings/processes.csv"], stdout=PIPE)
data, _ = p.communicate()
data = data.decode("utf-8").split("\n")

pids = []

for line in data:
    if "," not in line:
        continue
    line = line.split(",")

    try:
        date = parse(line[0])
        if (datetime.datetime.now() - date).total_seconds():
            pids.append(str(int(line[-1])))
    except:
        pass

pids = list(set(pids))

# SAVE PS
if len(pids) > 0:
    p = Popen(["ps", "-o", "pid,user,lstart", "-p", ",".join(pids)], stdout=PIPE)
    data, _ = p.communicate()
    data = data.decode("utf-8")
    ind = data.find("\n")
    print(data[ind:].strip())
