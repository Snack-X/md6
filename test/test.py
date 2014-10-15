import sys
sys.path.append("..")

from md6 import md6hash

md6 = md6hash()
f = open("result.csv", "r")
result = f.readlines()
f.close()

total = 0
ok = 0

for line in result:
	size, data, comp = line.strip().split(",")
	size = int(size)

	hash = md6.hex(data, size)

	total += 1
	if hash == comp:
		ok += 1

print "%d / %d test(s) passed." % (ok, total)