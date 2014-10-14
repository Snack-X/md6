var md6hash = require("../md6").md6hash;
var fs = require("fs");

var md6 = new md6hash();
var result = fs.readFileSync("result.csv", "ascii");
result = result.split("\n");

var total = 0, ok = 0;

for(var i in result) {
	var line = result[i];
	if(line === "") break;

	var a = line.split(",");

	var size = parseInt(a[0], 10);
	var data = a[1];
	var comp = a[2];

	var hash = md6.hex(data, size);

	total++;
	if(hash === comp) ok++;
}

console.log("" + ok + " / " + total + " test(s) passed.");