const fs=require('fs'),assert=require('assert');
const s=fs.readFileSync('public/assistant/assistant.js','utf8');
for(const token of ['UCO_NAV_LEXICON','ucoMatchTerm','ucoIsVisible','ucoSectionMatches','guideFromQuestion','askOwnPrices','askOwnOrders','askOwnDocuments'])assert(s.includes(token),token);
assert(!s.includes('n.includes(normalizeUco(t))'),'old substring matcher remains');
console.log('7.1L knowledge integration: OK');
