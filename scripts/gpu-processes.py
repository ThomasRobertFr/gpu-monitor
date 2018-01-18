from subprocess import Popen, PIPE
import datetime
from dateutil.parser import parse
import sys

# GET PIDs
p = Popen(["tail", "-n", "80", sys.argv[1] if len(sys.argv) > 1 else "/tmp/gpuReadings/processes.csv"], stdout=PIPE)
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
    import re, json

    p = Popen(["ps", "xao", "pid,ppid,pgid,sid"], stdout=PIPE)
    data, _ = p.communicate()
    data = data.decode("utf-8")
    lines = data.split("\n")[1:-1]

    childs = {}

    for x in lines:
        xs = re.sub(" +", " ", re.sub("^ +", "", x)).split(" ")
        for pid in pids:
            if (int(xs[1]) == int(pid) or int(xs[2]) == int(pid)) and xs[0] != "":
                if pid not in childs: childs[pid] = []
                childs[pid].append(xs[0])

    print(json.dumps(childs))

    p = Popen(["ps", "-o", "pid,user,lstart", "-p", ",".join(pids + sum(list(childs.values()), []))], stdout=PIPE)
    data, _ = p.communicate()
    data = data.decode("utf-8")
    ind = data.find("\n")
    print(data[ind:].strip())
