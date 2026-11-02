const fs=require('fs'),assert=require('assert');
const s=fs.readFileSync('public/assistant/assistant.js','utf8');
for(const token of ['CART_REFERENCE_TTL','cartContextCommand(value)','lastCartReference=null','requestNativeCartEdit(command)','lastCartReference=command.action'])assert(s.includes(token),token);
assert(s.includes("if(!budgetCommand)lastCartReference=null"));
console.log('7.2A static context integration OK');
