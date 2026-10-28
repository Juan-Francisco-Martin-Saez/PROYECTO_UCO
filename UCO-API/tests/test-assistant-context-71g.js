/* Test estático de integración 7.1G; no sustituye una prueba en navegador. */
const fs=require('node:fs'),assert=require('node:assert/strict');
const s=fs.readFileSync(require('node:path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
for(const token of ['CTX_TTL','readCatalogContext','saveCatalogContext','clearCatalogContext','catalogSubject','isFollowup','isReset','guideFromQuestion','askUcoLogic'])assert.ok(s.includes(token),'Falta '+token);
assert.ok(!s.includes('lastSubject'),'Se ha mantenido el contexto anterior sin verificar');
console.log('OK: componentes de memoria contextual presentes');
