const fs=require('fs'),assert=require('assert');
const s=fs.readFileSync('public/assistant/assistant.js','utf8');
for(const token of ['lastComparedProducts=null','isComparisonFollowup','askComparedPrices','sameUnit','price_cents','lastComparedProducts=choices.map','lastOwnOrderId=null;lastComparedProducts=null'])assert(s.includes(token),token);
assert(s.includes('credentials:\'same-origin\''));
console.log('7.1R static integration OK');
